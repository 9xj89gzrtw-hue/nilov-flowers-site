<?php
/* Occasion-лендинги (SEO-критик 4/10 critical#2: не было страниц под intent-запросы
   «свадебные букеты спб» / «букет на день рождения» / «траурные композиции»).
   Контент и тумблер active — в админке (критерии 14/33). */
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
    $pageTitle = 'Страница не найдена';
    require __DIR__ . '/partials/head.php';
    echo '<title>' . e($pageTitle) . '</title></head><body>';
    require __DIR__ . '/partials/header.php';
    echo '<main id="main"><section class="section"><div class="wrap"><h1 class="section-title">Такой страницы нет</h1>'
        . '<p class="section-sub">Зато свежие букеты — <a href="/#catalog">в каталоге</a>.</p></div></section></main>';
    require __DIR__ . '/partials/footer.php';
    echo '</body></html>';
    exit;
}

$canonicalUrl = 'https://flowers.interfood-catering.ru/occasion/' . rawurlencode($oc['slug']);
$metaTitle = $oc['meta_title'] !== '' ? $oc['meta_title'] : ($oc['title'] . ' — ' . $shopName);
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
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $oc['title'],
    'description' => $metaDesc,
    'url' => $canonicalUrl,
    'isPartOf' => ['@type' => 'WebSite', 'name' => $shopName, 'url' => 'https://flowers.interfood-catering.ru/'],
] + ($faq !== [] ? ['mainEntity' => array_map(static fn($f) => [
    '@type' => 'Question', 'name' => $f['q'],
    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
], $faq)] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>
<main id="main">
  <section class="section" style="padding-top:34px">
    <div class="wrap" style="max-width:880px">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <ol>
          <li><a href="/">Главная</a></li>
          <li><a href="/#catalog">Каталог</a></li>
          <li aria-current="page"><?= e($oc['title']) ?></li>
        </ol>
      </nav>
      <h1 class="section-title"><?= e($oc['title']) ?></h1>
      <p class="section-sub" style="max-width:720px"><?= e($oc['intro']) ?></p>

      <?php if ($products !== []): ?>
      <div class="catalog__grid" style="margin-top:8px">
        <?php foreach ($products as $p): $price = productPrice($p); $img = '/img/products/' . rawurlencode(productImageFile($p)); ?>
        <article class="product-card">
          <div class="product-card__media">
            <a class="product-card__media-link" href="/product/<?= e($p['slug']) ?>" aria-label="<?= e($p['name']) ?>">
              <picture>
                <?php $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', urldecode($img)); ?>
                <?php if ($webp !== $img && is_file(BASE_PATH . $webp)): ?><source type="image/webp" srcset="<?= e($webp) ?>"><?php endif; ?>
                <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
              </picture>
            </a>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$p['id'] ?>"
              data-product-name="<?= e($p['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="В корзину">+</button>
          </div>
          <div class="product-card__body">
            <a class="product-card__name" href="/product/<?= e($p['slug']) ?>"><?= e($p['name']) ?></a>
            <p class="product-card__price"><?= formatPrice($price) ?></p>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($oc['body'] !== ''): ?>
      <div style="margin-top:26px;font-size:1rem;line-height:1.65"><?= nl2br(e($oc['body'])) ?></div>
      <?php endif; ?>

      <?php if ($faq !== []): ?>
      <h2 class="section-title" style="font-size:clamp(1.3rem,2.6vw,1.7rem);margin-top:36px">Частые вопросы</h2>
      <?php foreach ($faq as $f): ?>
      <details class="faq-item" style="margin-top:10px">
        <summary class="faq-item__q"><?= e($f['q']) ?></summary>
        <p class="faq-item__a"><?= e($f['a']) ?></p>
      </details>
      <?php endforeach; ?>
      <?php endif; ?>

      <p style="margin-top:32px;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn--accent" href="/#order">Заказать с доставкой сегодня</a>
        <a class="btn btn--outline" href="/#catalog">Весь каталог</a>
      </p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
