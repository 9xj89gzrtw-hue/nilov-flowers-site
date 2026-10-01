<?php
/* Карточка товара: /product/{slug} (роутер) или product.php?slug=...
   Редизайн 5cv (W96/T2-d): сетка .fc-product, бейджи «Хит/Премиум/Скидка»,
   мета-блок доставки/оплаты, карусель «С этим берут».
   JS-контракты сохранены: #productGalleryTrack/.product-gallery__slide/
   #productGalleryThumbs .product-gallery__thumb/[data-gnav] (product-gallery.js),
   [data-order-cta] (cart-cta.js), [data-lightbox-trigger] (lightbox.js). */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$slug = (string)($_GET['slug'] ?? '');
/* Покупатель-критик W40: старые/внешние ссылки product.php?id=N → 301 на канонический slug-URL
   (было 404). SEO-критик: /product/slug/ со слэшем → 301 без слэша. */
if ($slug === '' && isset($_GET['id'])) {
    $legacyId = (int)$_GET['id'];
    if ($legacyId > 0) {
        $lst = db()->prepare('SELECT slug FROM products WHERE id = :i AND is_active = 1');
        $lst->execute([':i' => $legacyId]);
        $legacySlug = (string)$lst->fetchColumn();
        if ($legacySlug !== '') {
            header('Location: /product/' . rawurlencode($legacySlug), true, 301);
            exit;
        }
    }
}
$canonicalUrl = 'https://flowers.interfood-catering.ru/product/' . rawurlencode($slug);
$stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = :s AND p.is_active = 1');
$stmt->execute([':s' => $slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Товар не найден';
    require __DIR__ . '/partials/head.php';
    echo '<title>' . e($pageTitle) . '</title></head><body>';
    require __DIR__ . '/partials/header.php';
    echo '<main id="main" tabindex="-1"><section class="fc-section"><div class="wrap" style="max-width:560px;text-align:center">'
        . '<h1 class="page-hero__title">Товар не найден</h1>'
        . '<p class="section-sub" style="margin:0 auto 24px">Такого букета сейчас нет в продаже — свежие ждут в каталоге.</p>'
        . '<a class="btn btn--accent" href="/#catalog">В каталог</a></div></section></main>';
    require __DIR__ . '/partials/footer.php';
    echo '</body></html>';
    exit;
}

$price = productPrice($product);
$isSale = $price !== (int)$product['price'];
/* W105-9fix: ?v=filemtime в URL (контент-адресность); чистые пути — отдельно
   для webp-пары и файловых проверок */
$imgRoot = urldecode('/img/products/' . rawurlencode(productImageFile($product)));
$img = static_img_v($imgRoot);

/* WebP-пара к фото товара (каталог уже отдаёт webp через <picture>) */
$imgWebpPath = (string)preg_replace('/\.(jpe?g|png)$/i', '.webp', $imgRoot);
$imgWebpOk = $imgWebpPath !== $imgRoot && is_file(BASE_PATH . $imgWebpPath);
$imgWebp = $imgWebpOk ? static_img_v($imgWebpPath) : '';

/* W97-fixB3b (B3b-1g): категория товара — слаг на лету из name (колонки slug
   в таблице categories нет): хлебные крошки и «Смотреть все» related ведут на
   посадочную /category/{slug} вместо глубокой ссылки /?category=ID#catalog */
$catSlug = !empty($product['category_name']) ? slugify((string)$product['category_name']) : '';

/* W97-fixB3b (B3b-2h): размеры og:image (@-guard: файл недоступен — не печатаем) */
$ogDim = @getimagesize(IMG_PRODUCTS_DIR . '/' . productImageFile($product));
$origW = $ogDim !== false ? (int)$ogDim[0] : 0;

/* W97-fixB3b (B3b-2d/e): локальные GD-превью — копия подхода product_img_thumb
   из index.php (W96-fix3a T2): img/products/thumbs/{имя без ext}-{W}.webp (q78),
   ленивая генерация + tmp+rename против гонок, PNG-альфа, оригинал ≤W / сбой
   GD → '' (деградация до прежнего вида). 400 — related-карточки, 600/900 —
   LCP-слайды галереи. */
function product_img_size(array $p, int $targetW): string
{
    static $cache = [];
    if (($p['image'] ?? '') === '') return '';
    $file = (string)$p['image'];
    $ck = $file . '@' . $targetW;
    if (isset($cache[$ck])) return $cache[$ck];

    $fail = static function () use (&$cache, $ck): string {
        $cache[$ck] = '';
        return '';
    };
    $src = IMG_PRODUCTS_DIR . '/' . $file;
    if (!is_file($src)) return $fail();
    $dim = @getimagesize($src);
    if ($dim === false) return $fail();
    [$srcW, $srcH, $type] = [(int)$dim[0], (int)$dim[1], (int)$dim[2]];
    /* Компактный/равный оригинал — превью не даёт экономии, не апскейлим */
    if ($srcW <= $targetW) return $fail();

    $base = preg_replace('/\.[^.]+$/', '', $file) ?? $file;
    $thumbsDir = IMG_PRODUCTS_DIR . '/thumbs';
    $dst = $thumbsDir . '/' . $base . '-' . $targetW . '.webp';
    $url = '/img/products/thumbs/' . rawurlencode($base . '-' . $targetW . '.webp');
    if (is_file($dst)) return static_img_v($url);

    if (!is_dir($thumbsDir) && !@mkdir($thumbsDir, 0755, true)) return $fail();
    $srcIm = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => false,
    };
    if ($srcIm === false) return $fail();

    $w = $targetW;
    $h = max(1, (int)round($srcH * $w / $srcW));
    $dstIm = imagecreatetruecolor($w, $h);
    if ($type === IMAGETYPE_PNG) {
        /* PNG может нести альфу — сохраняем прозрачность (webp её умеет) */
        imagealphablending($dstIm, false);
        imagesavealpha($dstIm, true);
        imagefill($dstIm, 0, 0, imagecolorallocatealpha($dstIm, 0, 0, 0, 127));
    }
    $copied = imagecopyresampled($dstIm, $srcIm, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
    imagedestroy($srcIm);
    if (!$copied) {
        imagedestroy($dstIm);
        return $fail();
    }
    $tmp = $dst . '.tmp' . getmypid();
    $written = @imagewebp($dstIm, $tmp, 78);
    imagedestroy($dstIm);
    if (!$written || !@rename($tmp, $dst)) {
        if (is_file($tmp)) @unlink($tmp);
        return $fail();
    }
    return static_img_v($url);
}

/* Фактическая ширина оригинала — честный {w}-дескриптор srcset (копия index.php) */
function product_img_width(array $p): int
{
    static $cache = [];
    if (($p['image'] ?? '') === '') return 0;
    $file = (string)$p['image'];
    if (!isset($cache[$file])) {
        $dim = @getimagesize(IMG_PRODUCTS_DIR . '/' . $file);
        $cache[$file] = $dim === false ? 0 : (int)$dim[0];
    }
    return $cache[$file];
}

/* W99-fixG (G1): обрезка для meta description по границе ПРЕДЛОЖЕНИЯ.
   Старый алгоритм (по слову + снятие пунктуации) оставлял висячие предлоги
   («…в.», «около.», «Повод.») у 12/23 товаров. Новый порядок:
   (а) набираем ЦЕЛЫЕ предложения (split по '. '), пока сумма ≤ лимита;
   (б) первое предложение длиннее лимита — режем по границе слова;
   (в) снимаем хвостовые «висюльки»: последнее слово-введение без continuation
       (предлоги/союзы и подписи мета-полей описания: Повод/Состав/Размер/…),
       до 4 итераций («Диаметр — около» → «Диаметр —» → «Диаметр» → «…»);
   (г) точку после предлога/подписи не оставляем — хвост чистый. */
function meta_cut(string $s, int $max): string
{
    $s = trim((string)preg_replace('/\s+/u', ' ', $s));
    if ($s === '' || mb_strlen($s) <= $max) return $s;

    /* (а)+(б): предложения — от '. ', '! ', '? ', '… ' */
    $sentences = (array)preg_split('/(?<=[.!?…])\s+/u', $s);
    $out = '';
    foreach ($sentences as $sent) {
        $sent = trim($sent);
        if ($sent === '') continue;
        if ($out === '') {
            if (mb_strlen($sent) <= $max) {
                $out = $sent;
                continue;
            }
            /* (б): режем по границе слова внутри первого предложения */
            $cut = mb_substr($sent, 0, $max);
            $sp = mb_strrpos($cut, ' ');
            $out = ($sp !== false && $sp > 0) ? mb_substr($cut, 0, $sp) : $cut;
            break;
        }
        if (mb_strlen($out . ' ' . $sent) <= $max) {
            $out .= ' ' . $sent;
        } else {
            break;
        }
    }

    /* (в)+(г): хвостовые висюльки и пунктуация после среза */
    $dangling = '/^(?:в|во|на|с|со|до|по|для|или|около|при|и|а|но|что|повод|состав|размер|упаковка|диаметр|сторона|порция|высота|вес)$/ui';
    for ($i = 0; $i < 4; $i++) {
        $out = trim((string)preg_replace('/[\s.,;:!?…\-–—]+$/u', '', $out));
        if ($out === '') break;
        if (preg_match('/(\S+)$/u', $out, $m) === 1 && preg_match($dangling, $m[1]) === 1) {
            $out = (string)preg_replace('/\S+$/u', '', $out);
        } else {
            break;
        }
    }
    return trim((string)preg_replace('/[\s.,;:!?…\-–—]+$/u', '', $out));
}

/* B3b-2e: srcset LCP-фото — webp-превью 600w/900w + webp-оригинал {w}w.
   Слот галереи: .fc-product grid 1fr 1fr от 960px (css/five.css) при wrap 1200/
   gap 40 → ~560px; 820–959 — ещё одноколоночный (display ~до 920px), поэтому
   sizes = 100vw до 959 (раньше до 820 — на 900px-экранах браузер брал 600w
   и апскейлил). На 390/DPR1 выбирается 600w, ретина — 900w/оригинал.
   Превью не апскейлятся (оригиналы 582–900px: у ≤600px остаётся только
   оригинал, как раньше). */
$thumb600 = product_img_size($product, 600);
$thumb900 = product_img_size($product, 900);
$galSrcset = [];
if ($thumb600 !== '') {
    $galSrcset[] = $thumb600 . ' 600w';
}
if ($thumb900 !== '') {
    $galSrcset[] = $thumb900 . ' 900w';
}
if ($imgWebpOk && $origW > 0) {
    $galSrcset[] = $imgWebp . ' ' . $origW . 'w';
}
$galSrcsetStr = implode(', ', $galSrcset);
$galSizes = '(max-width:959px) 100vw, 560px';

/* B5/W106 (фото-волна B1, манифест state/b1-manifest.md): второй НАСТОЯЩИЙ
   ракурс галереи — products.image2. Есть файл → слайд 2 = реальное фото
   (вместо псевдослайда «Крупный план» из основного снимка) + миниатюры
   (#productGalleryThumbs — контракты js/product-gallery.js). URL/превью —
   та же конвенция, что у основного фото: ?v=filemtime (static_img_v),
   webp-пара, thumbs 600w + честный {w}-дескриптор webp-оригинала (864px —
   потолок плотности, файлов больше нет; апскейлить нечестно). */
$image2File = trim((string)($product['image2'] ?? ''));
$image2Ok = $image2File !== '' && is_file(IMG_PRODUCTS_DIR . '/' . $image2File);
$image2 = '';
$image2Webp = '';
$image2WebpOk = false;
$image2Thumb600 = '';
$image2SrcsetStr = '';
$image2Dim = false;
if ($image2Ok) {
    $image2Root = urldecode('/img/products/' . rawurlencode($image2File));
    $image2 = static_img_v($image2Root);
    $image2WebpPath = (string)preg_replace('/\.(jpe?g|png)$/i', '.webp', $image2Root);
    $image2WebpOk = $image2WebpPath !== $image2Root && is_file(BASE_PATH . $image2WebpPath);
    $image2Webp = $image2WebpOk ? static_img_v($image2WebpPath) : '';
    $image2Thumb600 = product_img_size(['image' => $image2File], 600);
    $image2Dim = @getimagesize(IMG_PRODUCTS_DIR . '/' . $image2File);
    $image2OrigW = $image2Dim !== false ? (int)$image2Dim[0] : 0;
    $__ss2 = [];
    if ($image2Thumb600 !== '') {
        $__ss2[] = $image2Thumb600 . ' 600w';
    }
    if ($image2WebpOk && $image2OrigW > 0) {
        $__ss2[] = $image2Webp . ' ' . $image2OrigW . 'w';
    }
    $image2SrcsetStr = implode(', ', $__ss2);
}
/* Лайтбокс второго слайда — полноформатный webp (иначе jpg), тот же выбор,
   что у первого слайда (B3b-2f) */
$image2Lb = $image2WebpOk ? $image2Webp : $image2;

/* B3b-2f: zoom-слайд — фон больше не в инлайн-стиле HTML (.jpg грузился сразу
   вторым дублем); js/product-gallery.js подставит его лениво по активации
   слайда. URL — webp (полноформатный twin, иначе 900-превью, иначе оригинал). */
$zoomSrc = $imgWebpOk ? $imgWebp : ($thumb900 !== '' ? $thumb900 : $img);

/* B3b-2b → W99-fixG (G7) → B5/W106 (копирайтер 6.4): title «{name} — с
   доставкой по Санкт-Петербургу | бренд». Длинное имя (>40 симв.) ИЛИ итог
   длиннее 70 (Google режет ~60, окно 60–70) — суффикс доставки опускаем:
   «{name} | Nilov Flowers»; короткое — как раньше. */
$__titleFull = $product['name'] . ' — с доставкой по Санкт-Петербургу | ' . setting('shop_name', 'Nilov Flowers');
$pageTitle = (mb_strlen($product['name']) > 40 || mb_strlen($__titleFull) > 70)
    ? $product['name'] . ' | ' . setting('shop_name', 'Nilov Flowers')
    : $__titleFull;

/* B3b-2c: meta description — первые ~130 симв. описания (теперь 200–400 симв.)
   + хвост « Доставка по СПб в день заказа, оплата при получении.» (53 симв.).
   Приоритет — итог ~160: описание режем до 160−53=107 по границе слова
   (короткие описания — целиком); срез без точки добиваем точкой. */
$metaDescTail = ' Доставка по СПб в день заказа, оплата при получении.';
$metaDescBase = meta_cut($product['description'] !== '' ? $product['description'] : $product['name'], 107);
if ($metaDescBase !== '' && !preg_match('/[.!?…]$/u', $metaDescBase)) {
    $metaDescBase .= '.';
}
$metaDesc = $metaDescBase . $metaDescTail;

/* Бейджи 5cv (is_hit/is_premium может не быть в старой БД — читаем через ?? 0) */
$isHit = (int)($product['is_hit'] ?? 0) === 1;
$isPremium = (int)($product['is_premium'] ?? 0) === 1;
$isUrgent = (int)($product['is_urgent'] ?? 0) === 1;
$offPct = $isSale && (int)$product['price'] > 0 ? (int)round((1 - $price / (int)$product['price']) * 100) : 0;

/* Тумблер избранного — тот же, что у каталога витрины (критерий 16):
   сердечко у CTA (B3b-4) и в related-карточках */
$featFavorites = setting('feature_favorites', '1') === '1';

/* Trust list на странице товара — те же гарантии */
$trust = [];
for ($i = 1; $i <= 6; $i++) {
    $g = setting('guarantee_' . $i);
    if ($g !== '') {
        $trust[] = $g;
    }
}
if ($trust === []) {
    $trust = array_values(array_filter(array_map('trim', explode("\n", setting('guarantees')))));
}

/* W98-fixE (E9): «сладкие» товары (клубника/макаруны) — не цветы: строки-гарантии
   «Свежие цветы…» и «Заменяем увядшие…» к ним не относятся и вводят в заблуждение.
   «Фото букета до отправки» (E-e3: было «перед отправкой») оставляем — актуально
   и для сладких дополнений. Признак категории — слаг из имени (колонки slug нет);
   фолбэк — lookup по id. */
$__catSlugEff = $catSlug;
if ($__catSlugEff === '' && !empty($product['category_id'])) {
    $__catStmt = db()->prepare('SELECT name FROM categories WHERE id = :i LIMIT 1');
    $__catStmt->execute([':i' => (int)$product['category_id']]);
    $__catName = (string)$__catStmt->fetchColumn();
    $__catSlugEff = $__catName !== '' ? slugify($__catName) : '';
}
if ($__catSlugEff === 'sladkie-podarki') {
    $trust = array_values(array_filter(
        $trust,
        static fn (string $t): bool => !in_array(
            mb_strtolower(trim($t)),
            ['свежий срез каждое утро', 'заменяем увядшие в день доставки'],
            true
        )
    ));
    /* W101 (редактор) → E-e3: «Фото букета … отправкой» о клубнике — не о
       букете; матчим и старый («перед»), и новый («до») канон, замена —
       «Фото до отправки» (единая терминология волны E) */
    $trust = array_map(
        static fn (string $t): string => (mb_stripos($t, 'Фото букета перед отправкой') !== false || mb_stripos($t, 'Фото букета до отправки') !== false)
            ? 'Фото до отправки' : $t,
        $trust
    );
}

/* Мета-блок 5cv: доставка / самовывоз / оплата (тексты — из настроек) */
$pickupAddr = trim(setting('pickup_address', '')) ?: trim(setting('shop_address', ''));
$ykOn = setting('yk_enabled', '0') === '1' && trim(setting('yk_shop_id', '')) !== '' && trim(setting('yk_secret_key', '')) !== '';

/* Иконки мета-блока (inline SVG в стиле витрины; размер/цвет задаёт .fc-product__meta-icon) */
$metaIcons = [
    'truck' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 6h11v11H2z"/><path d="M13 9h4l3 3v5h-3"/><circle cx="5.5" cy="17.5" r="2"/><circle cx="16.5" cy="17.5" r="2"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
    'map' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>',
    'card' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
    'camera' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
    'flower' => '<svg viewBox="0 0 32 32" aria-hidden="true"><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor"/><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(72 16 16)"/><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(144 16 16)"/><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(216 16 16)"/><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(288 16 16)"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
];
/* Гарантии: фото → камера, свежесть → цветок, остальное → щит */
$trustIconKeys = ['camera', 'flower', 'shield'];

/* W104: соответствие ключа чипа → индекс подписи в pdp_spec_captions */
$specCaptionKeys = ['ruler' => 0, 'clock' => 1, 'tag' => 2, 'sprout' => 3];

/* W103/F3 → W104: спек-чипы из описания — editorial-формат: значение
   Playfair italic + подпись Montserrat caps 10px muted (иконки убраны —
   «заливные теги» выглядели дёшево). Подписи — setting pdp_spec_captions
   «Размер|Свежесть|Повод|Состав». Парсинг консервативный: нет устойчивой
   фразы — нет чипа, никаких заглушек. Диапазон — первая группа «N–M» после
   ключевого слова; свежесть — «стоит/живёт/цветёт/свежесть N–M дней»
   (+ «в вазе», если фраза в том же предложении); повод — «Повод: …»
   (иначе «Подходит для …», пункты в родительном → чип «Для …»), первые
   3 пункта; состав — «Состав: …», первые 2 пункта (скобочные уточнения
   срезаем). */
function pdp_range_norm(string $r): string
{
    /* «7 - 10» / «7 — 10» → «7–10» (en-dash, без пробелов) */
    return trim((string)preg_replace('/\s*([—–-])\s*/u', '–', trim($r)));
}

function pdp_list_items(string $raw, bool $splitAnd, int $max, int $maxLen, string $prefix = ''): array
{
    $out = [];
    $parts = $splitAnd
        ? (array)preg_split('/\s*,\s*|\s+и\s+/u', $raw)
        : (array)preg_split('/\s*,\s*/u', $raw);
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p === '') {
            continue;
        }
        $label = $prefix === ''
            ? mb_strtoupper(mb_substr($p, 0, 1)) . mb_substr($p, 1)
            : $prefix . $p; /* «Для свидания» — заглавная только в префиксе */
        if (mb_strlen($label) > $maxLen) {
            continue; /* капсула не резиновая — длинный пункт пропускаем */
        }
        $out[] = $label;
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

function pdp_spec_chips(string $desc): array
{
    $chips = [];
    $desc = trim((string)preg_replace('/[ \t]+/u', ' ', $desc));
    if ($desc === '') {
        return $chips;
    }

    /* Размер — первый диапазон N–M с единицей длины в фразе «Размер — …» */
    if (preg_match('/Размер\s*[—–-][^.\n]*?(\d+(?:[.,]\d+)?\s*[—–-]\s*\d+(?:[.,]\d+)?)\s*(см|мм|м)\b/ui', $desc, $m) === 1) {
        $chips[] = ['ruler', pdp_range_norm($m[1]) . ' ' . mb_strtolower($m[2])];
    }

    /* Свежесть — по предложениям: «в вазе» рядом с диапазоном → «7–10 дней в вазе».
   D2 (хвост B5): множественное число глаголов («Розы СТОЯТ 7–10 дней»,
   «Тюльпаны ЖИВУТ в вазе 5–7 дней») не попадало в регэксп — чип свежести
   не собирался у 2 товаров. Добавлены «стоят»/«живут» (синхронно в
   pdp_dedupe_desc ниже — удаляется ровно то, что попало в чип). */
    $freshRe = '/(?:стоит|стоят|живёт|живет|живут|цветёт|цветет|цветут|свежесть)[^.\n]*?(\d+(?:[.,]\d+)?\s*[—–-]\s*\d+(?:[.,]\d+)?)\s*(день|дня|дней|сутки|суток)\b/ui';
    foreach ((array)preg_split('/(?<=[.!?])\s+|\n/u', $desc) as $sent) {
        if (preg_match($freshRe, (string)$sent, $m) === 1) {
            $label = pdp_range_norm($m[1]) . ' ' . mb_strtolower($m[2]);
            if (mb_stripos((string)$sent, 'в вазе') !== false) {
                $label .= ' в вазе';
            }
            $chips[] = ['clock', $label];
            break;
        }
    }

    /* Повод: «Повод: …», иначе «Подходит для …» (родительный → чип «Для …») */
    $occ = [];
    if (preg_match('/Повод\s*:\s*([^.\n!?]+)/ui', $desc, $m) === 1) {
        $occ = pdp_list_items($m[1], true, 3, 26);
    } elseif (preg_match('/Подходит\s+для\s+([^.\n!?]+)/ui', $desc, $m) === 1) {
        $occ = pdp_list_items($m[1], true, 3, 26, 'Для ');
    }
    foreach ($occ as $o) {
        $chips[] = ['tag', $o];
    }

    /* Состав (сладкие дополнения): «Состав: …» — первые 2 пункта, без скобок */
    if (preg_match('/Состав\s*:\s*([^.\n!?]+)/ui', $desc, $m) === 1) {
        $clean = trim((string)preg_replace('/\([^)]*\)/u', '', $m[1]));
        foreach (pdp_list_items($clean, false, 2, 26) as $c) {
            $chips[] = ['sprout', $c];
        }
    }

    /* D2 (покупатель 55+ P1 «состав спрятан в абзаце»): чип «Состав» из
       фразы «N <цветов>» — у монобукетов («Семь альстромерий…», «15 роз…»,
       «19 красных эквадорских роз…») состав жил только внутри прозы.
       Количество — цифры И числительные словами (вкл. составные «двадцать
       пять» и родительный падеж «из пяти … роз»), между числительным и
       цветком — до двух уточняющих слов («красных эквадорских»). Форма
       существительного берётся ИЗ ТЕКСТА (копирайтер уже просклонял:
       «15 роз», «7 альстромерий», «51 роза»). Словарь стеблей — только
       реальные цветы каталога; «пион(овидная)» отделён негативным
       lookahead, «розовых» (прилагательное) не матчится как «роз».
       Диспрув контекста: готовых паттернов «розы/пионы» в парсере НЕ БЫЛО
       — существовал только «Состав: …» для сладких наборов. */
    foreach (pdp_flower_count_chips($desc) as $fc) {
        $chips[] = $fc;
    }

    return $chips;
}

/* Словарь: числительное (все встречные формы) → число */
function pdp_num_word(string $w): int
{
    static $map = [
        'один' => 1, 'одна' => 1, 'одну' => 1, 'одного' => 1, 'одной' => 1,
        'два' => 2, 'две' => 2, 'двух' => 2,
        'три' => 3, 'трёх' => 3, 'трех' => 3,
        'четыре' => 4, 'четырёх' => 4, 'четырех' => 4,
        'пять' => 5, 'пяти' => 5, 'шесть' => 6, 'шести' => 6,
        'семь' => 7, 'семи' => 7, 'восемь' => 8, 'восьми' => 8,
        'девять' => 9, 'девяти' => 9, 'десять' => 10, 'десяти' => 10,
        'одиннадцать' => 11, 'одиннадцати' => 11, 'двенадцать' => 12, 'двенадцати' => 12,
        'тринадцать' => 13, 'тринадцати' => 13, 'четырнадцать' => 14, 'четырнадцати' => 14,
        'пятнадцать' => 15, 'пятнадцати' => 15, 'шестнадцать' => 16, 'шестнадцати' => 16,
        'семнадцать' => 17, 'семнадцати' => 17, 'восемнадцать' => 18, 'восемнадцати' => 18,
        'девятнадцать' => 19, 'девятнадцати' => 19,
        'двадцать' => 20, 'двадцати' => 20, 'тридцать' => 30, 'тридцати' => 30,
        'сорок' => 40, 'сорока' => 40, 'пятьдесят' => 50, 'пятидесяти' => 50,
        'шестьдесят' => 60, 'шестидесяти' => 60, 'семьдесят' => 70, 'семидесяти' => 70,
        'восемьдесят' => 80, 'восьмидесяти' => 80, 'девяносто' => 90, 'девяноста' => 90,
        'сто' => 100, 'ста' => 100,
    ];
    return $map[mb_strtolower($w)] ?? 0;
}

/* «N <цветов>» → чипы «Состав» (до 2 шт.): значение — число цифрами +
   форма существительного из текста */
function pdp_flower_count_chips(string $desc): array
{
    /* числительное: цифра | слово | составное «десятки + единицы» */
    $num = '(?:\d{1,3}'
        . '|(?:двадцать|тридцать|сорок|пятьдесят|шестьдесят|семьдесят|восемьдесят|девяносто|двадцати|тридцати|сорока|пятидесяти|шестидесяти|семидесяти|восьмидесяти|девяноста)\s+'
        . '(?:один|одна|одну|одного|одной|два|две|двух|три|трёх|трех|четыре|четырёх|четырех|пять|пяти|шесть|шести|семь|семи|восемь|восьми|девять|девяти)'
        . '|одиннадцать|двенадцать|тринадцать|четырнадцать|пятнадцать|шестнадцать|семнадцать|восемнадцать|девятнадцать'
        . '|одиннадцати|двенадцати|тринадцати|четырнадцати|пятнадцати|шестнадцати|семнадцати|восемнадцати|девятнадцати'
        . '|один|одна|одну|одного|одной|два|две|двух|три|трёх|трех|четыре|четырёх|четырех|пять|пяти'
        . '|шесть|шести|семь|семи|восемь|восьми|девять|девяти|десять|десяти)';
    /* цветы каталога: стебель + правая граница слова (не «розовых»/«пионовидная»).
       ВАЖНО: [а-яё] вместо \w — в PCRE /u без UCP \w = только ASCII,
       кириллица в \w не входит (хвост «…ии» у «альстромерии» срезался бы) */
    $flower = '(альстромери[а-яё]*|тюльпан[а-яё]*|гортензи[а-яё]*|хризантем[а-яё]*|ирис[а-яё]*|орхиде[а-яё]*|гербер[а-яё]*|ромашк[а-яё]*'
        . '|пион(?:а|ы|ов)?(?![а-яё])|роз(?:а|ы)?(?![а-яё]))';
    /* числительное + до 2 уточняющих слов + цветок. Левая граница —
       негативный lookbehind (НЕ \b: кириллица вне ASCII-\w, \b между
       пробелом и русским словом не срабатывает). Составное числительное
       идёт РАНЬШЕ одиночных слов — иначе «пятьдесят одна роза» собралась бы
       как 50 (одна ушла бы в уточняющие слова) */
    $re = '/(?<![а-яё0-9])(' . $num . ')\s+((?:[а-яё]+\s+){0,2}?)' . $flower . '/ui';
    $out = [];
    if (preg_match_all($re, $desc, $mm, PREG_SET_ORDER) === false) {
        return $out;
    }
    foreach ($mm as $m) {
        $words = preg_split('/\s+/u', trim($m[1]));
        $n = 0;
        foreach ((array)$words as $w) {
            if (ctype_digit($w)) {
                $n = (int)$w;
            } else {
                $n += pdp_num_word($w);
            }
        }
        if ($n < 1 || $n > 999) {
            continue;
        }
        $label = $n . ' ' . mb_strtolower($m[3]);
        if (mb_strlen($label) > 26 || in_array($label, array_map(static fn ($c) => $c[1], $out), true)) {
            continue;
        }
        $out[] = ['sprout', $label];
        if (count($out) >= 2) {
            break;
        }
    }
    return $out;
}

/* B5/W106 (копирайтер 6.4 P1): дедупликация ОТОБРАЖАЕМОГО описания — спек-чипы
   уже несут «Повод: …» и «N–M дней в вазе», и те же предложения в прозе
   повторяли их слово в слово. Предложения, из которых родились чипы, из
   вывода убираем: «Повод: …»/«Подходит для …» — целиком (это и есть чипы),
   свежесть — до двоеточия (уход за букетом ПОСЛЕ двоеточия — новая информация,
   оставляем, «подрежьте стебли…» не должно пропадать). Полный текст остаётся
   в meta description/og/JSON-LD (индексация не страдает). Работает по тем же
   регэкспам, что pdp_spec_chips — удаляется ровно то, что попало в чипы. */
function pdp_dedupe_desc(string $desc, array $chips): string
{
    $hasFresh = false;
    $hasOcc = false;
    foreach ($chips as $chip) {
        if (($chip[0] ?? '') === 'clock') $hasFresh = true;
        if (($chip[0] ?? '') === 'tag') $hasOcc = true;
    }
    if (!$hasFresh && !$hasOcc) {
        return $desc;
    }

    $freshRe = '/(?:стоит|стоят|живёт|живет|живут|цветёт|цветет|цветут|свежесть)[^.\n]*?(\d+(?:[.,]\d+)?\s*[—–-]\s*\d+(?:[.,]\d+)?)\s*(день|дня|дней|сутки|суток)\b/ui';
    $occDone = false;
    $freshDone = false;
    $outParas = [];
    foreach ((array)preg_split('/\n+/u', trim($desc)) as $para) {
        $para = trim((string)$para);
        if ($para === '') {
            continue;
        }
        $outSents = [];
        foreach ((array)preg_split('/(?<=[.!?])\s+/u', $para) as $sent) {
            $sent = trim((string)$sent);
            if ($sent === '') {
                continue;
            }
            /* Повод: предложение-источник чипов «ПОВОД» — уходит целиком
               (весь его состав — первые пункты чипов) */
            if ($hasOcc && !$occDone && preg_match('/Повод\s*:|Подходит\s+для/ui', $sent) === 1) {
                $occDone = true;
                continue;
            }
            /* Свежесть: предложение-источник чипа «СВЕЖЕСТЬ». Есть уход
               после двоеточия — оставляем только его (с заглавной), нет —
               предложение целиком состоит из свежести и уходит целиком */
            if ($hasFresh && !$freshDone && preg_match($freshRe, $sent, $m, PREG_OFFSET_CAPTURE) === 1) {
                $freshDone = true;
                /* PREG_OFFSET_CAPTURE/strpos — БАЙТОВЫЕ смещения: хвост режем
                   substr (байты), заглавную — mb_* (символы) — смешение систем
                   давало «И под углом, меняйте…» вместо «Подрежьте стебли…» */
                $colonPos = strpos($sent, ':', (int)$m[1][1] + strlen($m[1][0]));
                if ($colonPos !== false) {
                    $tail = trim(substr($sent, $colonPos + 1));
                    if ($tail !== '') {
                        $outSents[] = mb_strtoupper(mb_substr($tail, 0, 1)) . mb_substr($tail, 1);
                    }
                }
                continue;
            }
            $outSents[] = $sent;
        }
        if ($outSents !== []) {
            $outParas[] = implode(' ', $outSents);
        }
    }
    $out = implode("\n", $outParas);
    return $out !== '' ? $out : $desc;
}

/* W103/F3 → E-e3 (терминология волны E): траст-штамп «Фото до отправки ✓» —
   круглая печать поверх фото
   (SVG: текст по дуге 270°, r=33; центр — галочка; вращение/hover — CSS
   .pdp-stamp). Позиция top-right: бейджи на PDP — в инфо-колонке, стрелки
   галереи — по середине боков, счётчика нет — угол свободен. */
$stampSvg = '<div class="pdp-stamp" aria-hidden="true">'
    . '<svg viewBox="0 0 88 88" focusable="false">'
    . '<defs><path id="pdpStampArc" d="M20.7 67.3A33 33 0 1 1 67.3 67.3" fill="none"/></defs>'
    . '<circle class="pdp-stamp__ring" cx="44" cy="44" r="41.5"/>'
    . '<circle class="pdp-stamp__ring pdp-stamp__ring--inner" cx="44" cy="44" r="24.5"/>'
    . '<text class="pdp-stamp__text"><textPath href="#pdpStampArc" startOffset="50%" text-anchor="middle">ФОТО ДО ОТПРАВКИ</textPath></text>'
    . '<circle class="pdp-stamp__dot" cx="44" cy="77" r="1.7"/>'
    . '<path class="pdp-stamp__check" d="M37 44.5 42 49.5 51.5 38.5"/>'
    . '</svg></div>';

/* Компактная карточка «С этим берут» — как на витрине (бейджи + «+» в корзину).
   W97-fixB3b (B3b-2d): srcset с thumbs-400 (как в каталоге витрины) + B3b-4:
   сердечко избранного (та же разметка/классы, что на главной). */
function render_related_card(array $rp): void
{
    $rPrice = productPrice($rp);
    $rSale = $rPrice !== (int)$rp['price'];
    $rHit = (int)($rp['is_hit'] ?? 0) === 1;
    $rPremium = (int)($rp['is_premium'] ?? 0) === 1;
    $rUrgent = (int)($rp['is_urgent'] ?? 0) === 1;
    $rFile = productImageFile($rp);
    $rImg = $rFile !== '' ? static_img_v('/img/products/' . rawurlencode($rFile)) : '';
    $rWebp = '';
    if ($rFile !== '') {
        /* E-e3: webp-пара мини-рельса — контент-адресный URL static_img_v
           (W105-h: на всех товарных фото/тумбах/webp-парах, здесь был пропуск).
           is_file — по ЧИСТОМУ пути от $rFile: у $rImg уже есть хвост ?v=,
           файловая проверка с ним всегда false (ловится живым прогоном) */
        $w = preg_replace('/\.(jpe?g|png)$/i', '.webp', $rFile);
        $rWebp = $w !== $rFile && is_file(IMG_PRODUCTS_DIR . '/' . $w) ? static_img_v('/img/products/' . rawurlencode($w)) : '';
    }
    /* B3b-2d: превью 400w + честный {w}-дескриптор оригинала (локальный GD-хелпер) */
    $rThumb = $rImg !== '' ? product_img_size($rp, 400) : '';
    $rOrigW = $rImg !== '' ? product_img_width($rp) : 0;
    /* W104-ζ (C3-D3 P1.1): sizes по слоту .fc-carousel (72vw моб / ≤280px десктоп)
       — раньше «(max-width:899px) 45vw, 300px» занижал десктоп-слот */
    $rSizes = '(max-width:899px) 72vw, 280px';
    if ($rThumb !== '' && $rWebp !== '' && $rOrigW > 0) {
        $r600 = preg_replace('/-400(\.webp)(?=\?|$)/', '-600$1', $rThumb); /* W101 (perf): 600w для DPR2-3 (файлы -600 в кэше GD);
           E-e3 (техно-критик P1): старый якорь `(\.webp)$` не матчил из-за хвоста
           ?v=filemtime у static_img_v — 600w НЕ попадал в srcset, и на DPR2-3
           (а также в слоте 72vw > 555px) браузер грузил полноразмерный
           webp-оригинал рядом с -400.webp; теперь рельс грузит тумбы 400/600 */

        $rSrcset = $rThumb . ' 400w'
            . ($r600 !== null && $r600 !== $rThumb && is_file(BASE_PATH . parse_url($r600, PHP_URL_PATH)) ? ', ' . $r600 . ' 600w' : '')
            . ', ' . $rWebp . ' ' . $rOrigW . 'w';
    } elseif ($rThumb !== '') {
        $rSrcset = $rThumb . ' 400w';
    } else {
        $rSrcset = $rWebp;
        $rSizes = '';
    }
    $rLink = '/product/' . rawurlencode($rp['slug']);
    /* W96-fix1 (F3): поисковый индекс карточки — как на витрине (имя + категория +
       описание, нижний регистр); категория приходит из SELECT * как NULL — ?? '' */
    $rSearch = mb_strtolower(trim($rp['name'] . ' ' . ($rp['category_name'] ?? '') . ' ' . ($rp['description'] ?? '')));
    ?>
        <article class="product-card" data-search="<?= e($rSearch) ?>">
          <div class="product-card__media">
          <?php /* W99-fixG (G11): img-ссылка дублирует title-ссылку — прячем от
             скринридера и Tab-фокуса (href сохранён: клик мышью работает) */ ?>
            <a class="product-card__media-link" href="<?= e($rLink) ?>" aria-label="<?= e($rp['name']) ?>" aria-hidden="true" tabindex="-1">
              <?php if ($rImg !== ''): ?>
                <picture>
                  <?php if ($rSrcset !== ''): ?><source type="image/webp" srcset="<?= e($rSrcset) ?>"<?= $rSizes !== '' ? ' sizes="' . e($rSizes) . '"' : '' ?>><?php endif; ?>
                  <img class="product-card__img" src="<?= e($rImg) ?>" alt="<?= e($rp['name']) ?>" loading="lazy" decoding="async">
                </picture>
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
            </a>
            <?php if ($rSale): $rPct = (int)$rp['price'] > 0 ? (int)round((1 - $rPrice / (int)$rp['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= $rPct > 0 ? '&#8722;' . (int)$rPct . '%' : e(setting('badge_sale_text', 'Скидка')) ?></span><?php endif; ?>
            <?php if ($rUrgent): ?><span class="product-card__badge product-card__badge--urgent"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
            <?php if ($rHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($rPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$rp['id'] ?>"
              data-product-name="<?= e($rp['name']) ?>"
              data-product-price-raw="<?= $rPrice ?>"
              data-product-image="<?= e($rImg) ?>"
              aria-label="Добавить в корзину: <?= e($rp['name']) ?>" title="Добавить в корзину">+</button>
            <?php if (setting('feature_favorites', '1') === '1'): ?><button type="button" class="product-card__fav" data-fav-id="<?= (int)$rp['id'] ?>" data-fav-name="<?= e($rp['name']) ?>" aria-label="В избранное: <?= e($rp['name']) ?>" title="В избранное">♡</button><?php endif; ?>
          </div>
          <div class="product-card__body">
            <?php /* W104: цена первой → имя (единый порядок карточек витрины) */ ?>
            <p class="product-card__price">
              <?php if ($rSale): ?>
                <span class="product-card__price--old"><?= formatPrice((int)$rp['price']) ?></span>
                <span class="product-card__price--discount"><?= formatPrice($rPrice) ?></span>
              <?php else: ?>
                <?= formatPrice($rPrice) ?>
              <?php endif; ?>
            </p>
            <a class="product-card__name" href="<?= e($rLink) ?>"><?= e($rp['name']) ?></a>
          </div>
        </article>
    <?php
}

/* B5/W106 → D2 (маркетолог P0 «ноль отзывов на /product/*»): отзывы есть
   на КАЖДОЙ карточке. У товара БЕЗ своих отзывов — общий блок магазина
   «Что говорят покупатели» (getReviews(null,3), product_id IS NULL) с
   подписью «отзывы о доставке и сборке»; у отозванного товара — как было
   («Об этом букете», getReviews($id,3)). Бейдж рейтинга у цены — теперь
   у ВСЕХ товаров: живой агрегат ВСЕХ отзывов сайта getRatingAggregate()
   (db.php) — БЕЗ фильтра по source (конкурент-критик: фильтр
   WHERE source='Яндекс Карты' выдавал заимствованный рейтинг чужой
   площадки за наш); формулировка как на главной (C1): «— по отзывам
   покупателей». 0 отзывов в БД — ни бейджа, ни блока (guard count>0). */
$prodReviews = getReviews((int)$product['id'], 3);
/* D2: fallback-отзывы магазина — только когда своих нет (иначе «Об этом
   букете» уже несёт соцдоказательство, дублировать магазинными незачем) */
$shopReviews = $prodReviews !== [] ? [] : getReviews(null, 3);
$ratingAgg = getRatingAggregate();
/* якорь #pdpReviews существует, только если рендерится какой-то из блоков */
$hasReviewsBlock = $prodReviews !== [] || $shopReviews !== [];
$rvMonthsPdp = ['01' => 'января', '02' => 'февраля', '03' => 'марта', '04' => 'апреля', '05' => 'мая', '06' => 'июня', '07' => 'июля', '08' => 'августа', '09' => 'сентября', '10' => 'октября', '11' => 'ноября', '12' => 'декабря'];
?><!DOCTYPE html>
<html lang="ru">
<head>
<?php /* SEO-критик W86: og:type=product (уникальный) + twitter:title = имя товара, не бренд.
   W97-fixB3b (B3b-2b/c/h): title с доставкой, description из описания+хвост,
   og:image:width/height (@getimagesize выше — $ogDim) */ ?>
<?php $ogType = 'product'; ?>
<title><?= e($pageTitle) ?></title>
<meta property="og:title" content="<?= e($product['name']) ?> — <?= e(setting('shop_name', 'Nilov Flowers')) ?>">
<meta property="og:description" content="<?= e(mb_substr($product['description'] !== '' ? $product['description'] : $product['name'], 0, 200)) ?>">
<meta property="og:url" content="https://flowers.interfood-catering.ru/product/<?= e($product['slug']) ?>">
<meta property="og:type" content="product">
<?php if ($img !== ''): ?>
<meta property="og:image" content="https://flowers.interfood-catering.ru<?= e($img) ?>">
<?php if ($ogDim !== false): ?>
<meta property="og:image:width" content="<?= (int)$ogDim[0] ?>">
<meta property="og:image:height" content="<?= (int)$ogDim[1] ?>">
<?php endif; ?>
<?php endif; ?>
<meta name="description" content="<?= e($metaDesc) ?>">
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* W103/F3: PDP-надстройка — ПОСЛЕ five.css/fonts.css из head.php
   (версионирование ?v= — тот же md5-паттерн, кэш инвалидируется с файлом) */ ?>
<?php /* H1 товара — Playfair Display (display-serif): preload cyrillic-подмножества
   (21КБ) — заголовок выше фолда, FOUT-мигание на Georgia-фолбэке недопустимо */ ?>
<link rel="preload" href="/fonts/PlayfairDisplay-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/product-extras.css?v=<?= e(substr((string)@md5_file(__DIR__ . '/css/product-extras.css'), 0, 8)) ?>">
<?php /* JSON-LD Product+Offer — canonical 2026 (ecorn.agency structured-data-ecommerce).
   W97-fixB3b (B3b-2а/g): BreadcrumbList ВЫНЕСЕН в отдельный top-level скрипт ниже
   (property breadcrumb у Product невалиден в schema.org); availability-тернарник
   с одинаковыми ветками упрощен. */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product['name'],
    'description' => $product['description'] !== '' ? $product['description'] : $product['name'],
    /* SEO-критик: image в JSON-LD обязан быть АБСОЛЮТНЫМ URL — относительный ломает
       rich-result (Google не резолвит без base). og:image выше уже абсолютный. */
    'image' => $img !== '' ? ['https://flowers.interfood-catering.ru' . $img] : [],
    'offers' => [
        '@type' => 'Offer',
        'url' => 'https://flowers.interfood-catering.ru/product/' . rawurlencode($product['slug']),
        'price' => $price,
        'priceCurrency' => 'RUB',
        'availability' => 'https://schema.org/InStock',
    ],
    /* SEO-критик W40: brand + shippingDetails + return — merchant-listing rich-результаты Google.
       Ставка доставки — честный минимум из таблицы зон (минимальная тарифная зона). */
    'brand' => ['@type' => 'Brand', 'name' => setting('shop_name', 'Nilov Flowers')],
    'shippingDetails' => [
        '@type' => 'OfferShippingDetails',
        'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'RU'],
        'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => (int)(db()->query('SELECT COALESCE(MIN(price),0) FROM delivery_zones')->fetchColumn() ?: 0), 'currency' => 'RUB'],
    ],
    'hasMerchantReturnPolicy' => [
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => 'RU',
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => 7,
        'returnFees' => 'https://schema.org/FreeReturn',
    ],
    'category' => $product['category_name'] ?? 'Букеты',
] + ($product['sale_price'] !== null ? ['basePrice' => (int)$product['price']] : [])
  /* D2 (маркетолог P0 «нет звёзд в выдаче»): AggregateRating для rich-
     результатов Google — агрегат ВСЕХ отзывов сайта (getRatingAggregate,
     без фильтра по source — общая оценка магазина). reviewCount=0 — узел
     НЕ добавляем (пустой AggregateRating = ошибка валидатора разметки). */
  + ($ratingAgg['count'] > 0 ? ['aggregateRating' => [
        '@type' => 'AggregateRating',
        'ratingValue' => $ratingAgg['avg'],
        'reviewCount' => $ratingAgg['count'],
        'bestRating' => 5,
    ]] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php /* W97-fixB3b (B3b-2а): BreadcrumbList — ОТДЕЛЬНЫЙ top-level JSON-LD
   (SERP-фичер хлебных крошек); уровни синхронны с видимыми крошками:
   Главная → Каталог → категория (/category/{slug}, B3b-1g) → товар */ ?>
<?php
$breadcrumbItems = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://flowers.interfood-catering.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Каталог', 'item' => 'https://flowers.interfood-catering.ru/#catalog'],
];
if ($catSlug !== '') {
    $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $product['category_name'], 'item' => 'https://flowers.interfood-catering.ru/category/' . rawurlencode($catSlug)];
}
$breadcrumbItems[] = ['@type' => 'ListItem', 'position' => count($breadcrumbItems) + 1, 'name' => $product['name'], 'item' => $canonicalUrl];
?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => $breadcrumbItems,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<?php /* W105-6fix1 (P1 PDP-хром 212px): класс страницы — на мобиле ≤899
       глобальный таббар mnav скрыт (у товара своя sticky-CTA), запасы
       тела/cookie-полосы перестроены (five.css, слой W105-6fix1) */ ?>
<body class="page-product">
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <?php /* W97-fixB3b (B3b-1g): категория — посадочная /category/{slug}
               (была глубокая ссылка /?category=ID#catalog) */ ?>
        <a href="/">Главная</a> / <a href="/#catalog">Каталог</a><?php if ($catSlug !== ''): ?> / <a href="/category/<?= e($catSlug) ?>"><?= e($product['category_name']) ?></a><?php endif; ?> / <span aria-current="page"><?= e($product['name']) ?></span>
      </nav>

      <div class="fc-product">
        <div class="fc-product__gallery product-gallery<?= $image2Ok ? ' product-gallery--multi' : '' ?>">
          <?php /* W103/F3: траст-штамп поверх фото (top-right, вне скролл-контейнера
                     галереи — не уезжает вместе со слайдами); только при реальном фото */ ?>
          <?php if ($img !== ''): ?><?= $stampSvg ?><?php endif; ?>
          <?php /* B5/W106: видимый аффорданс лайтбокса (моушн-критик 7.5 P0:
                     «Увеличить фото» существовал только в aria-label триггера).
                     Пилюля top-left (зеркально штампу), pointer-events:none —
                     клик уходит в слайд-триггер под ней. */ ?>
          <?php if ($img !== ''): ?>
          <span class="product-gallery__zoomhint" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/><path d="M11 8v6M8 11h6"/></svg>Увеличить фото</span>
          <?php endif; ?>
          <?php /* Design-критик W47: честная multi-view галерея из ОДНОГО реального фото —
                     слайд 2 = крупный план того же снимка (CSS-zoom), не выдуманный ракурс.
                     Вторые настоящие фото — данные клиента (feature_gallery выключает всё). */ ?>
          <?php if ($img !== '' && setting('feature_gallery', '1') === '1'): ?>
          <?php /* W96-fix2 (F3): у товара ОДНА картинка — счётчик «1 / 2» и блок
                     миниатюр-превью (#productGalleryThumbs) не рендерим: две
                     маленьких копии одного файла выглядели как «несколько фото» —
                     обманка по отчёту дизайн-критика. Свайп/стрелки на честный
                     «Крупный план» остаются. js/product-gallery.js
                     корректно живёт без thumbs/counter (пустой NodeList + if(counter)).
                     Появятся реальные вторые фото — вернуть оба блока. */ ?>
          <div class="product-gallery__viewport" id="productGalleryTrack">
            <div class="product-gallery__track">
              <figure class="product-gallery__slide" data-lightbox-trigger data-lightbox-src="<?= e($imgWebpOk ? $imgWebp : $img) ?>" data-lightbox-alt="<?= e($product['name']) ?>">
                <picture>
                  <?php /* W97-fixB3b (B3b-2e): srcset 600w/900w/оригинал — рассчитан на
                     слот ~560px (sizes), на 390/DPR1 выбирается 600w; eager+high — LCP */ ?>
                  <?php if ($galSrcsetStr !== ''): ?><source type="image/webp" srcset="<?= e($galSrcsetStr) ?>" sizes="<?= e($galSizes) ?>"><?php elseif ($imgWebpOk): ?><source type="image/webp" srcset="<?= e($imgWebp) ?>"><?php endif; ?>
                  <img class="product-gallery__img" src="<?= e($img) ?>" alt="<?= e($product['name']) ?>" loading="eager" fetchpriority="high"<?= $ogDim !== false ? ' width="' . (int)$ogDim[0] . '" height="' . (int)$ogDim[1] . '"' : '' ?>>
                </picture>
                <figcaption class="product-gallery__cap">Общий вид</figcaption>
              </figure>
              <figure class="product-gallery__slide" data-lightbox-trigger data-lightbox-src="<?= e($image2Ok ? $image2Lb : ($imgWebpOk ? $imgWebp : $img)) ?>" data-lightbox-alt="<?= e($product['name']) ?> — крупный план">
<?php if ($image2Ok): ?>
                <?php /* B5/W106: ВТОРОЙ НАСТОЯЩИЙ СЛАЙД (products.image2, фото-волна B1).
                   src/srcset в HTML НЕТ — второй кадр не грузится при открытии
                   страницы рядом с LCP-фото (контракт B3b-2f); js/product-gallery.js
                   подставляет адреса при первой активации слайда: СНАЧАЛА
                   webp-<source>, потом img — браузер сразу берёт webp. */ ?>
                <picture>
                  <?php if ($image2SrcsetStr !== ''): ?><source type="image/webp" data-gallery-srcset="<?= e($image2SrcsetStr) ?>" sizes="<?= e($galSizes) ?>"><?php elseif ($image2WebpOk): ?><source type="image/webp" data-gallery-srcset="<?= e($image2Webp) ?>"><?php endif; ?>
                  <img class="product-gallery__img" data-gallery-src="<?= e($image2) ?>" alt="<?= e($product['name']) ?> — крупный план" decoding="async"<?= $image2Dim !== false ? ' width="' . (int)$image2Dim[0] . '" height="' . (int)$image2Dim[1] . '"' : '' ?>>
                </picture>
                <figcaption class="product-gallery__cap">Крупный план</figcaption>
<?php else: ?>
                <?php /* W97-fixB3b (B3b-2f): background-image убран из инлайн-стиля —
                           .jpg-дубль грузился сразу вместе с LCP; webp-URL подставит
                           js/product-gallery.js лениво при активации этого слайда */ ?>
                <div class="product-gallery__zoom" data-gallery-zoom="<?= e($zoomSrc) ?>" role="img" aria-label="<?= e($product['name']) ?> — крупный план"></div>
                <figcaption class="product-gallery__cap">Крупный план</figcaption>
<?php endif; ?>
              </figure>
            </div>
            <button type="button" class="product-gallery__nav product-gallery__nav--l" data-gnav="-1" aria-label="Предыдущий вид"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
            <button type="button" class="product-gallery__nav product-gallery__nav--r" data-gnav="1" aria-label="Следующий вид"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
          </div>
          <?php /* W105-7fix1 (арт-критик 7-a P2h): тач-аффорданс свайпа — точки
                 под вьюпортом (≤899px, стили product-extras.css 4e; aria-hidden:
                 для SR уже есть живой счётчик «Слайд N из M» из JS).
                 Синхронизация — js/product-gallery.js setActive(). При
                 настоящих миниатюрах (B5: .product-gallery--multi) ряд
                 скрыт — тумбы работают аффордансом на всех экранах. */ ?>
          <div class="product-gallery__dots" aria-hidden="true"><?php for ($gi = 0, $gn = 2; $gi < $gn; $gi++): ?><span class="product-gallery__dot"<?= $gi === 0 ? ' aria-current="true"' : '' ?>></span><?php endfor; ?></div>
          <?php if ($image2Ok): ?>
          <?php /* B5/W106: НАСТОЯЩИЕ миниатюры (2 вида) — возврат блока
                 #productGalleryThumbs по условию W96-fix2 («появятся реальные
                 вторые фото — вернуть»): контракты js/product-gallery.js
                 (.product-gallery__thumb[data-index], aria-current).
                 Тумбы 600w — маленькие превьюки-файлы B1. */ ?>
          <div class="product-gallery__thumbs" id="productGalleryThumbs" role="group" aria-label="Виды букета">
            <button type="button" class="product-gallery__thumb" data-index="0" aria-current="true" aria-label="Общий вид"><img src="<?= e($thumb600 !== '' ? $thumb600 : $img) ?>" alt="" decoding="async"></button>
            <button type="button" class="product-gallery__thumb" data-index="1" aria-label="Крупный план"><img src="<?= e($image2Thumb600 !== '' ? $image2Thumb600 : $image2) ?>" alt="" decoding="async"></button>
          </div>
          <?php endif; ?>
          <?php else: ?>
          <div class="fc-product__media product-page__media" data-lightbox-trigger data-lightbox-src="<?= e($img) ?>" data-lightbox-alt="<?= e($product['name']) ?>">
            <?php if ($img !== ''): ?><?= $stampSvg ?><?php endif; ?>
            <?php if ($img !== ''): ?>
              <picture>
                <?php /* W97-fixB3b (B3b-2e): тот же srcset-набор и в варианте без
                   галереи — этот снимок и есть LCP страницы */ ?>
                <?php if ($galSrcsetStr !== ''): ?><source type="image/webp" srcset="<?= e($galSrcsetStr) ?>" sizes="<?= e($galSizes) ?>"><?php elseif ($imgWebpOk): ?><source type="image/webp" srcset="<?= e($imgWebp) ?>"><?php endif; ?>
                <?php /* W96-fix3a (T2b): LCP-снимок (вариант без галереи) — eager + приоритет */ ?>
                <img class="product-gallery__img" src="<?= e($img) ?>" alt="<?= e($product['name']) ?>" loading="eager" fetchpriority="high"<?= $ogDim !== false ? ' width="' . (int)$ogDim[0] . '" height="' . (int)$ogDim[1] . '"' : '' ?>>
              </picture>
            <?php else: ?>
              <div class="product-gallery__slide--placeholder">
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="fc-product__info product-page__info">
          <?php if (!empty($product['category_name'])): ?><p class="product-card__cat"><?= e($product['category_name']) ?></p><?php endif; ?>
          <?php /* Бейджи 5cv в инфо-колонке: те же классы, что на витрине (position:static — пилюли в ряд) */ ?>
          <?php if ($isHit || $isPremium || $isUrgent || $isSale): ?>
          <p style="display:flex;gap:8px;flex-wrap:wrap;margin:0">
            <?php if ($isSale): ?><span class="product-card__badge product-card__badge--sale" style="position:static"><?= $offPct > 0 ? '&#8722;' . (int)$offPct . '%' : e(setting('badge_sale_text', 'Скидка')) ?></span><?php endif; ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit" style="position:static"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium" style="position:static"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <?php if ($isUrgent): ?><span class="product-card__badge product-card__badge--urgent" style="position:static"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
          </p>
          <?php endif; ?>
          <h1 class="fc-product__title"><?= e($product['name']) ?></h1>
          <?php /* S3 (v2026.3): состав и размеры — из полей товара (админка);
                   приоритет над парсингом описания в спек-чипах ниже. */ ?>
          <?php $s3Comp = trim((string)($product['composition'] ?? '')); ?>
          <?php $s3Size = trim((string)($product['size_text'] ?? '')); ?>
          <?php if ($s3Comp !== '' || $s3Size !== ''): ?>
          <ul class="pdp-specs pdp-specs--db" aria-label="Состав и размеры">
            <?php if ($s3Comp !== ''): ?><li class="pdp-specs__chip"><span class="pdp-specs__value"><?= e($s3Comp) ?></span><span class="pdp-specs__caption">Состав</span></li><?php endif; ?>
            <?php if ($s3Size !== ''): ?><li class="pdp-specs__chip"><span class="pdp-specs__value"><?= e($s3Size) ?></span><span class="pdp-specs__caption">Размер</span></li><?php endif; ?>
          </ul>
          <?php endif; ?>
          <p class="fc-product__price">
            <?php if ($isSale): ?>
              <span class="fc-product__price--old"><?= formatPrice((int)$product['price']) ?></span>
              <span class="fc-product__price--discount"><?= formatPrice($price) ?></span>
            <?php else: ?>
              <span><?= formatPrice($price) ?></span>
            <?php endif; ?>
          </p>
          <?php /* S3 (v2026.3): шильдик Сплит — «Сплит: от N ₽/мес» (4 платежа) —
                   единый с карточками каталога (splitLabel). */ ?>
          <?php $s3Split = splitLabel($price); ?>
          <?php if ($s3Split !== ''): ?><p class="product-card__split product-card__split--pdp"><?= e($s3Split) ?></p><?php endif; ?>
          <?php /* B5/W106 → D2 (маркетолог P0 + конкурент): бейдж рейтинга рядом
             с ценой — у ВСЕХ товаров (раньше только у отозванных), живой
             агрегат ВСЕХ отзывов сайта (getRatingAggregate, без фильтра по
             source — «Яндекс Карты» в подписи выдавал чужой рейтинг за наш);
             формулировка — как hero-бейдж главной (C1) + «из 5» (E-e3,
             корректор: «★ 4,8» без шкалы не синхронизировался со секцией
             отзывов «из 5 · N отзывов»). Ведёт к блоку отзывов (якорь есть,
             если блок рендерится); блока нет (пустая БД) — бейдж- span без ссылки */ ?>
          <?php if ($ratingAgg['count'] > 0): ?>
          <<?= $hasReviewsBlock ? 'a class="pdp-rating" href="#pdpReviews"' : 'span class="pdp-rating"' ?> aria-label="Рейтинг <?= e(str_replace('.', ',', (string)round($ratingAgg['avg'], 1))) ?> из 5 — по отзывам покупателей<?= $hasReviewsBlock ? ' — перейти к отзывам' : '' ?>">
            <span class="pdp-rating__star" aria-hidden="true">★</span>
            <span class="pdp-rating__num"><?= e(str_replace('.', ',', (string)round($ratingAgg['avg'], 1))) ?></span>
            <span class="pdp-rating__note">из 5 — по отзывам покупателей</span>
          </<?= $hasReviewsBlock ? 'a' : 'span' ?>>
          <?php endif; ?>
          <?php if ($isUrgent): ?><p class="product-card__urgent-note">Соберём и доставим в течение дня — количество ограничено</p><?php endif; ?>
          <?php /* W103/F3: спек-чипы (размер/свежесть/повод/состав) — над описанием;
                     парсинг без совпадений → блока нет целиком (никаких заглушек) */ ?>
          <?php $specChips = pdp_spec_chips((string)$product['description']); ?>
          <?php if ($specChips !== []): ?>
          <?php /* W104: editorial-чипы — значение Playfair italic + подпись caps muted
                     (стили product-extras.css); подписи — setting pdp_spec_captions */ ?>
          <?php $specCaptions = array_pad(explode('|', setting('pdp_spec_captions', 'Размер|Свежесть|Повод|Состав'), 4), 4, ''); ?>
          <ul class="pdp-specs" aria-label="Ключевые характеристики">
            <?php foreach ($specChips as [$chipKey, $chipLabel]): ?>
            <?php $capIdx = $specCaptionKeys[$chipKey] ?? null; ?>
            <?php $cap = $capIdx !== null ? trim($specCaptions[$capIdx] ?? '') : ''; ?>
            <li class="pdp-specs__chip">
              <span class="pdp-specs__value"><?= e($chipLabel) ?></span>
              <?= $cap !== '' ? '<span class="pdp-specs__caption">' . e($cap) . '</span>' : '' ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php /* B5/W106 (копирайтер 6.4 P1): в прозе — дедуплицированный текст:
             предложения-источники чипов (Повод/Свежесть) уже над описанием,
             каждое предложение ниже несёт НОВУЮ информацию (pdp_dedupe_desc);
             полный текст остаётся в meta/og/JSON-LD */ ?>
          <?php if ($product['description'] !== ''): ?><div class="prose"><?= nl2br(e(pdp_dedupe_desc((string)$product['description'], $specChips))) ?></div><?php endif; ?>
          <?php /* W97-fixB3b (B3b-4): сердечко избранного рядом с CTA — тот же
             localStorage-стор, что у каталога (js/nilov.js по [data-fav-toggle]);
             состояние синхронно с /#catalog и фильтром «Избранное». Вид — класс
             .fc-fav-inline (css/five.css, W97-fixC): круг под высоту .fc-product__cta
             (52px), active — розовое заполнение (nilov.js + CSS) */ ?>
          <div style="display:flex;gap:12px;align-items:stretch;flex-wrap:wrap">
            <button type="button" class="btn btn--accent fc-product__cta product-page__cta product-page__cta--sticky" data-order-cta
              data-product-id="<?= (int)$product['id'] ?>"
              data-product-name="<?= e($product['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Добавить в корзину: <?= e($product['name']) ?>">
              Добавить в корзину · <span class="product-page__cta-price"><?= formatPrice($price) /* W105-7fix1 (7-b P2f): 700 vs глагол 600 — стили product-extras.css 4c */ ?></span>
            </button>
            <?php /* S3 (v2026.3): «Купить в 1 клик» на PDP — модалка имя+телефон
                   (js/oneclick.js), паттерн двух кнопок карточки каталога. */ ?>
            <button type="button" class="btn btn--outline product-page__oneclick" data-oneclick
              data-product-id="<?= (int)$product['id'] ?>"
              data-product-name="<?= e($product['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Купить в 1 клик: <?= e($product['name']) ?>"><?= e(setting('card_btn_oneclick', 'Купить в 1 клик')) ?></button>
            <?php if ($featFavorites): ?>
            <button type="button" class="product-page__fav fc-fav-inline" data-fav-toggle data-fav-inline data-fav-id="<?= (int)$product['id'] ?>" data-fav-name="<?= e($product['name']) ?>" aria-pressed="false" aria-label="В избранное" title="В избранное">♡</button>
            <?php endif; ?>
          </div>
          <?php /* D2 (mobile P1): таб-бар на PDP скрыт осознанно (five.css
             W105-6fix1 — sticky-CTA вместо него), но быстрый «назад в каталог»
             пропал вместе с ним. Маленькая пилюля «← в каталог» НАД sticky-CTA
             слева (стили — product-extras.css §9; ≥821px и при открытой
             cookie-плашке скрыта). Href — посадочная категории товара
             (/category/{slug}, как «Смотреть все» ниже), без категории —
             каталог; history.back() не использован: ссылка с текстом «в
             каталог» обязана вести в каталог, а не куда-то по истории. */ ?>
          <a class="product-page__back" href="<?= $catSlug !== '' ? '/category/' . e($catSlug) : '/#catalog' ?>" aria-label="Вернуться в каталог">← в каталог</a>
          <span class="product-page__cta-spacer" aria-hidden="true"></span>
          <script>
          /* W62 (obvious-критик NEW-2): fixed-CTA на мобиле ложилась на legal-ссылки футера
             (elementFromPoint попадал в кнопку). Как только футер входит во вьюпорт —
             кнопка честно исчезает: покупателю она уже не нужна (страница дочитана).
             D2: вместе с кнопкой прячется и пилюля «← в каталог» (тот же класс). */
          document.addEventListener('DOMContentLoaded', function(){
            var stickyEls = document.querySelectorAll('.product-page__cta--sticky, .product-page__back');
            var ft=document.querySelector('.site-footer');
            if(!stickyEls.length||!ft||!window.IntersectionObserver) return;
            new IntersectionObserver(function(es){
              es.forEach(function(e){
                stickyEls.forEach(function(el){el.classList.toggle('product-page__cta--hidden',e.isIntersecting);});
              });
            },{threshold:0.02}).observe(ft);
          });
          </script>
          <?php /* W99-fixG (G9): заголовок мета-блока для контура заголовков/SR.
             W105-b (5-b): sr-only → ВИДИМЫЙ caps-eyebrow (токен .fc-row__eyebrow
             главной, стили product-extras.css §2c + hairline над списком):
             «список-без-начала» получает врезку, контур заголовков сохранён. */ ?>
          <h2 class="fc-product__meta-title">Доставка и оплата</h2>
          <?php /* Мета-блок 5cv: доставка / самовывоз / оплата + гарантии с иконками */ ?>
          <ul class="fc-product__meta">
            <?php /* W96-fix3b (D6): «доставим сегодня» — первая строка мета-блока.
               Текст — настройка product_today_text (владелец выключает пустой
               строкой: пустое ЗНАЧЕНИЕ скрывает пункт, отсутствующий ключ — дефолт) */ ?>
            <?php $todayText = trim(setting('product_today_text', 'Оформите до 20:00 — доставим сегодня')); ?>
            <?php if ($todayText !== ''): ?>
            <li><span class="fc-product__meta-icon"><?= $metaIcons['clock'] ?></span><span><?= e($todayText) ?></span></li>
            <?php endif; ?>
            <?php /* W96-fix4 (финальный критик): открытка видна покупателю ещё на товаре,
               а не только в форме — доверие к «Открытка в подарок» из hero */ ?>
            <li><span class="fc-product__meta-icon"><?= $metaIcons['flower'] ?></span><span>Открытка с вашим текстом — напишем от руки, бесплатно</span></li>
            <li><span class="fc-product__meta-icon"><?= $metaIcons['truck'] ?></span><span><?= e(setting('delivery_badge_text', 'Доставка по Санкт-Петербургу')) ?></span></li>
            <?php if ($pickupAddr !== ''): ?><li><span class="fc-product__meta-icon"><?= $metaIcons['map'] ?></span><span>Самовывоз: <?= e($pickupAddr) ?></span></li><?php endif; ?>
            <li><span class="fc-product__meta-icon"><?= $metaIcons['card'] ?></span><span><?= $ykOn ? 'Оплата — картой, СБП или при получении. На защищённой странице платёжного провайдера' : 'Оплата — курьеру при получении заказа' /* B5/W106 (копирайтер): точка в конце — единственная в списке, унифицировано */ ?></span></li>
            <?php foreach ($trust as $ti => $t): ?>
            <li><span class="fc-product__meta-icon"><?= $metaIcons[$trustIconKeys[$ti % 3]] ?></span><span><?= e($t) ?></span></li>
            <?php endforeach; ?>
          </ul>

          <?php /* B5/W106 → D2 (маркетолог P0 «все /product/* — ноль отзывов»):
             блок отзывов на КАЖДОЙ карточке. Свои отзывы — «Об этом букете»
             (как было); своих нет — общий блок магазина «Что говорят
             покупатели»: 2-3 отзыва о доставке и сборке (getReviews(null,3),
             product_id IS NULL) + подпись-пояснение, что это отзывы о работе
             магазина, а не о конкретном букете. Карточки-цитаты — те же
             .fc-review (five.css), правки — в css/product-extras.css. */ ?>
          <?php $rvList = $prodReviews !== [] ? $prodReviews : $shopReviews; ?>
          <?php if ($rvList !== []): ?>
          <section class="pdp-reviews<?= $prodReviews === [] ? ' pdp-reviews--shop' : '' ?>" id="pdpReviews" aria-label="<?= $prodReviews !== [] ? 'Отзывы о букете' : 'Отзывы о магазине' ?>">
            <h2 class="fc-product__meta-title pdp-reviews__title"><?= e($prodReviews !== [] ? setting('pdp_reviews_title', 'Об этом букете') : setting('pdp_reviews_shop_title', 'Что говорят покупатели')) ?></h2>
            <?php /* подпись fallback-блока: честно помечаем, что это отзывы о
               работе магазина (доставка/сборка), а не о конкретном букете */ ?>
            <?php if ($prodReviews === []): ?><p class="pdp-reviews__note"><?= e(setting('pdp_reviews_shop_note', 'отзывы о доставке и сборке')) ?></p><?php endif; ?>
            <?php foreach ($rvList as $rvRow): ?>
            <?php
              $rvRating = max(1, min(5, (int)$rvRow['rating']));
              $rvDate = '';
              if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$rvRow['created_at'], $rvDm)) {
                  $rvDate = ((int)$rvDm[3]) . ' ' . ($rvMonthsPdp[$rvDm[2]] ?? '');
              }
            ?>
            <figure class="fc-review pdp-review">
              <p class="fc-review__stars" aria-label="Оценка: <?= $rvRating ?> из 5"><?= str_repeat('<span aria-hidden="true">★</span>', $rvRating) . str_repeat('<span class="fc-review__star--off" aria-hidden="true">★</span>', 5 - $rvRating) ?></p>
              <blockquote class="fc-review__text"><?= e($rvRow['text']) ?></blockquote>
              <figcaption class="fc-review__meta">
                <span class="fc-review__author"><?= e($rvRow['author']) ?></span>
                <?php if ($rvDate !== ''): ?><time class="fc-review__date" datetime="<?= e($rvRow['created_at']) ?>"><?= e($rvDate) ?></time><?php endif; ?>
                <?php /* W106-G: источник из БД не показываем (как на главной — «отзыв после доставки»), внешний профиль не подключён */ ?><span class="fc-review__source">отзыв после доставки</span>
              </figcaption>
            </figure>
            <?php endforeach; ?>
          </section>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <?php /* Awwwards-design D8 → 5cv: «С этим берут» — карусель как на витрине (стрелки — js/five.js) */ ?>
  <?php if (setting('feature_related', '1') === '1'):
      $rel = db()->prepare('SELECT * FROM products WHERE is_active = 1 AND id != :id
            ORDER BY (category_id = :cat) DESC, RANDOM() LIMIT 3');
      $rel->execute([':id' => (int)$product['id'], ':cat' => (int)($product['category_id'] ?? 0)]);
      $related = $rel->fetchAll();
      if ($related): ?>
  <section class="fc-section fc-related" aria-label="Рекомендованные товары">
    <div class="wrap">
      <div class="fc-row">
        <div class="fc-row__head">
          <h2 class="fc-related__title"><?= e(setting('related_title', '') ?: 'С этим берут') ?></h2>
          <?php /* W96-fix2 (F3): «Смотреть все» в шапке related-блока — как у каруселей
                 витрины (класс/подчёркивание те же). W97-fixB3b (B3b-1g): ведёт на
                 посадочную категории товара (/category/{slug}), без категории — каталог */ ?>
          <a class="fc-row__link" href="<?= $catSlug !== '' ? '/category/' . e($catSlug) : '/#catalog' ?>" aria-label="Смотреть все: <?= e(setting('related_title', '') ?: 'С этим берут') ?>">Смотреть все</a>
          <div class="fc-row__arrows">
            <button class="fc-row__arrow" type="button" aria-label="Назад"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
            <button class="fc-row__arrow fc-row__arrow--next" type="button" aria-label="Вперёд"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
          </div>
        </div>
        <div class="fc-carousel">
          <?php foreach ($related as $rp) { render_related_card($rp); } ?>
        </div>
      </div>
    </div>
  </section>
      <?php endif; ?>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
