<?php
/* Occasion-лендинги (SEO-критик 4/10 critical#2: не было страниц под intent-запросы
   «свадебные букеты спб» / «букет на день рождения» / «траурные композиции»).
   Контент и тумблер active — в админке (критерии 14/33).
   Редизайн 5cv (W96/T2-d): page-hero + карточки как на витрине + fc-faq. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$slug = (string)($_GET['slug'] ?? '');
$stmt = db()->prepare('SELECT * FROM occasions WHERE slug = :s AND active = 1');
$stmt->execute([':s' => $slug]);
$oc = $stmt->fetch();
$shopName = setting('shop_name', 'Nilov Flowers');

if (!$oc) {
    http_response_code(404);
    $pageTitle = 'Страница не найдена — ' . $shopName;
    require __DIR__ . '/partials/head.php';
    echo '<title>' . e($pageTitle) . '</title></head><body>';
    require __DIR__ . '/partials/header.php';
    echo '<main id="main" tabindex="-1"><section class="fc-section"><div class="wrap" style="max-width:560px;text-align:center">'
        . '<h1 class="page-hero__title">Такой страницы нет</h1>'
        . '<p class="section-sub" style="margin:0 auto 24px">Зато свежие букеты — в каталоге.</p>'
        . '<a class="btn btn--accent" href="/#catalog">В каталог</a></div></section></main>';
    require __DIR__ . '/partials/footer.php';
    echo '</body></html>';
    exit;
}

$canonicalUrl = 'https://flowers.interfood-catering.ru/occasion/' . rawurlencode($oc['slug']);
/* W98-fixE (E17): title повода — всегда с брендом «— Nilov Flowers» (по образцу
   product.php); meta_title из админки без бренда больше не оставляет «голый» title */
$metaTitle = ($oc['meta_title'] !== '' ? $oc['meta_title'] : $oc['title']) . ' — ' . $shopName;
$metaDesc = $oc['meta_description'] !== '' ? $oc['meta_description'] : mb_substr($oc['intro'], 0, 160);

/* Подборка: только витринные товары с ценой и фото (без «0 ₽»-дыр). */
$ids = array_values(array_filter(array_map('intval', explode(',', (string)$oc['product_ids']))));
$products = [];
if ($ids !== []) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $ps = db()->prepare("SELECT * FROM products WHERE id IN ($in) AND is_active = 1 AND price > 0 AND image <> '' ORDER BY sort, id");
    $ps->execute($ids);
    $products = $ps->fetchAll();
}
$faq = [];
foreach ([['faq_q1', 'faq_a1'], ['faq_q2', 'faq_a2']] as [$qk, $ak]) {
    $q = trim((string)$oc[$qk]);
    $a = trim((string)$oc[$ak]);
    if ($q !== '' && $a !== '') { $faq[] = ['q' => $q, 'a' => $a]; }
}

/* Карточка подборки — как на витрине (бейджи «Хит/Премиум/Скидка» + «+» в корзину).
   W97-fixB3b: srcset с thumbs-400 как в каталоге (B3b-3) + сердечко избранного
   с той же разметкой/классами, что на главной (B3b-4). */
function product_img_webp_local(array $p): string
{
    if (($p['image'] ?? '') === '') return '';
    $webp = '/img/products/' . rawurlencode(preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', $p['image']));
    return is_file(BASE_PATH . urldecode($webp)) ? $webp : '';
}

/* GD-превью /img/products/thumbs/{имя без ext}-400.webp — копия подхода
   product_img_thumb из index.php (W96-fix3a T2): ленивая генерация, tmp+rename,
   PNG-альфа, оригинал ≤400px / сбой GD → '' (деградация до прежнего вида) */
function product_img_thumb_local(array $p): string
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

function product_img_width_local(array $p): int
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

function render_occasion_card(array $p): void
{
    $price = productPrice($p);
    $isSale = $price !== (int)$p['price'];
    $isHit = (int)($p['is_hit'] ?? 0) === 1;
    $isPremium = (int)($p['is_premium'] ?? 0) === 1;
    $isUrgent = (int)($p['is_urgent'] ?? 0) === 1;
    $file = productImageFile($p);
    $img = $file !== '' ? '/img/products/' . rawurlencode($file) : '';
    $webp = product_img_webp_local($p);
    /* W97-fixB3b (B3b-3): srcset — webp-превью 400w + webp-оригинал {w}w (как каталог) */
    $thumb = $img !== '' ? product_img_thumb_local($p) : '';
    $origW = $img !== '' ? product_img_width_local($p) : 0;
    if ($thumb !== '' && $webp !== '' && $origW > 0) {
        $srcset = $thumb . ' 400w, ' . $webp . ' ' . $origW . 'w';
        $sizes = '(max-width:899px) 45vw, (min-width:900px) 300px';
    } elseif ($thumb !== '') {
        $srcset = $thumb . ' 400w';
        $sizes = '(max-width:899px) 45vw, (min-width:900px) 300px';
    } else {
        $srcset = $webp;
        $sizes = '';
    }
    $link = '/product/' . rawurlencode($p['slug']);
    ?>
        <article class="product-card">
          <div class="product-card__media">
            <?php /* W99-fixG (G11): img-ссылка дублирует title-ссылку — прячем от
                   скринридера и Tab-фокуса (href сохранён: клик мышью работает) */ ?>
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>" aria-hidden="true" tabindex="-1">
              <picture>
                <?php if ($srcset !== ''): ?><source type="image/webp" srcset="<?= e($srcset) ?>"<?= $sizes !== '' ? ' sizes="' . e($sizes) . '"' : '' ?>><?php endif; ?>
                <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
              </picture>
            </a>
            <?php if ($isSale): $offPct = (int)$p['price'] > 0 ? (int)round((1 - $price / (int)$p['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= e(setting('badge_sale_text', 'Скидка')) ?><?php if ($offPct > 0): ?> <?= $offPct ?>%<?php endif; ?></span><?php endif; ?>
            <?php if ($isUrgent): ?><span class="product-card__badge product-card__badge--urgent"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$p['id'] ?>"
              data-product-name="<?= e($p['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="В корзину">+</button>
            <?php /* W97-fixB3b (B3b-4): сердечко — та же разметка/классы, что на главной
                   (js/nilov.js ловит клики делегированно на любой странице) */ ?>
            <?php if (setting('feature_favorites', '1') === '1'): ?><button type="button" class="product-card__fav" data-fav-id="<?= (int)$p['id'] ?>" data-fav-name="<?= e($p['name']) ?>" aria-label="В избранное: <?= e($p['name']) ?>" title="В избранное">♡</button><?php endif; ?>
          </div>
          <div class="product-card__body">
            <a class="product-card__name" href="<?= e($link) ?>"><?= e($p['name']) ?></a>
            <p class="product-card__price">
              <?php if ($isSale): ?>
                <span class="product-card__price--old"><?= formatPrice((int)$p['price']) ?></span>
                <span class="product-card__price--discount"><?= formatPrice($price) ?></span>
              <?php else: ?>
                <?= formatPrice($price) ?>
              <?php endif; ?>
            </p>
          </div>
        </article>
    <?php
}
$pageTitle = $metaTitle;
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php $ocImg = $products !== [] ? '/img/products/' . rawurlencode(productImageFile($products[0])) : ''; ?>
<?= $ocImg !== '' ? '<meta property="og:image" content="https://flowers.interfood-catering.ru' . e($ocImg) . '">' : '' ?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* W97-fixB3b (B3b-3): CollectionPage БЕЗ mainEntity-вопросов (FAQ как
   mainEntity у CollectionPage невалиден) — FAQ вынесен в отдельный top-level
   FAQPage ниже; CollectionPage оставлен: name/description/url/isPartOf */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $oc['title'],
    'description' => $metaDesc,
    'url' => $canonicalUrl,
    'isPartOf' => ['@type' => 'WebSite', 'name' => $shopName, 'url' => 'https://flowers.interfood-catering.ru/'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php if ($faq !== []): ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn(array $f): array => [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faq),
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
        <a href="/">Главная</a> / <a href="/#catalog">Каталог</a> / <span aria-current="page"><?= e($oc['title']) ?></span>
      </nav>
      <div class="page-hero">
        <h1 class="page-hero__title"><?= e($oc['title']) ?></h1>
        <p class="section-sub"><?= e($oc['intro']) ?></p>
      </div>

      <?php if ($products !== []): ?>
      <div class="catalog__grid">
        <?php foreach ($products as $p) { render_occasion_card($p); } ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($oc['body'] !== ''): ?>
  <section class="fc-section fc-section--subtle">
    <div class="wrap"><div class="prose"><?= nl2br(e($oc['body'])) ?></div></div>
  </section>
  <?php endif; ?>

  <section class="fc-section">
    <div class="wrap" style="max-width:760px">
      <?php if ($faq !== []): ?>
      <h2 class="section-title"><?= e(setting('faq_title', 'Частые вопросы')) ?></h2>
      <div class="fc-faq">
      <?php foreach ($faq as $f): ?>
      <details class="faq-item">
        <summary class="faq-item__q"><?= e($f['q']) ?></summary>
        <p class="faq-item__a"><?= e($f['a']) ?></p>
      </details>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <p style="margin-top:<?= $faq !== [] ? '32' : '0' ?>px;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn--accent" href="/#order">Заказать с доставкой сегодня</a>
        <a class="btn btn--outline" href="/#catalog">Весь каталог</a>
      </p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
