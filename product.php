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
$img = '/img/products/' . rawurlencode(productImageFile($product));

/* WebP-пара к фото товара (каталог уже отдаёт webp через <picture>) */
$imgWebp = preg_replace('/\.(jpe?g|png)$/i', '.webp', urldecode($img));
$imgWebpOk = $imgWebp !== $img && is_file(BASE_PATH . $imgWebp);

/* Бейджи 5cv (is_hit/is_premium может не быть в старой БД — читаем через ?? 0) */
$isHit = (int)($product['is_hit'] ?? 0) === 1;
$isPremium = (int)($product['is_premium'] ?? 0) === 1;
$isUrgent = (int)($product['is_urgent'] ?? 0) === 1;
$offPct = $isSale && (int)$product['price'] > 0 ? (int)round((1 - $price / (int)$product['price']) * 100) : 0;

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

/* Мета-блок 5cv: доставка / самовывоз / оплата (тексты — из настроек) */
$pickupAddr = trim(setting('pickup_address', '')) ?: trim(setting('shop_address', ''));
$ykOn = setting('yk_enabled', '0') === '1' && trim(setting('yk_shop_id', '')) !== '' && trim(setting('yk_secret_key', '')) !== '';

/* Иконки мета-блока (inline SVG в стиле витрины; размер/цвет задаёт .fc-product__meta-icon) */
$metaIcons = [
    'truck' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 6h11v11H2z"/><path d="M13 9h4l3 3v5h-3"/><circle cx="5.5" cy="17.5" r="2"/><circle cx="16.5" cy="17.5" r="2"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
    'map' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>',
    'card' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
    'camera' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
    'flower' => '<svg viewBox="0 0 32 32" aria-hidden="true"><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor"/><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(72 16 16)"/><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(144 16 16)"/><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(216 16 16)"/><ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(288 16 16)"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
];
/* Гарантии: фото → камера, свежесть → цветок, остальное → щит */
$trustIconKeys = ['camera', 'flower', 'shield'];

/* Компактная карточка «С этим берут» — как на витрине (бейджи + «+» в корзину) */
function render_related_card(array $rp): void
{
    $rPrice = productPrice($rp);
    $rSale = $rPrice !== (int)$rp['price'];
    $rHit = (int)($rp['is_hit'] ?? 0) === 1;
    $rPremium = (int)($rp['is_premium'] ?? 0) === 1;
    $rUrgent = (int)($rp['is_urgent'] ?? 0) === 1;
    $rFile = productImageFile($rp);
    $rImg = $rFile !== '' ? '/img/products/' . rawurlencode($rFile) : '';
    $rWebp = '';
    if ($rImg !== '') {
        $w = preg_replace('/\.(jpe?g|png)$/i', '.webp', urldecode($rImg));
        $rWebp = $w !== $rImg && is_file(BASE_PATH . $w) ? $w : '';
    }
    $rLink = '/product/' . rawurlencode($rp['slug']);
    /* W96-fix1 (F3): поисковый индекс карточки — как на витрине (имя + категория +
       описание, нижний регистр); категория приходит из SELECT * как NULL — ?? '' */
    $rSearch = mb_strtolower(trim($rp['name'] . ' ' . ($rp['category_name'] ?? '') . ' ' . ($rp['description'] ?? '')));
    ?>
        <article class="product-card" data-search="<?= e($rSearch) ?>">
          <div class="product-card__media">
            <a class="product-card__media-link" href="<?= e($rLink) ?>" aria-label="<?= e($rp['name']) ?>">
              <?php if ($rImg !== ''): ?>
                <picture>
                  <?php if ($rWebp !== ''): ?><source type="image/webp" srcset="<?= e($rWebp) ?>"><?php endif; ?>
                  <img class="product-card__img" src="<?= e($rImg) ?>" alt="<?= e($rp['name']) ?>" loading="lazy" decoding="async">
                </picture>
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
            </a>
            <?php if ($rSale): $rPct = (int)$rp['price'] > 0 ? (int)round((1 - $rPrice / (int)$rp['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= e(setting('badge_sale_text', 'Акционная цена')) ?><?php if ($rPct > 0): ?> −<?= $rPct ?>%<?php endif; ?></span><?php endif; ?>
            <?php if ($rUrgent): ?><span class="product-card__badge product-card__badge--urgent"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
            <?php if ($rHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($rPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$rp['id'] ?>"
              data-product-name="<?= e($rp['name']) ?>"
              data-product-price-raw="<?= $rPrice ?>"
              data-product-image="<?= e($rImg) ?>"
              aria-label="Добавить в корзину: <?= e($rp['name']) ?>" title="В корзину">+</button>
          </div>
          <div class="product-card__body">
            <a class="product-card__name" href="<?= e($rLink) ?>"><?= e($rp['name']) ?></a>
            <p class="product-card__price">
              <?php if ($rSale): ?>
                <span class="product-card__price--old"><?= formatPrice((int)$rp['price']) ?></span>
                <span class="product-card__price--discount"><?= formatPrice($rPrice) ?></span>
              <?php else: ?>
                <?= formatPrice($rPrice) ?>
              <?php endif; ?>
            </p>
          </div>
        </article>
    <?php
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<?php /* SEO-критик W86: og:type=product (уникальный) + twitter:title = имя товара, не бренд */ ?>
<?php $pageTitle = $product['name'] . ' — ' . setting('shop_name', 'Nilov Flowers'); $ogType = 'product'; ?>
<title><?= e($pageTitle) ?></title>
<meta property="og:title" content="<?= e($product['name']) ?> — <?= e(setting('shop_name', 'Nilov Flowers')) ?>">
<meta property="og:description" content="<?= e(mb_substr($product['description'] !== '' ? $product['description'] : $product['name'], 0, 200)) ?>">
<meta property="og:url" content="https://flowers.interfood-catering.ru/product/<?= e($product['slug']) ?>">
<meta property="og:type" content="product">
<?= $img !== '' ? '<meta property="og:image" content="https://flowers.interfood-catering.ru' . e($img) . '">' : '' ?>
<meta name="description" content="<?= e(mb_substr($product['description'] !== '' ? $product['description'] : $product['name'], 0, 160)) ?>">
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* JSON-LD Product+Offer — canonical 2026 (ecorn.agency structured-data-ecommerce) */ ?>
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
        'availability' => $product['is_urgent'] == 1 ? 'https://schema.org/InStock' : 'https://schema.org/InStock',
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
    /* SEO-критик W86: BreadcrumbList — SERP-фичер хлебных крошек */
    'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://flowers.interfood-catering.ru/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Каталог', 'item' => 'https://flowers.interfood-catering.ru/#catalog'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $product['name'], 'item' => 'https://flowers.interfood-catering.ru/product/' . rawurlencode($product['slug'])],
    ]],
] + ($product['sale_price'] !== null ? ['basePrice' => (int)$product['price']] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> / <a href="/#catalog">Каталог</a><?php if (!empty($product['category_name'])): ?> / <a href="/?category=<?= (int)$product['category_id'] ?>#catalog"><?= e($product['category_name']) ?></a><?php endif; ?> / <span aria-current="page"><?= e($product['name']) ?></span>
      </nav>

      <div class="fc-product">
        <div class="fc-product__gallery product-gallery">
          <?php /* Design-критик W47: честная multi-view галерея из ОДНОГО реального фото —
                     слайд 2 = крупный план того же снимка (CSS-zoom), не выдуманный ракурс.
                     Вторые настоящие фото — данные клиента (feature_gallery выключает всё). */ ?>
          <?php if ($img !== '' && setting('feature_gallery', '1') === '1'): ?>
          <?php /* W96-fix2 (F3): у товара ОДНА картинка — счётчик «1 / 2» и блок
                     миниатюр-превью (#productGalleryThumbs) не рендерим: две
                     маленьких копии одного файла выглядели как «несколько фото» —
                     обманка по отчёту дизайн-критика. Свайп/стрелки на честный
                     «Приближение этого же фото» остаются. js/product-gallery.js
                     корректно живёт без thumbs/counter (пустой NodeList + if(counter)).
                     Появятся реальные вторые фото — вернуть оба блока. */ ?>
          <div class="product-gallery__viewport" id="productGalleryTrack">
            <div class="product-gallery__track">
              <figure class="product-gallery__slide">
                <picture>
                  <?php if ($imgWebpOk): ?><source type="image/webp" srcset="<?= e($imgWebp) ?>"><?php endif; ?>
                  <?php /* W96-fix3a (T2b): LCP-снимок — явный eager + высокий приоритет
                     загрузки (loading-атрибута не было — работало eager по умолчанию,
                     но без приоритета браузер мог тянуть фото после стилей/скриптов) */ ?>
                  <img class="product-gallery__img" src="<?= e($img) ?>" alt="<?= e($product['name']) ?>" loading="eager" fetchpriority="high"<?= ($gDim = @getimagesize(IMG_PRODUCTS_DIR . '/' . productImageFile($product))) ? ' width="' . (int)$gDim[0] . '" height="' . (int)$gDim[1] . '"' : '' ?>>
                </picture>
                <figcaption class="product-gallery__cap">Общий план букета</figcaption>
              </figure>
              <figure class="product-gallery__slide">
                <div class="product-gallery__zoom" style="background-image:url('<?= e($img) ?>')" role="img" aria-label="<?= e($product['name']) ?> — крупный план"></div>
                <figcaption class="product-gallery__cap">Приближение этого же фото</figcaption>
              </figure>
            </div>
            <button type="button" class="product-gallery__nav product-gallery__nav--l" data-gnav="-1" aria-label="Предыдущий вид">‹</button>
            <button type="button" class="product-gallery__nav product-gallery__nav--r" data-gnav="1" aria-label="Следующий вид">›</button>
          </div>
          <?php else: ?>
          <div class="fc-product__media product-page__media" data-lightbox-trigger data-lightbox-src="<?= e($img) ?>" data-lightbox-alt="<?= e($product['name']) ?>">
            <?php if ($img !== ''): ?>
              <picture>
                <?php if ($imgWebpOk): ?><source type="image/webp" srcset="<?= e($imgWebp) ?>"><?php endif; ?>
                <?php /* W96-fix3a (T2b): LCP-снимок (вариант без галереи) — eager + приоритет */ ?>
                <img class="product-gallery__img" src="<?= e($img) ?>" alt="<?= e($product['name']) ?>" loading="eager" fetchpriority="high">
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
            <?php if ($isSale): ?><span class="product-card__badge product-card__badge--sale" style="position:static"><?= e(setting('badge_sale_text', 'Акционная цена')) ?><?php if ($offPct > 0): ?> −<?= $offPct ?>%<?php endif; ?></span><?php endif; ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit" style="position:static"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium" style="position:static"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <?php if ($isUrgent): ?><span class="product-card__badge product-card__badge--urgent" style="position:static"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
          </p>
          <?php endif; ?>
          <h1 class="fc-product__title"><?= e($product['name']) ?></h1>
          <p class="fc-product__price">
            <?php if ($isSale): ?>
              <span class="fc-product__price--old"><?= formatPrice((int)$product['price']) ?></span>
              <span class="fc-product__price--discount"><?= formatPrice($price) ?></span>
            <?php else: ?>
              <span><?= formatPrice($price) ?></span>
            <?php endif; ?>
          </p>
          <?php if ($isUrgent): ?><p class="product-card__urgent-note">Соберём и доставим в течение дня — количество ограничено</p><?php endif; ?>
          <?php if ($product['description'] !== ''): ?><div class="prose"><?= nl2br(e($product['description'])) ?></div><?php endif; ?>
          <button type="button" class="btn btn--accent fc-product__cta product-page__cta product-page__cta--sticky" data-order-cta
            data-product-id="<?= (int)$product['id'] ?>"
            data-product-name="<?= e($product['name']) ?>"
            data-product-price-raw="<?= $price ?>"
            data-product-image="<?= e($img) ?>"
            aria-label="Добавить в корзину: <?= e($product['name']) ?>">
            Добавить в корзину · <?= formatPrice($price) ?>
          </button>
          <span class="product-page__cta-spacer" aria-hidden="true"></span>
          <script>
          /* W62 (obvious-критик NEW-2): fixed-CTA на мобиле ложилась на legal-ссылки футера
             (elementFromPoint попадал в кнопку). Как только футер входит во вьюпорт —
             кнопка честно исчезает: покупателю она уже не нужна (страница дочитана). */
          document.addEventListener('DOMContentLoaded', function(){
            var cta=document.querySelector('.product-page__cta--sticky');
            var ft=document.querySelector('.site-footer');
            if(!cta||!ft||!window.IntersectionObserver) return;
            new IntersectionObserver(function(es){
              es.forEach(function(e){cta.classList.toggle('product-page__cta--hidden',e.isIntersecting);});
            },{threshold:0.02}).observe(ft);
          });
          </script>
          <?php /* Мета-блок 5cv: доставка / самовывоз / оплата + гарантии с иконками */ ?>
          <ul class="fc-product__meta">
            <?php /* W96-fix3b (D6): «доставим сегодня» — первая строка мета-блока.
               Текст — настройка product_today_text (владелец выключает пустой
               строкой: пустое ЗНАЧЕНИЕ скрывает пункт, отсутствующий ключ — дефолт) */ ?>
            <?php $todayText = trim(setting('product_today_text', 'Оформите до 20:00 — доставим сегодня')); ?>
            <?php if ($todayText !== ''): ?>
            <li><span class="fc-product__meta-icon"><?= $metaIcons['clock'] ?></span><span><?= e($todayText) ?></span></li>
            <?php endif; ?>
            <li><span class="fc-product__meta-icon"><?= $metaIcons['truck'] ?></span><span><?= e(setting('delivery_badge_text', 'Доставка по Санкт-Петербургу')) ?></span></li>
            <?php if ($pickupAddr !== ''): ?><li><span class="fc-product__meta-icon"><?= $metaIcons['map'] ?></span><span>Самовывоз: <?= e($pickupAddr) ?></span></li><?php endif; ?>
            <li><span class="fc-product__meta-icon"><?= $metaIcons['card'] ?></span><span><?= $ykOn ? 'Оплата — картой, СБП или при получении. На защищённой странице платёжного провайдера.' : 'Оплата — курьеру при получении заказа.' ?></span></li>
            <?php foreach ($trust as $ti => $t): ?>
            <li><span class="fc-product__meta-icon"><?= $metaIcons[$trustIconKeys[$ti % 3]] ?></span><span><?= e($t) ?></span></li>
            <?php endforeach; ?>
          </ul>
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
                 витрины (класс/подчёркивание те же); со страницы товара — в каталог */ ?>
          <a class="fc-row__link" href="/#catalog">Смотреть все</a>
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
