<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* W105-9fix (критики 8-a/9-a: «imagery 1x-only» — диспрув + системный фикс):
   контент-адресные URL картинок — ?v=filemtime файла.
   ПРОБЛЕМА: ленивые thumbs и webp-сиблинги живут по СТАБИЛЬНЫМ URL; после
   EXIF-фикса/поворота в админке (rotateStoredImage) или перегенерации
   пиксели меняются, а URL нет → HTTP-кэш браузера и SW отдают СТАРЫЕ
   пиксели (слепые критики трижды мерили naturalWidth 288/280 у файлов
   400px — кэш их сессий). ВЕРСИЯ = mtime: любое изменение файла = новый
   URL = чистый кэш у ВСЕХ (SW, HTTP, CDN). Работает и для «?» уже в URL. */
function static_img_v(string $url): string
{
    if ($url === '' || $url[0] !== '/') return $url;
    $root = BASE_PATH . urldecode((string)(parse_url($url, PHP_URL_PATH) ?? ''));
    $m = is_file($root) ? @filemtime($root) : false;
    return $m ? $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $m : $url;
}

/**
 * Стресс-критик W87 (stored XSS admin->посетитель): витринные rich-text поля (cookie_banner_text).
 * Whitelist: только <a href="/..."> или <a href="https://..."> без иных атрибутов.
 * Остальная разметка срезается strip_tags. Clamp длины — сервером (не maxlength).
 */
function sanitize_rich_text(string $v, int $max = 500): string
{
    $v = strip_tags($v, '<a>');
    $v = preg_replace_callback('/<a\b[^>]*>/i', function ($m) {
        $href = '';
        $x = $m[0];
        if (preg_match('/href\s*=\s*"([^"]*)"/i', $x, $h)) { $href = $h[1]; }
        elseif (preg_match('/href\s*=\s*\'([^\']*)\'/i', $x, $h)) { $href = $h[1]; }
        elseif (preg_match('/href\s*=\s*([^\s"\x27>]+)/i', $x, $h)) { $href = $h[1]; }
        $href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
        if ($href !== '' && preg_match('#^(?:/(?!/)|https://)#i', $href)) {
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" rel="nofollow">';
        }
        return '<a>';
    }, $v);
    $v = preg_replace('/<\/a\s*>/i', '</a>', $v);
    return mb_substr(trim($v), 0, $max);
}

/**
 * W100-fixH2 (J4): ссылочные настройки — защитный фильтр схем.
 * Допустимы: пусто, «#якорь», «/путь» (не «//»), «https://…», «http://…», «tel:…», «mailto:…».
 * Всё остальное (javascript:, data:, vbscript:, протокол-относительные «//», пробелы/угловые
 * скобки) — недопустимо. Используется на входе (админка) и на выводе (safe_url).
 */
function safe_url_ok(string $url): bool
{
    $u = trim($url);
    if ($u === '') { return true; }                                  /* пусто */
    if (preg_match('~^#[^\s<>"\']*$~', $u)) { return true; }          /* #якорь */
    if (preg_match('~^/(?!/)[^\s<>"\']*$~', $u)) { return true; }     /* /путь, не //host */
    if (preg_match('~^https?://[^\s<>"\']+$~i', $u)) { return true; } /* http(s)://… */
    if (preg_match('~^(?:tel|mailto):[^\s<>"\']+$~i', $u)) { return true; }
    return false;
}

/** W100-fixH2 (J4): defensive-фильтр на выводе — недопустимая схема заменяется на «#».
 *  Значение не экранируется: оборачивать в e() при выводе в href. */
function safe_url(string $url): string
{
    return safe_url_ok($url) ? trim($url) : '#';
}

/**
 * W100-fixH2 (J4): идентификатор мессенджера/каталога (номер WhatsApp, имя канала Telegram,
 * VK-хэндл, ID организации на Яндекс Картах) — витрина вклеивает его в свой URL
 * (wa.me/{цифры}, t.me/{имя}, vk.com/{имя}, yandex.ru/maps/org/{id}), поэтому полное
 * «href»-значение тут не нужно: достаточно отсутствия схем, пробелов и разметки
 * (двоеточие/слэши запрещены — javascript: и протокол-относительные ссылки невозможны).
 */
function safe_url_identifier_ok(string $v): bool
{
    $v = trim($v);
    if ($v === '') { return true; }
    return preg_match('~^[A-Za-z0-9_@.\-+()\s]{1,128}$~', $v) === 1
        && !preg_match('~[:/]~', $v);
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function allSettings(): array
{
    $out = [];
    foreach (db()->query('SELECT key, value FROM settings') as $row) {
        $out[$row['key']] = $row['value'];
    }
    return $out;
}

function saveSettings(array $values): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    foreach ($values as $k => $v) {
        $stmt->execute([':k' => $k, ':v' => (string)$v]);
    }
}

function formatPrice(int $v): string
{
    /* W98-fixE (E16): тысячи — через NBSP (U+00A0): «2 500 ₽» не рвётся по строкам
       и не склеивается с ₽; совместимость шире узкого NNBSP (U+202F).
       W105-6fix2 (критик-типограф 6-b, P0): NBSP и ПЕРЕД ₽ — 79 из 83 цен
       витрины шли с обычным пробелом (склейку фронтенда дублирует js/glue.js) */
    return number_format($v, 0, ',', "\u{00A0}") . "\u{00A0}₽";
}

/* W98-fixE (E16): «голое» число с тем же NBSP-разделителем — чипы цен, фильтр
   каталога, сноски зон (формат единый с formatPrice на всей витрине) */
function formatSum(int $v): string
{
    return number_format($v, 0, ',', "\u{00A0}");
}

function productPrice(array $p): int
{
    return ($p['sale_price'] !== null && $p['sale_price'] !== '' && (int)$p['sale_price'] > 0)
        ? (int)$p['sale_price'] : (int)$p['price'];
}

/* Критик-покупатель B1 (critical): карточка и страница товара подставляли РАЗНЫЕ
   демо-фото товару без своего image (позиция в списке против id%3). Единый
   детерминированный фолбэк: одно и то же фото всегда у одного и того же id. */
function productImageFile(array $p): string
{
    if (($p['image'] ?? '') !== '') return $p['image'];
    $demo = ['roz.jpg', 'p2.jpg', 'p3.jpg'];
    return $demo[((int)($p['id'] ?? 0)) % count($demo)];
}

function slugify(string $s): string
{
    $trans = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
        'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
        'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, $trans);
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
    return trim($s, '-') ?: 'product-' . time();
}


/** Русское склонение существительного по числу: pluralRu(5, ['товар','товара','товаров']) → «товаров».
    Владелец-критик W44: «товар(а/ов)» без человеческой формы. */
function pluralRu(int $n, array $forms): string
{
    $n = abs($n) % 100;
    $k = $n % 10;
    if ($n > 10 && $n < 20) return $forms[2];
    if ($k > 1 && $k < 5) return $forms[1];
    if ($k === 1) return $forms[0];
    return $forms[2];
}

/* W70 (владелец NEW-2): ОДИН словарь переходов для ленты и деталки.
   S3 (v2026.3): курьерский поток этапов — new → photo (согласование фото) →
   florist (флорист собирает) → courier (у курьера) → done (доставлен);
   canceled/unredeemed — возврат в новые; done — финален. Прежние
   confirmed/in_progress мигрированы в photo/florist (db.php S3). */
function orderTransitions(): array
{
    return [
        'new' => ['photo', 'florist', 'courier', 'done', 'canceled'],
        'photo' => ['florist', 'courier', 'done', 'canceled'],
        'florist' => ['photo', 'courier', 'done', 'canceled'],
        'courier' => ['done', 'canceled'],
        'canceled' => ['new'],
        'unredeemed' => ['new'],
        'done' => [],
    ];
}

/* S3 (v2026.3): этапы канбана в порядке следования (владелец/флористы).
   statuses() — полный словарь (включая отменённые/невыкупленные). */
function kanbanStages(): array
{
    return ['new', 'photo', 'florist', 'courier', 'done'];
}

function statuses(): array
{
    return [
        'new' => 'Новый',
        'photo' => 'Согласование фото',
        'florist' => 'Флорист собирает',
        'courier' => 'У курьера',
        'done' => 'Доставлен',
        'canceled' => 'Отменён',
        'unredeemed' => 'Не выкуплен',
    ];
}

/* S3 (v2026.3): Яндекс Сплит / Долями — платёж делится на N частей
   (настройка split_divider, по умолчанию 4). Первую платят сразу,
   витрина показывает «от X ₽/мес»: цена / делитель, округление вверх,
   чтобы не обещать меньше минимального платежа. */
function splitMonthly(int $price): int
{
    $div = max(2, (int)setting('split_divider', '4'));
    return (int)ceil($price / $div);
}

/* S3: печать шильдика Сплит по шаблону настройки split_label
   ('Сплит: от {price} ₽/мес'). Пустая настройка split_enabled — не печатаем. */
function splitLabel(int $price): string
{
    if (setting('split_enabled', '1') !== '1' || $price <= 0) {
        return '';
    }
    /* S4: {div} — число платежей (split_divider); формат по умолчанию
       «Сплит: от N ₽ × 4» (старые кастомы без {div} продолжают работать). */
    return str_replace(
        ['{price}', '{div}'],
        [formatSum(splitMonthly($price)), (string)(int)setting('split_divider', '4')],
        setting('split_label', 'Сплит: от {price} ₽ × {div}')
    );
}

/* S3: список тегов товара → массив нижнего регистра ('розы, монобукеты' →
   ['розы','монобукеты']). Список чипсов каталога — настройка chips_tags. */
function productTagsList(array $p): array
{
    $raw = (string)($p['tags'] ?? '');
    if ($raw === '') {
        return [];
    }
    $out = [];
    foreach (explode(',', $raw) as $t) {
        $t = mb_strtolower(trim($t), 'UTF-8');
        if ($t !== '') {
            $out[] = $t;
        }
    }
    return $out;
}

/**
 * Сохраняет загруженное изображение с проверкой MIME.
 * @return string имя файла в каталоге или ''
 */
function saveUpload(array $file, string $dir): string
{
    if (!isset($file['tmp_name']) || $file['tmp_name'] === '' || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return '';
    }
    if ($file['size'] > 12 * 1024 * 1024) {
        return '';
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return '';
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (string)($info['mime'] ?? '');
    if (!isset($allowed[$mime])) {
        return '';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return '';
    }

    /* W4: нормализация «телефонных» фото уже ПОСЛЕ move (иначе move перезатрёт):
       resize 1400px, мягкий контраст, нейтрализация жёлтого, webp-копия.
       GD недоступен/сбой → файл остаётся как загрузили. */
    normalizeUpload($dir . '/' . $name, $allowed[$mime]);

    return $name;
}

/* Нормализация фото из телефона (W4): только GD, без внешних зависимостей.
   1) resize до 1400px по длинной стороне (витрина ~700px CSS × 2 retina);
   2) +brightness 4 / контраст мягкий — телефонные фото часто тёмные и серые;
   3) +7 синего — гасит жёлтую тональность ламп накаливания;
   4) webp-копия рядом — карточки каталога подхватывают через <picture>.
   Идемпотентно: сбой GD → исходный файл не трогаем.
   W105 (3-b, баг «фото боком»): ДО ресайза применяется EXIF-ориентация
   (applyExifOrientation) — поворот меняет W/H, а GD-сохранение срезает EXIF,
   поэтому без этого шага все производные (webp-сиблинг, thumbs) наследовали
   лежащие боком пиксели навсегда. */
function normalizeUpload(string $path, string $ext): void
{
    if (!function_exists('imagecreatefromjpeg')) {
        return;
    }
    $src = match ($ext) {
        'jpg' => @imagecreatefromjpeg($path),
        'png' => @imagecreatefrompng($path),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
    if ($src === false) {
        return;
    }
    /* W105 (3-b): EXIF-ориентация ДО ресайза (rotate меняет W/H). Дешёвый
       guard для webp/png: exif_read_data там не читается → вернётся как есть. */
    $src = applyExifOrientation($src, $path);
    $w = imagesx($src);
    $h = imagesy($src);
    $maxSide = 1400;
    if (max($w, $h) > $maxSide) {
        $scale = $maxSide / max($w, $h);
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        if ($ext === 'png' || $ext === 'webp') {
            /* W105 (3-b): альфа PNG/WebP не должна заливаться чёрным
               при перекодировке через truecolor-ресайз */
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }
    @imagefilter($src, IMG_FILTER_BRIGHTNESS, 4);
    @imagefilter($src, IMG_FILTER_CONTRAST, -6);
    @imagefilter($src, IMG_FILTER_COLORIZE, 0, 0, 7);
    $ok = match ($ext) {
        'jpg' => @imagejpeg($src, $path, 88),
        'png' => @imagepng($src, $path, 6),
        'webp' => function_exists('imagewebp') ? @imagewebp($src, $path, 88) : false,
        default => false,
    };
    if ($ok && function_exists('imagewebp') && $ext !== 'webp') {
        @imagewebp($src, preg_replace('/\.(jpe?g|png)$/i', '.webp', $path), 80);
    }
    /* PHP 8.0+: imagedestroy() не нужен и deprecated с 8.5 — опускаем. */
}

/* W105 (3-b, «фото грузятся повёрнутыми»): EXIF-ориентация телефонных фото
   (iPhone/Android пишут Orientation 6/8/3 и повёрнутые пиксели) — применяется
   к ХРАНЯЩИМСЯ пикселям ДО ресайза и ДО GD-сохранения (GD срезает EXIF, тег
   ориентации теряется безвозвратно). Возвращает уже выпрямленный GdImage,
   уничтожая исходный ресурс при повороте (imagerotate возвращает НОВУЮ картинку).
   Карта тегов (что сделать с пикселями, чтобы встали прямо):
     2 — зеркально по горизонтали          5 — на 90° по часовой + зеркально
     3 — на 180°                          6 — на 90° по часовой
     4 — зеркально по вертикали           7 — на 90° против часовой + зеркально
                                          8 — на 90° против часовой
   imagerotate() в GD крутит ПРОТИВ часовой стрелки, поэтому «-90» = по часовой.
   PNG/WebP/GIF без читаемого EXIF проходят насквозь без изменений. */
function applyExifOrientation(GdImage $img, string $path): GdImage
{
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($path, 'IFD0', false);
    if (!is_array($exif)) {
        return $img;
    }
    $o = (int)($exif['Orientation'] ?? 1);
    if ($o < 2 || $o > 8) {
        return $img;
    }
    $angle = match ($o) {
        3 => 180,
        5, 6 => -90,
        7, 8 => 90,
        default => 0, /* 2 и 4 — только зеркалим, без поворота */
    };
    if ($angle !== 0) {
        /* прозрачный фон: у 90/180/270 покрытие полное и он не виден, но
           альфа-канал PNG/WebP не заливается чёрным при пересборке */
        $bg = @imagecolorallocatealpha($img, 0, 0, 0, 127);
        $rotated = $bg === false ? false : @imagerotate($img, $angle, $bg);
        if ($rotated instanceof GdImage) {
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
            imagedestroy($img);
            $img = $rotated;
        }
    }
    if (in_array($o, [2, 5, 7], true)) {
        @imageflip($img, IMG_FLIP_HORIZONTAL);
    } elseif ($o === 4) {
        @imageflip($img, IMG_FLIP_VERTICAL);
    }
    return $img;
}

/* W105 (3-b): ручной поворот УЖЕ сохранённого файла (админ-кнопки ⟲ ⟳ ↕) —
   для фото, загруженных до фикса EXIF: их ориентационный тег срезан старой
   GD-перекодировкой, авторотация невозможна — крутим пиксели руками.
   Сохраняет в тот же файл (JPEG q92), затем сносит производные от старых
   пикселей: webp-сиблинг рядом ({base}.webp) и thumbs/{base}-*.webp —
   витрина лениво перегенерирует их из повёрнутого оригинала.
   $rot: 'ccw' — против часовой, 'cw' — по часовой, 'half' — 180°. */
function rotateStoredImage(string $path, string $rot): bool
{
    $angle = match ($rot) {
        'ccw' => 90,
        'cw' => -90,
        'half' => 180,
        default => 0,
    };
    if ($angle === 0 || !function_exists('imagecreatefromjpeg')) {
        return false;
    }
    if (!is_file($path)) {
        return false;
    }
    $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
    $src = match ($ext) {
        'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
        'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        'gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false,
        default => false,
    };
    if (!($src instanceof GdImage)) {
        return false;
    }
    $alpha = in_array($ext, ['png', 'webp', 'gif'], true);
    $bg = $alpha ? @imagecolorallocatealpha($src, 0, 0, 0, 127) : @imagecolorallocate($src, 0, 0, 0);
    $rotated = $bg === false ? false : @imagerotate($src, $angle, $bg);
    imagedestroy($src);
    if (!($rotated instanceof GdImage)) {
        return false;
    }
    if ($alpha) {
        /* логотип/favicon в PNG — прозрачность обязана пережить поворот */
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
    }
    $ok = match ($ext) {
        'jpg', 'jpeg' => @imagejpeg($rotated, $path, 92),
        'png' => @imagepng($rotated, $path, 6),
        'webp' => function_exists('imagewebp') ? @imagewebp($rotated, $path, 92) : false,
        'gif' => @imagegif($rotated, $path),
        default => false,
    };
    imagedestroy($rotated);
    if (!$ok) {
        return false;
    }
    /* производные от старых пикселей больше не годятся */
    $base = (string)(preg_replace('/\.[^.]+$/', '', basename($path)) ?? basename($path));
    $dir = dirname($path);
    if ($ext !== 'webp') {
        @unlink($dir . '/' . $base . '.webp');
    }
    foreach (glob($dir . '/thumbs/' . $base . '-*.webp') ?: [] as $stale) {
        @unlink($stale);
    }
    return true;
}

/* W105 (3-b, админ): группа кнопок ручного поворота уже загруженного фото.
   Вызывается в admin/products.php и admin/settings.php рядом с превью.
   Кнопки привязываются к ОТДЕЛЬНОЙ форме через атрибут form= (вложенность
   <form> в HTML запрещена, а кнопки лежат внутри большой формы редактирования;
   паттерн уже используется bulk-формой в admin/products.php). Сама форма
   печатается отдельно — rotateControlsForm() — вне основной формы страницы. */
function rotateControlsButtons(string $formId): void
{
    $btn = static function (string $rot, string $label, string $title) use ($formId): string {
        return '<button type="submit" form="' . e($formId) . '" name="rot" value="' . e($rot)
            . '" class="btn btn--ghost" title="' . e($title) . '" aria-label="' . e($title)
            . '" style="min-width:44px;min-height:44px;font-size:1.05rem;line-height:1;padding:6px 12px">'
            . $label . '</button>';
    };
    echo '<div style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:8px">'
        . '<span style="font-size:.78rem;color:var(--ink-soft)">Фото боком? Повернуть:</span>'
        . $btn('ccw', '⟲', 'Повернуть на 90° против часовой стрелки')
        . $btn('cw', '⟳', 'Повернуть на 90° по часовой стрелке')
        . $btn('half', '↕', 'Развернуть на 180°')
        . '</div>';
}

/* W105 (3-b, админ): невидимая форма-носитель для rotateControlsButtons().
   $hidden — скрытые поля (action=rotate_img + id товара или key настройки). */
function rotateControlsForm(string $formId, array $hidden): void
{
    $h = csrf_field();
    foreach ($hidden as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
    }
    echo '<form method="post" id="' . e($formId) . '" style="display:none">' . $h . '</form>';
}

function deleteImage(string $name, string $dir): void
{
    if ($name !== '' && !str_contains($name, '/')) { /* W101 (security): аргументы были перепутаны — guard был мёртв */
        $path = $dir . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
