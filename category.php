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
    return $p['image'] !== '' ? '/img/products/' . rawurlencode($p['image']) : '';
}

function product_img_webp(array $p): string
{
    if ($p['image'] === '') return '';
    $webp = '/img/products/' . rawurlencode(preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', $p['image']));
    return is_file(BASE_PATH . urldecode($webp)) ? $webp : '';
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
    if (is_file($dst)) return $url;

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
    return $url;
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
        $srcset = $thumb . ' 400w, ' . $imgWebp . ' ' . $origW . 'w';
    } elseif ($thumb !== '') {
        $srcset = $thumb . ' 400w';
    } elseif ($imgWebp !== '') {
        $srcset = $imgWebp;
    } else {
        $srcset = '';
    }
    $sizes = '(max-width:899px) 45vw, (min-width:900px) 300px';
    $link = '/product/' . rawurlencode($p['slug']);
    $searchIndex = mb_strtolower(trim($p['name'] . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['description'] ?? '')));
    ?>
        <article class="product-card reveal" data-category-id="<?= (int)($p['category_id'] ?? 0) ?>" data-price="<?= (int)$price ?>" data-hit="<?= (int)($p['is_hit'] ?? 0) ?>" data-premium="<?= (int)($p['is_premium'] ?? 0) ?>" data-search="<?= e($searchIndex) ?>">
          <div class="product-card__media">
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>">
              <?php if ($img !== ''): ?>
                <picture>
                  <?php if ($srcset !== ''): ?><source type="image/webp" srcset="<?= e($srcset) ?>"<?= $thumb !== '' ? ' sizes="' . e($sizes) . '"' : '' ?>><?php endif; ?>
                  <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
                </picture>
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
            </a>
            <?php if ($isSale): $offPct = (int)$p['price'] > 0 ? (int)round((1 - $price / (int)$p['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= e(setting('badge_sale_text', 'Скидка')) ?><?php if ($offPct > 0): ?> <?= $offPct ?>%<?php endif; ?></span><?php endif; ?>
            <?php if ((int)($p['is_urgent'] ?? 0) === 1): ?><span class="product-card__badge product-card__badge--urgent"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <?php if ($ctx['featDeliveryBadge']): ?><span class="product-card__badge product-card__badge--deliv"><?= e(setting('delivery_badge_text', 'Доставка по Санкт-Петербургу')) ?></span><?php endif; ?>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$p['id'] ?>"
              data-product-name="<?= e($p['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="В корзину">+</button>
            <?php if ($ctx['featFavorites']): ?><button type="button" class="product-card__fav" data-fav-id="<?= (int)$p['id'] ?>" data-fav-name="<?= e($p['name']) ?>" aria-label="В избранное: <?= e($p['name']) ?>" title="В избранное">♡</button><?php endif; ?>
          </div>
          <div class="product-card__body">
            <span class="product-card__cat"><?= e($p['category_name'] ?? '') ?></span>
            <a class="product-card__name" href="<?= e($link) ?>"><?= e($p['name']) ?></a>
            <p class="product-card__price">
              <?php if ($isSale): ?>
                <span class="product-card__price--old"><?= formatPrice((int)$p['price']) ?></span>
                <span class="product-card__price--discount"><?= formatPrice($price) ?></span>
              <?php else: ?>
                <?= formatPrice($price) ?>
              <?php endif; ?>
            </p>
            <?php if ((int)($p['is_urgent'] ?? 0) === 1): ?>
            <p class="product-card__urgent-note">Соберём и доставим в течение дня — количество ограничено</p>
            <?php endif; ?>
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
$metaTitle = $catName . ' — купить с доставкой в СПб | ' . $shopName;
$metaDesc = meta_cut($intro, 160);
$pageTitle = $metaTitle;
/* $pageDescription → twitter:description в head.php; hero-og:image — ниже */
$pageDescription = $metaDesc;

/* og:image — hero-фото витрины (как у главной) */
$heroImg = setting('hero_image', '');
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php if ($heroImg !== ''): ?>
<meta property="og:image" content="https://flowers.interfood-catering.ru/img/uploads/<?= e(rawurlencode($heroImg)) ?>">
<?php $ogDim = @getimagesize(IMG_UPLOADS_DIR . '/' . $heroImg); ?>
<?php if ($ogDim !== false): ?>
<meta property="og:image:width" content="<?= (int)$ogDim[0] ?>">
<meta property="og:image:height" content="<?= (int)$ogDim[1] ?>">
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/head.php'; ?>
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
        <h1 class="page-hero__title"><?= e($catName) ?> с доставкой по Санкт-Петербургу</h1>
        <p class="section-sub"><?= e($intro) ?></p>
      </div>

      <?php if ($products !== []): ?>
      <?php /* Без id="catalogGrid": catalog-filter.js грузится только на главной —
             five.js с catalogGrid ушёл бы в режим живого поиска без реального
             фильтра (NfCatalogApply не определён). Вторичный режим: поиск из шапки
             уводит на /?q=…#catalog — то же поведение, что у occasion/product. */ ?>
      <div class="catalog__grid">
        <?php foreach ($products as $p) { render_product_card($p, $cardCtx); } ?>
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

  <section class="fc-section fc-section--subtle">
    <div class="wrap" style="max-width:760px">
      <p style="margin:0;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn--accent" href="/#order">Заказать с доставкой сегодня</a>
        <a class="btn btn--outline" href="/#catalog">Весь каталог</a>
      </p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
