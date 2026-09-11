<?php
/* Карточка товара: /product/{slug} (роутер) или product.php?slug=... */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$slug = (string)($_GET['slug'] ?? '');
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
    echo '<main><section class="section"><div class="wrap"><h1 class="section-title">Товар не найден</h1>'
        . '<p class="section-sub"><a href="/">Вернуться в каталог</a></p></div></section></main>';
    require __DIR__ . '/partials/footer.php';
    echo '</body></html>';
    exit;
}

$price = productPrice($product);
$isSale = $price !== (int)$product['price'];
$img = $product['image'] !== '' ? '/img/products/' . rawurlencode($product['image']) : '';
if ($img === '') {
    /* Демо-фото для товара без загруженного изображения */
    $demoImages = ['roz.jpg', 'p2.jpg', 'p3.jpg'];
    $img = '/img/products/' . $demoImages[$product['id'] % count($demoImages)];
}

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
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e($product['name']) ?> — <?= e(setting('shop_name', 'Магазин цветов')) ?></title>
<meta name="description" content="<?= e(mb_substr($product['description'], 0, 160)) ?>">
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* JSON-LD Product+Offer — canonical 2026 (ecorn.agency structured-data-ecommerce) */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product['name'],
    'description' => $product['description'] !== '' ? $product['description'] : $product['name'],
    'image' => $img !== '' ? [$img] : [],
    'offers' => [
        '@type' => 'Offer',
        'price' => $price,
        'priceCurrency' => 'RUB',
        'availability' => $product['is_urgent'] == 1 ? 'https://schema.org/InStock' : 'https://schema.org/InStock',
    ],
] + ($product['sale_price'] !== null ? ['basePrice' => (int)$product['price']] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main class="product-page">
  <div class="wrap">
    <nav class="breadcrumbs" aria-label="Хлебные крошки">
      <ol>
        <li><a href="/">Главная</a></li>
        <li><a href="/#catalog">Каталог</a></li>
        <?php if (!empty($product['category_name'])): ?><li><a href="/?category=<?= (int)$product['category_id'] ?>#catalog"><?= e($product['category_name']) ?></a></li><?php endif; ?>
        <li aria-current="page"><?= e($product['name']) ?></li>
      </ol>
    </nav>
    <div class="product-page__layout">
      <div class="product-gallery">
        <div class="product-page__media" data-lightbox-trigger data-lightbox-src="<?= e($img) ?>" data-lightbox-alt="<?= e($product['name']) ?>">
          <?php if ($img !== ''): ?>
            <img class="product-gallery__img" src="<?= e($img) ?>" alt="<?= e($product['name']) ?>">
          <?php else: ?>
            <div class="product-gallery__slide--placeholder">
              <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
            </div>
          <?php endif; ?>
          <?php if ($isSale): ?><span class="product-page__badge">Скидка</span><?php endif; ?>
        </div>
      </div>
      <div class="product-page__info">
        <?php if (!empty($product['category_name'])): ?><p class="product-page__category"><?= e($product['category_name']) ?></p><?php endif; ?>
        <h1 class="product-page__name"><?= e($product['name']) ?></h1>
        <p class="product-page__price">
          <?php if ($isSale): ?>
            <span class="product-page__price--old"><?= formatPrice((int)$product['price']) ?></span>
            <span class="product-page__price--discount"><?= formatPrice($price) ?></span>
          <?php else: ?>
            <span><?= formatPrice($price) ?></span>
          <?php endif; ?>
        </p>
        <?php if ($product['description'] !== ''): ?><p class="product-page__description"><?= nl2br(e($product['description'])) ?></p><?php endif; ?>
        <button type="button" class="btn btn--accent product-page__cta" data-order-cta
          data-product-id="<?= (int)$product['id'] ?>"
          data-product-name="<?= e($product['name']) ?>"
          data-product-price-raw="<?= $price ?>"
          data-product-image="<?= e($img) ?>"
          aria-label="Добавить в корзину: <?= e($product['name']) ?>">
          Добавить в корзину · <?= formatPrice($price) ?>
        </button>
        <p class="cart-note">Оплата — картой, СБП или при получении. На защищённой странице платёжного провайдера.</p>
        <?php if ($trust !== []): ?>
        <ul class="product-page__trust">
          <?php foreach ($trust as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
