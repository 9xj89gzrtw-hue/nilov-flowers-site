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
    $pageTitle = 'Страница не найдена';
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

/* Карточка подборки — как на витрине (бейджи «Хит/Премиум/Скидка» + «+» в корзину) */
function render_occasion_card(array $p): void
{
    $price = productPrice($p);
    $isSale = $price !== (int)$p['price'];
    $isHit = (int)($p['is_hit'] ?? 0) === 1;
    $isPremium = (int)($p['is_premium'] ?? 0) === 1;
    $isUrgent = (int)($p['is_urgent'] ?? 0) === 1;
    $file = productImageFile($p);
    $img = $file !== '' ? '/img/products/' . rawurlencode($file) : '';
    $webp = '';
    if ($img !== '') {
        $w = preg_replace('/\.(jpe?g|png)$/i', '.webp', urldecode($img));
        $webp = $w !== $img && is_file(BASE_PATH . $w) ? $w : '';
    }
    $link = '/product/' . rawurlencode($p['slug']);
    ?>
        <article class="product-card">
          <div class="product-card__media">
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>">
              <picture>
                <?php if ($webp !== ''): ?><source type="image/webp" srcset="<?= e($webp) ?>"><?php endif; ?>
                <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
              </picture>
            </a>
            <?php if ($isSale): $offPct = (int)$p['price'] > 0 ? (int)round((1 - $price / (int)$p['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= e(setting('badge_sale_text', 'Акционная цена')) ?><?php if ($offPct > 0): ?> −<?= $offPct ?>%<?php endif; ?></span><?php endif; ?>
            <?php if ($isUrgent): ?><span class="product-card__badge product-card__badge--urgent"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$p['id'] ?>"
              data-product-name="<?= e($p['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="В корзину">+</button>
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
