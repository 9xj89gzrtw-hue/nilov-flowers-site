<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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
       и не склеивается с ₽; совместимость шире узкого NNBSP (U+202F) */
    return number_format($v, 0, ',', "\u{00A0}") . ' ₽';
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
   new→confirmed/in_progress/canceled; confirmed→canceled/unredeemed; in_progress→confirmed/canceled
   (+«Выполнен» только через handover); canceled/unredeemed→new; done — финальный. */
function orderTransitions(): array
{
    return [
        'new' => ['confirmed', 'in_progress', 'canceled'],
        'confirmed' => ['in_progress', 'canceled', 'unredeemed'],
        'in_progress' => ['confirmed', 'canceled'],
        'canceled' => ['new'],
        'unredeemed' => ['new'],
        'done' => [],
    ];
}

function statuses(): array
{
    return [
        'new' => 'Новый',
        'confirmed' => 'Подтверждён',
        'in_progress' => 'В работе',
        'done' => 'Выполнен',
        'canceled' => 'Отменён',
        'unredeemed' => 'Не выкуплен',
    ];
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
   Идемпотентно: сбой GD → исходный файл не трогаем. */
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
    $w = imagesx($src);
    $h = imagesy($src);
    $maxSide = 1400;
    if (max($w, $h) > $maxSide) {
        $scale = $maxSide / max($w, $h);
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
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

function deleteImage(string $name, string $dir): void
{
    if ($name !== '' && !str_contains('/', $name)) {
        $path = $dir . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
