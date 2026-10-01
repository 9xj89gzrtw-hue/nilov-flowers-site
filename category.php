<?php
/* Посадочные страницы категорий /category/{slug} (W97-fixB3b B3b-1, SEO-major):
   intent-запросы «розы с доставкой спб» / «сборные букеты купить» получают
   собственный URL вместо безиндексной вкладки каталога.
   В таблице categories НЕТ колонки slug (схему не меняем — db.php под запретом
   волны): слаг вычисляем на лету из name через slugify() — тот же паттерн
   транслитерации, что у товаров/поводов. Колонка description подхватится
   автоматически, если появится в будущих миграциях (SELECT *). */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$shopName = setting('shop_name', 'Nilov Flowers');

/* Категория по слагу: slug(name) === slug из URL (демо-набор мал — коллизий нет) */
$category = null;
try {
    foreach (db()->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll() as $cRow) {
        if (slugify((string)$cRow['name']) === $slug) {
            $category = $cRow;
            break;
        }
    }
} catch (Throwable $e) {
    $category = null;
}

if (!$category) {
    http_response_code(404);
    $pageTitle = 'Страница не найдена — ' . $shopName;
    require __DIR__ . '/partials/head.php';
    echo '<title>' . e($pageTitle) . '</title></head><body>';
    require __DIR__ . '/partials/header.php';
    echo '<main id="main" tabindex="-1"><section class="fc-section"><div class="wrap" style="max-width:560px;text-align:center">'
        . '<h1 class="page-hero__title">Такой категории нет</h1>'
        . '<p class="section-sub" style="margin:0 auto 24px">Зато свежие букеты ждут в общем каталоге.</p>'
        . '<a class="btn btn--accent" href="/#catalog">В каталог</a></div></section></main>';
    require __DIR__ . '/partials/footer.php';
    echo '</body></html>';
    exit;
}

$catId = (int)$category['id'];
$catName = (string)$category['name'];

/* Товары категории — та же сортировка, что в каталоге витрины (sort, id) */
$stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.category_id = :cid AND p.is_active = 1 ORDER BY p.sort, p.id');
$stmt->execute([':cid' => $catId]);
$products = $stmt->fetchAll();
foreach ($products as &$pRow) {
    $pRow['image'] = productImageFile($pRow);
}
unset($pRow);

/* Тумблеры карточек — те же, что у каталога витрины (критерий 16) */
$featDeliveryBadge = setting('feature_delivery_badge', '1') === '1';
$featFavorites = setting('feature_favorites', '1') === '1';
$cardCtx = ['featDeliveryBadge' => $featDeliveryBadge, 'featFavorites' => $featFavorites];

/* ---- Локальные хелперы карточек — копия index.php (srcset thumbs 400w) ---- */

function product_img_url(array $p): string
{
    return $p['image'] !== '' ? static_img_v('/img/products/' . rawurlencode($p['image'])) : '';
}

function product_img_webp(array $p): string
{
    if ($p['image'] === '') return '';
    $webp = '/img/products/' . rawurlencode(preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', $p['image']));
    return is_file(BASE_PATH . urldecode($webp)) ? static_img_v($webp) : '';
}

/* GD-превью /img/products/thumbs/{имя без ext}-400.webp — ленивая генерация,
   tmp+rename, PNG-альфа, оригинал ≤400px → '' (копия product_img_thumb index.php) */
function product_img_thumb(array $p): string
{
    static $cache = [];
    if (($p['image'] ?? '') === '') return '';
    $file = (string)$p['image'];
    if (isset($cache[$file])) return $cache[$file];

    $fail = static function () use (&$cache, $file): string {
        $cache[$file] = '';
        return '';
    };
    $src = IMG_PRODUCTS_DIR . '/' . $file;
    if (!is_file($src)) return $fail();
    $dim = @getimagesize($src);
    if ($dim === false) return $fail();
    [$srcW, $srcH, $type] = [(int)$dim[0], (int)$dim[1], (int)$dim[2]];
    if ($srcW <= 400) return $fail();

    $base = preg_replace('/\.[^.]+$/', '', $file) ?? $file;
    $thumbsDir = IMG_PRODUCTS_DIR . '/thumbs';
    $dst = $thumbsDir . '/' . $base . '-400.webp';
    $url = '/img/products/thumbs/' . rawurlencode($base . '-400.webp');
    if (is_file($dst)) return static_img_v($url);

    if (!is_dir($thumbsDir) && !@mkdir($thumbsDir, 0755, true)) return $fail();
    $srcIm = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => false,
    };
    if ($srcIm === false) return $fail();

    $w = 400;
    $h = max(1, (int)round($srcH * $w / $srcW));
    $dstIm = imagecreatetruecolor($w, $h);
    if ($type === IMAGETYPE_PNG) {
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

/* Обрезка текста до ~N символов по границе слова (для meta description) */
function meta_cut(string $s, int $max): string
{
    $s = trim((string)preg_replace('/\s+/u', ' ', $s));
    if ($s === '' || mb_strlen($s) <= $max) return $s;
    $cut = mb_substr($s, 0, $max);
    $sp = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp > 0) {
        $cut = mb_substr($cut, 0, $sp);
    }
    return trim((string)preg_replace('/[\s.,;:!?\-–—]+$/u', '', $cut));
}

/* Карточка товара — копия render_product_card() index.php (бейджи, srcset,
   lazy, сердечко, «+» в корзину): та же разметка/классы, что в каталоге витрины */
function render_product_card(array $p, array $ctx): void
{
    $price = productPrice($p);
    $isSale = $price !== (int)($p['price'] ?? 0);
    $isHit = (int)($p['is_hit'] ?? 0) === 1;
    $isPremium = (int)($p['is_premium'] ?? 0) === 1;
    $img = product_img_url($p);
    $imgWebp = product_img_webp($p);
    $thumb = product_img_thumb($p);
    $origW = product_img_width($p);
    if ($thumb !== '' && $imgWebp !== '' && $origW > 0) {
        $t600 = preg_replace('/-400(\.webp)(?=\?|$)/', '-600$1', $thumb);
        $srcset = $thumb . ' 400w'
            . ($t600 !== null && $t600 !== $thumb && is_file(BASE_PATH . parse_url($t600, PHP_URL_PATH)) ? ', ' . $t600 . ' 600w' : '')
            . ', ' . $imgWebp . ' ' . $origW . 'w';
    } elseif ($thumb !== '') {
        $srcset = $thumb . ' 400w';
    } elseif ($imgWebp !== '') {
        $srcset = $imgWebp;
    } else {
        $srcset = '';
    }
    $isCarousel = !empty($ctx['carousel']);
    /* S4: сетка каталога 4/3/2 — sizes по фактическим слотам карточки */
    $sizes = $isCarousel
        ? '(max-width:899px) 72vw, 280px'
        : '(max-width:359px) 92vw, (max-width:767px) 46vw, (max-width:1023px) 31vw, (max-width:1279px) 23vw, 288px';
    $link = '/product/' . rawurlencode($p['slug']);
    $searchIndex = $isCarousel ? '' : mb_strtolower(trim($p['name'] . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['description'] ?? '')));
    $upsellAttr = (!$isCarousel && (int)($p['show_in_upsell'] ?? 0) === 1) ? ' data-upsell="1"' : '';
    $tagsAttr = trim((string)($p['tags'] ?? '')) !== ''
        ? ' data-tags="' . e(mb_strtolower(str_replace('ё', 'е', preg_replace('/\s+/u', ' ', trim((string)$p['tags']))) ?? ''), 'UTF-8') . '"'
        : '';
    $img2 = trim((string)($p['image2'] ?? ''));
    $img2Url = '';
    if ($img2 !== '' && is_file(IMG_PRODUCTS_DIR . '/' . $img2)) {
        $img2Url = '/img/products/' . rawurlencode($img2);
    }
    $comp = trim((string)($p['composition'] ?? ''));
    $sizeText = trim((string)($p['size_text'] ?? ''));
    /* S6: состав и размер — скрытые носители данных для Quick View
       (js/quickview.js читает их из DOM карточки; в карточке не видны). */
    $sizeIsDia = mb_strpos($sizeText, '⌀') === 0;
    $sizeClean = trim((string)preg_replace('/^[⌀↕]\s*/u', '', $sizeText));
    /* S6: сплит — тихая серая строка «Сплит: от N ₽ × 4» (splitLabel —
       util.php, настройка split_label; пилюля-виджет убрана по ТЗ) */
    $splitText = splitLabel($price);
    /* S6: рейтинг карточки — живой агрегат отзывов из БД (по товару,
       фолбэк — общий агрегат магазина), «★ 5.0 (28)» жёлтой звездой */
    global $productRatings;
    $cardRating = $productRatings[(int)$p['id']] ?? null;
    if ($cardRating === null) {
        $shopAgg = getRatingAggregate();
        if ($shopAgg['count'] > 0) {
            $cardRating = $shopAgg;
        }
    }
    /* S6: строка доставки под фото («Сегодня за 1–2 часа», настройка
       card_delivery_text; пусто — не печатаем). */
    $deliveryText = trim((string)setting('card_delivery_text', 'Сегодня за 1–2 часа'));
    /* S6: подпись ссылки 1-клика — полный текст настройки. */
    $oneclickFull = trim((string)setting('card_btn_oneclick', 'Купить в 1 клик'));
    ?>
        <article class="product-card"<?= $isCarousel
            ? ''
            : ' data-category-id="' . (int)($p['category_id'] ?? 0) . '" data-price="' . (int)$price . '" data-hit="' . (int)($p['is_hit'] ?? 0) . '" data-premium="' . (int)($p['is_premium'] ?? 0) . '" data-search="' . e($searchIndex) . '"' . $upsellAttr . $tagsAttr ?>>
          <div class="product-card__media">
          <?php /* S8-инцидент «серые прямоугольники»: БЕЗ <picture>, srcset/sizes
                 прямо на <img>, loading="eager" + decoding="async" — файл
                 запрашивается немедленно (синхронно с index.php). */ ?>
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>" aria-hidden="true" tabindex="-1">
              <?php if ($img !== ''): ?>
                <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>"<?php if ($srcset !== ''): ?> srcset="<?= e($srcset) ?>"<?php if ($thumb !== ''): ?> sizes="<?= e($sizes) ?>"<?php endif; ?><?php endif; ?> loading="eager" decoding="async">
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--ink-muted)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
              <?php if ($img2Url !== ''): ?>
                <img class="product-card__img2" src="<?= e($img2Url) ?>" alt="" aria-hidden="true" loading="eager" decoding="async">
              <?php endif; ?>
            </a>
            <?php /* на фото ТОЛЬКО жёлтый «Хит» (#FFB800) и скидка (белая
                   плашка); «Премиум» — редкий третий, чёрный. */ ?>
            <?php if ($isSale): $offPct = (int)$p['price'] > 0 ? (int)round((1 - $price / (int)$p['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= $offPct > 0 ? '&#8722;' . (int)$offPct . '%' : e(setting('badge_sale_text', 'Скидка')) ?></span><?php endif; ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
          </div>
          <div class="product-card__body">
            <?php if (!empty($ctx['feature'])): ?>
            <span class="product-card__feature-label"><?= e(setting('feature_card_label', 'Выбор флориста')) ?></span>
            <?php endif; ?>
            <?php /* S7 СТРОКА 1 (ЦЕНА + СПЛИТ): крупная жирная цена чёрным
                   (22–24px, font-bold, tabular-nums), при скидке рядом —
                   зачёркнутая старая; под ней компактный серый бейдж
                   «Сплит 860 ₽ × 4» (splitLabel, настройка split_label).
                   Синхронно с index.php (копия render_product_card). */ ?>
            <p class="product-card__price">
              <?php if ($isSale): ?>
                <span class="product-card__price--discount"><?= formatPrice($price) ?></span>
                <span class="product-card__price--old"><?= formatPrice((int)$p['price']) ?></span>
              <?php else: ?>
                <?= formatPrice($price) ?>
              <?php endif; ?>
            </p>
            <?php if ($splitText !== ''): ?>
            <p class="product-card__split"><span class="product-card__split-badge"><?= e($splitText) ?></span></p>
            <?php endif; ?>
            <?php /* S7 СТРОКА 2 (НАЗВАНИЕ): 15px, чёрное, medium, ровно 2 строки */ ?>
            <a class="product-card__name" href="<?= e($link) ?>"><?= e($p['name']) ?></a>
            <?php /* S7 СТРОКА 3 (ДОВЕРИЕ И СРОК): одна строка через точку —
                   «⚡ За 1–2 ч · ★ 5.0 (24)», серый неброский текст #767676. */ ?>
            <?php if ($deliveryText !== '' || $cardRating !== null): ?>
            <p class="product-card__meta">
              <?php if ($deliveryText !== ''): ?><span class="product-card__meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg><?= e($deliveryText) ?></span><?php endif; ?>
              <?php if ($deliveryText !== '' && $cardRating !== null): ?><span class="product-card__meta-sep" aria-hidden="true">·</span><?php endif; ?>
              <?php if ($cardRating !== null): ?><span class="product-card__meta-item product-card__meta-item--rating"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><?= e(sprintf('%.1f', (float)$cardRating['avg'])) ?>&nbsp;<span class="product-card__rating-count">(<?= (int)$cardRating['count'] ?>)</span></span><?php endif; ?>
            </p>
            <?php endif; ?>
            <?php /* скрытые данные для Quick View (не отображаются) */ ?>
            <?php if ($comp !== ''): ?><p class="product-card__comp" hidden><?= e($comp) ?></p><?php endif; ?>
            <?php if ($sizeClean !== ''): ?>
            <p class="product-card__size" hidden><?php if ($sizeIsDia): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M6.5 17.5 17.5 6.5"/></svg><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/></svg><?php endif; ?><span class="product-card__size-text"><?= e($sizeClean) ?></span></p>
            <?php endif; ?>
            <?php /* строка 6: «В корзину» (чёрная) + быстрая ссылка «Купить в 1 клик» */ ?>
            <div class="product-card__actions">
              <button type="button" class="product-card__cta" data-order-cta
                data-product-id="<?= (int)$p['id'] ?>"
                data-product-name="<?= e($p['name']) ?>"
                data-product-price-raw="<?= $price ?>"
                data-product-image="<?= e($img) ?>"
                aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="Добавить в корзину"><?= e(setting('card_btn_cart', 'В корзину')) ?></button>
              <button type="button" class="product-card__oneclick" data-oneclick
                data-product-id="<?= (int)$p['id'] ?>"
                data-product-name="<?= e($p['name']) ?>"
                data-product-price-raw="<?= $price ?>"
                data-product-image="<?= e($img) ?>"
                aria-label="<?= e($oneclickFull) ?>: <?= e($p['name']) ?>" title="<?= e($oneclickFull) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg><span><?= e($oneclickFull) ?></span></button>
            </div>
          </div>
        </article>
    <?php
}



/* ---- Мета-контент страницы ---- */

/* W98-fixE (E1): интро категории — setting('category_intro_{slug}') с ручным текстом
   из БД (INSERT OR IGNORE в db.php); фолбэк — грамматически безопасный шаблон
   с {name} (старый дефолт «Свежие {name} с утренней поставки» давал калеку
   «Свежие В шляпной коробке с утренней поставки»). Колоночное описание категории
   (если появится в будущих миграциях) — резервная ветка. */
$intro = trim(str_replace('{name}', $catName, setting(
    'category_intro_' . $slug,
    '{name} с доставкой по Санкт-Петербургу — соберём под ваш повод и привезём в день заказа. Оплата при получении.'
)));
if ($intro === '') {
    $intro = trim((string)($category['description'] ?? ''));
}
if ($intro === '') {
    $intro = $catName . ' с доставкой по Санкт-Петербургу';
}

$canonicalUrl = 'https://flowers.interfood-catering.ru/category/' . rawurlencode($slug);
$metaTitle = $catName . ' — свежие букеты с доставкой по Санкт-Петербургу | ' . $shopName;
$metaDesc = meta_cut($intro, 160);
$pageTitle = $metaTitle;
/* $pageDescription → twitter:description в head.php; hero-og:image — ниже */
$pageDescription = $metaDesc;

/* og:image — hero-фото витрины (как у главной). W104-ζ: фикс двойного пути —
   hero_image с W103 может быть ПУТЁМ ОТ КОРНЯ (img/editorial/…), а не только
   именем из img/uploads/; раньше печаталось «/img/uploads/img/editorial/…» (404).
   Логика — как в partials/head.php (сегментное кодирование). */
$heroImg = setting('hero_image', '');
$__hiRoot = '';
if ($heroImg !== '') {
    $__hi = str_replace('\\', '/', $heroImg);
    $__hiRoot = is_file(IMG_UPLOADS_DIR . '/' . $__hi) ? 'img/uploads/' . $__hi
        : (is_file(BASE_PATH . '/' . $__hi) ? $__hi : '');
}
$__hiUrl = $__hiRoot !== '' ? '/' . implode('/', array_map('rawurlencode', explode('/', $__hiRoot))) : '';
$__hiDim = $__hiRoot !== '' ? @getimagesize(BASE_PATH . '/' . $__hiRoot) : false;
/* W103/F2: стили тулбара фильтров/сортировки — отдельный файл, подключается
   ниже отдельным <link> после five.css (версия — md5-хэш, паттерн head.php T4) */
$categoryCssV = substr((string)@md5_file(__DIR__ . '/css/category.css'), 0, 8);
/* W103/G2: display-ДНК вторички (Playfair H1, шкала H2) — общий файл для
   category+occasion, подключается ПОСЛЕ category.css (версия — md5-хэш) */
$secondaryCssV = substr((string)@md5_file(__DIR__ . '/css/secondary.css'), 0, 8);
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php if ($__hiUrl !== ''): ?>
<meta property="og:image" content="https://flowers.interfood-catering.ru<?= e($__hiUrl) ?>">
<?php if ($__hiDim !== false): ?>
<meta property="og:image:width" content="<?= (int)$__hiDim[0] ?>">
<meta property="og:image:height" content="<?= (int)$__hiDim[1] ?>">
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* W103/F2: тулбар категорий — ПОСЛЕ five.css (токены var(--pink)/var(--ink)
       из five.css доступны на этой же странице; @import не нужен — CSP) */ ?>
<link rel="stylesheet" href="/css/category.css?v=<?= e($categoryCssV) ?>">
<?php /* W103/G2: H1 выше фолда — Playfair, поэтому preload cyrillic-подмножества
       (21КБ, тот же паттерн product.php: FOUT на Georgia-фолбэке недопустим) */ ?>
<link rel="preload" href="/fonts/Inter-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/secondary.css?v=<?= e($secondaryCssV) ?>">
<?php /* BreadcrumbList — ОТДЕЛЬНЫЙ top-level JSON-LD (Главная → Каталог → категория),
   в SERP — хлебные крошки; noindex НЕ ставим */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://flowers.interfood-catering.ru/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Каталог', 'item' => 'https://flowers.interfood-catering.ru/#catalog'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $catName, 'item' => $canonicalUrl],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php /* W99-fixG (G10): ItemList — позиции-URL товаров категории (карусель SERP-
   фичер для intent-страниц; пустая категория — без ItemList). */ ?>
<?php if ($products !== []): ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'itemListElement' => array_map(static fn (int $i, array $p): array => [
        '@type' => 'ListItem',
        'position' => $i,
        'url' => 'https://flowers.interfood-catering.ru/product/' . rawurlencode($p['slug']),
    ], range(1, count($products)), array_values($products)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php endif; ?>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>
<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> / <a href="/#catalog">Каталог</a> / <span aria-current="page"><?= e($catName) ?></span>
      </nav>
      <div class="page-hero">
        <?php /* W103/G2: display-ДНК — H1 категорий Playfair 600 (SOTD-судья: «H1
               Montserrat 36px — маркетплейс»). Первое слово — italic-акцент
               pink-deep (паттерн fc-hero__accent главной). Разбиение по первому
               пробелу, e() на ОБЕ части (mb_* — UTF-8-безопасно). */ ?>
        <?php
        $h1Text = $catName . ' с доставкой по Санкт-Петербургу';
        $h1Space = mb_strpos($h1Text, ' ');
        $h1First = $h1Space === false ? $h1Text : mb_substr($h1Text, 0, $h1Space);
        $h1Rest = $h1Space === false ? '' : mb_substr($h1Text, $h1Space);
        ?>
        <h1 class="page-hero__title"><em class="page-hero__accent"><?= e($h1First) ?></em><?= e($h1Rest) ?></h1>
        <p class="section-sub"><?= e($intro) ?></p>
      </div>

      <?php if ($products !== []): ?>
      <?php /* W103/F2 (P0 двух критиков: «на /category нет ни фильтров, ни сортировки»):
             тулбар под H1 — чипы цены (пороги = чипы главной: chips_price_low/high)
             + сегмент-контрол сортировки + счётчик + сброс. Фильтрация/сортировка —
             js/category.js по data-price (фактическая цена с учётом sale_price) и
             data-hit карточек; URL-синхронизация ?price=…&sort=… (replaceState). */ ?>
      <?php
        $catFeatChips = setting('feature_chips', '1') === '1';
        $catLow = (int)setting('chips_price_low', '3500');
        $catHigh = (int)setting('chips_price_high', '7000');
      ?>
      <div class="cat-toolbar" id="catToolbar">
        <div class="cat-toolbar__controls">
          <?php if ($catFeatChips): ?>
          <div class="cat-chips" role="group" aria-label="Фильтр букетов по цене">
            <button type="button" class="cat-chip is-active" data-chip="all" aria-pressed="true">Все</button>
            <button type="button" class="cat-chip" data-chip="low" data-max="<?= $catLow ?>" aria-pressed="false">до <?= formatSum($catLow) ?> ₽</button>
            <button type="button" class="cat-chip" data-chip="mid" data-min="<?= $catLow ?>" data-max="<?= $catHigh ?>" aria-pressed="false"><?= formatSum($catLow) ?>–<?= formatSum($catHigh) ?> ₽</button>
            <button type="button" class="cat-chip" data-chip="high" data-min="<?= $catHigh ?>" aria-pressed="false">от <?= formatSum($catHigh) ?> ₽</button>
          </div>
          <?php endif; ?>
          <div class="cat-sort" role="group" aria-label="Сортировка букетов">
            <span class="cat-sort__label" aria-hidden="true">Сортировка:</span>
            <span class="cat-sort__group">
              <button type="button" class="cat-sort__btn is-active" data-sort="pop" aria-pressed="true">Сначала популярные</button>
              <button type="button" class="cat-sort__btn" data-sort="asc" aria-pressed="false">Дешевле</button>
              <button type="button" class="cat-sort__btn" data-sort="desc" aria-pressed="false">Дороже</button>
            </span>
          </div>
        </div>
        <div class="cat-toolbar__meta">
          <span class="cat-count" id="catCount" role="status" aria-live="polite"></span>
          <button type="button" class="cat-reset" id="catReset" hidden>Сбросить</button>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($products !== []): ?>
      <?php /* id="catGrid" (НЕ catalogGrid): catalog-filter.js/five.js главной ищут
             #catalogGrid — на этой странице их нет (footer.php грузит их только на
             главной), а уникальный id подхватывает js/category.js. Вторичный режим:
             поиск из шапки уводит на /?q=…#catalog — как у occasion/product. */ ?>
      <div class="catalog__grid" id="catGrid">
        <?php foreach ($products as $p) { render_product_card($p, $cardCtx); } ?>
        <?php /* W105-6fix1: хвост ряда — та же CTA-плитка, что в каталоге
               главной (общий класс .catalog__grid — общий хвост); span под
               остаток ряда ставит js/grid-tail.js (вызывается из apply()
               category.js — в т.ч. после сортировки appendChild-ом, плитка
               сама возвращается последним ребёнком). */ ?>
        <?php
        $tailTitle = trim((string)setting('catalog_tail_title', 'Соберём|на заказ'));
        [$tailT1, $tailT2] = array_pad(explode('|', $tailTitle, 2), 2, '');
        if ($tailT1 !== ''):
        ?>
        <a class="catalog-tail reveal" href="/#order" data-grid-tail aria-label="Собрать букет на заказ">
          <span class="catalog-tail__kicker"><?= e(setting('catalog_tail_kicker', 'Не нашли нужный букет?')) ?></span>
          <span class="catalog-tail__title"><?= e($tailT1) ?><?= $tailT2 !== '' ? ' <em>' . e($tailT2) . '</em>' : '' ?></span>
          <span class="catalog-tail__text"><?= e(setting('catalog_tail_text', 'Под ваш повод, палитру и бюджет — фото готового букета пришлём до отправки')) ?></span>
          <span class="catalog-tail__arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>
        <?php endif; ?>
      </div>
      <?php /* W103/F2: пустое состояние фильтра — 0 карточек в диапазоне (тексты —
             settings с дефолтами, паттерн catalog_empty_* главной).
             C-c4 (CRO P0 «поиск-тупик», аналог главной): фильтр вырезал всё —
             выход в индивидуальный заказ «Собрать на заказ» (акцентная кнопка,
             /#order), сброс фильтров — вторым (как resetPriceBtn главной).
             Ряд .cat-empty__actions — css/category.css. */ ?>
      <div class="cat-empty" id="catEmpty" hidden>
        <p class="cat-empty__title"><?= e(setting('category_filter_empty_title', 'В этой ценовой категории пока пусто')) ?></p>
        <p class="cat-empty__hint"><?= e(setting('category_filter_empty_hint', 'Попробуйте другой диапазон или посмотрите все букеты категории')) ?></p>
        <div class="cat-empty__actions">
          <a class="btn btn--accent cat-empty__cta" href="/#order">Собрать на заказ</a>
          <button type="button" class="btn btn--outline" id="catEmptyReset">Сбросить фильтры</button>
        </div>
      </div>
      <?php else: ?>
      <?php /* Пустая категория — честная заглушка со ссылкой на общий каталог */ ?>
      <div class="catalog-empty" style="text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px"><?= e(setting('category_empty_title', 'В этой категории пока пусто 🌷')) ?></p>
        <p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px"><?= e(setting('category_empty_hint', 'Свежие букеты других категорий — в общем каталоге')) ?></p>
        <a class="btn btn--outline" href="/#catalog"><?= e(setting('catalog_title', 'Каталог')) ?></a>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <?php /* D-d3 (маркетолог P1, волна 2): на страницах категорий не было ни
         одного H2 — недобор по СЧ/НЧ. SEO-блок под сеткой: H2 «Сколько стоит
         доставка?» + ответ — СУЩЕСТВУЮЩАЯ пара faq_q1/faq_a1 (та же живёт в
         FAQ главной, редактируется в админке синхронно), без выдуманного
         текста и без воды. Выбор «Доставка {категория} по СПб» отвергнут:
         составные имена категорий («В шляпной коробке», «Сладкие подарки»)
         дают нескладные заголовки. Типографика — .section-title (Playfair-
         шкала H2 вторичек, secondary.css §4) + читательская .prose (§4b). */ ?>
  <section class="fc-section fc-section--subtle">
    <div class="wrap" style="max-width:760px">
      <h2 class="section-title"><?= e(setting('faq_q1', 'Сколько стоит доставка?')) ?></h2>
      <div class="prose">
        <p><?= e(setting('faq_a1', 'Зависит от района: 300–500 ₽ по Санкт-Петербургу, самовывоз бесплатный. Точная сумма сразу видна при оформлении заказа.')) ?></p>
      </div>
      <p style="margin:32px 0 0;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn--accent" href="/#order">Заказать с доставкой сегодня</a>
        <a class="btn btn--outline" href="/#catalog">Весь каталог</a>
      </p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<?php /* W103/F2: фильтры/сортировка категории — только на этой странице (версия
       ?v= — md5-хэш, паттерн footer.php A9; defer — после построения DOM) */ ?>
<script src="/js/category.js?v=<?= e(substr((string)@md5_file(__DIR__ . '/js/category.js'), 0, 8)) ?>" defer></script>
</body>
</html>
