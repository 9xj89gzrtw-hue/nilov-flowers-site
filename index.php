<?php
/* Главная витрина: hero + trust strip + каталог с вкладками + как работаем + форма заказа */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$pdo = db();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort, id')->fetchAll();
$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.sort, p.id')->fetchAll();
$zones = $pdo->query('SELECT id, name, price FROM delivery_zones ORDER BY sort, id')->fetchAll();

/* Внешний вид: hero-текст можно отключить, отзывы Яндекса — показать по ID */
$heroTextEnabled = setting('hero_text_enabled', '1') === '1';
$yandexReviewsId = trim(setting('yandex_reviews_id', ''));
$yandexReviewsEnabled = setting('yandex_reviews_enabled', '0') === '1' && $yandexReviewsId !== '';
$heroBtnText = trim(setting('hero_button_text', ''));
$heroBtnLink = trim(setting('hero_button_link', ''));
$heroBtn = $heroTextEnabled && $heroBtnText !== '' && $heroBtnLink !== '';

/* Trust strip: гарантии — guarantee_1..N, либо настройки guarantees построчно */
$guarantees = [];
for ($i = 1; $i <= 6; $i++) {
    $g = setting('guarantee_' . $i);
    if ($g !== '') {
        $guarantees[] = $g;
    }
}
if ($guarantees === []) {
    $guarantees = array_values(array_filter(array_map('trim', explode("\n", setting('guarantees')))));
}

function product_img_url(array $p): string
{
    return $p['image'] !== '' ? '/img/products/' . rawurlencode($p['image']) : '';
}

/* Демо-фото: активным товарам без своего фото подставляем файлы из img/products,
   чтобы витрина не выглядела пустой. Свои фото (загруженные в админке) не трогаем. */
$demoImages = ['roz.jpg', 'p2.jpg', 'p3.jpg'];
$demoIdx = 0;
foreach ($products as &$pRow) {
    if ($pRow['is_active'] == 1 && $pRow['image'] === '') {
        $pRow['image'] = $demoImages[$demoIdx % count($demoImages)];
        $demoIdx++;
    }
}
unset($pRow);
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e(setting('shop_name', 'Nilov Flowers')) ?> — свежие цветы с доставкой в Санкт-Петербурге</title>
<meta name="description" content="<?= e(setting('shop_name', 'Nilov Flowers')) ?> — свежие букеты с утренней поставки, доставка по Санкт-Петербургу в день заказа. Полевая Сабировская ул., 47, корп. 1. Фото букета перед отправкой.">
<meta property="og:title" content="<?= e(setting('shop_name', 'Nilov Flowers')) ?> — свежие цветы с доставкой в СПб">
<meta property="og:description" content="Букеты с доставкой в день заказа по Санкт-Петербургу. Фото перед отправкой, свежие цветы с утренней поставки.">
<meta property="og:url" content="https://flowers.interfood-catering.ru/">
<?= setting('hero_image') !== '' ? '<meta property="og:image" content="https://flowers.interfood-catering.ru/img/uploads/' . e(rawurlencode(setting('hero_image'))) . '">' : '' ?>
<meta name="description" content="<?= e(setting('hero_subtitle')) ?>">
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* JSON-LD Florist — canonical 2026 (hanafloristpos.com/schema-guide, thestacc.com/local-business-schema) */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Florist',
    'name' => setting('shop_name', 'Nilov Flowers'),
    'url' => 'https://flowers.interfood-catering.ru/',
    'telephone' => setting('shop_phone', ''),
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => 'Полевая Сабировская ул., 47, корп. 1',
        'addressLocality' => 'Санкт-Петербург',
        'addressCountry' => 'RU',
    ],
    'addressString' => setting('shop_address', ''),
    'priceRange' => '₽₽',
    'image' => setting('hero_image', '') !== '' ? '/img/uploads/' . rawurlencode(setting('hero_image')) : '',
] + (setting('yandex_reviews_id') !== '' ? ['sameAs' => ['https://yandex.ru/maps/org/' . setting('yandex_reviews_id')]] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main>
  <!-- HERO: фото в арке, текст рядом -->
  <section class="hero">
    <div class="wrap hero__grid">
      <div>
        <?php if ($heroTextEnabled): ?>
        <h1 class="hero__hook"><?= e(setting('hero_title', 'Свежие цветы с утренней поставки')) ?></h1>
        <p class="hero__subtitle"><?= e(setting('hero_subtitle')) ?></p>
        <?php endif; ?>
        <?php if ($heroBtn || ($heroTextEnabled && $guarantees !== [])): ?>
        <div class="hero__meta">
          <?php if ($heroBtn): ?>
          <a class="btn btn--accent" href="<?= e(setting('hero_button_link', '#catalog')) ?>"><?= e($heroBtnText) ?></a>
          <?php endif; ?>
          <?php if ($heroTextEnabled && $guarantees !== []): ?>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><?= e($guarantees[0]) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="hero__media">
        <?php if (setting('hero_image') !== ''): ?>
          <img class="hero__img" src="/img/uploads/<?= e(setting('hero_image')) ?>" alt="<?= e(setting('hero_title')) ?>">
        <?php else: ?>
          <div class="hero__img" role="img" aria-label="Букет цветов">
            <svg viewBox="0 0 80 94" style="width:34%;margin:auto;color:#fff;opacity:.85" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- TRUST STRIP -->
  <?php if ($guarantees !== []): ?>
  <section class="trust-strip">
    <div class="wrap">
      <ul class="trust-strip__list">
        <?php foreach ($guarantees as $g): ?>
          <li class="trust-strip__item"><?= e($g) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- КАТАЛОГ -->
  <section class="section" id="catalog">
    <div class="wrap">
      <h2 class="section-title">Каталог</h2>
      <p class="section-sub">Соберём и доставим букет в день заказа</p>
      <div class="catalog-tabs" id="catalogTabs" role="tablist" aria-label="Категории">
        <button type="button" class="catalog-tabs__tab is-active" role="tab" aria-selected="true" data-category-id="all">Все</button>
        <?php foreach ($categories as $c): ?>
          <button type="button" class="catalog-tabs__tab" role="tab" aria-selected="false" data-category-id="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="catalog__grid" id="catalogGrid">
        <?php foreach ($products as $p):
            $price = productPrice($p);
            $isSale = $price !== (int)$p['price'];
            $img = product_img_url($p);
            $link = '/product/' . rawurlencode($p['slug']);
        ?>
        <article class="product-card reveal" data-category-id="<?= (int)($p['category_id'] ?? 0) ?>">
          <div class="product-card__media">
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>">
              <?php if ($img !== ''): ?>
                <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
            </a>
            <?php if ($isSale): ?><span class="product-card__badge">Скидка</span><?php endif; ?>
            <?php if ((int)($p['is_urgent'] ?? 0) === 1): ?><span class="product-card__badge product-card__badge--urgent">Успеть сегодня</span><?php endif; ?>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$p['id'] ?>"
              data-product-name="<?= e($p['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Добавить в корзину: <?= e($p['name']) ?>">+</button>
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
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- КАК ЭТО РАБОТАЕТ -->
  <?php if (setting('step_1') !== ''): ?>
  <section class="section how-it-works" id="how-it-works">
    <div class="wrap">
      <h2 class="section-title"><?= e(setting('steps_title', 'Как это работает')) ?></h2>
      <div class="how-it-works__grid">
        <?php foreach ([1, 2, 3] as $n):
            $step = setting('step_' . $n);
            if ($step === '') continue; ?>
          <div class="how-it-works__step reveal">
            <div class="how-it-works__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-4.6-7-10a4.5 4.5 0 0 1 7-3.7A4.5 4.5 0 0 1 19 11c0 5.4-7 10-7 10z"/></svg></div>
            <p class="how-it-works__num">Шаг <?= $n ?></p>
            <p class="how-it-works__title"><?= e($step) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ОТЗЫВЫ ЯНДЕКС КАРТ -->
  <?php if ($yandexReviewsEnabled): ?>
  <section class="section yandex-reviews">
    <div class="wrap">
      <h2 class="section-title">Отзывы о нас на Яндекс Картах</h2>
      <p class="section-sub">Покупатели оценивают нас на Яндекс Картах</p>
      <a class="btn btn--accent" href="https://yandex.ru/maps/org/<?= e(rawurlencode($yandexReviewsId)) ?>" target="_blank" rel="noopener">Читать отзывы</a>
    </div>
  </section>
  <?php endif; ?>

  <!-- ФОРМА ЗАКАЗА -->
  <section class="section order" id="order">
    <div class="wrap">
      <h2 class="section-title">Оформление заказа</h2>
      <p class="order__selected" id="orderSelected"></p>
      <form class="order-form" id="orderForm" novalidate>
        <div class="order-form__field">
          <label for="orderName">Ваше имя *</label>
          <input type="text" id="orderName" name="name" autocomplete="name" required minlength="2">
          <span class="order-form__error" id="orderNameError"></span>
        </div>
        <div class="order-form__field">
          <label for="orderPhone">Телефон</label>
          <input type="tel" id="orderPhone" name="phone" autocomplete="tel" placeholder="+7 (___) ___-__-__">
          <span class="order-form__error" id="orderPhoneError"></span>
        </div>
        <div class="order-form__field">
          <label for="orderEmail">Email</label>
          <input type="email" id="orderEmail" name="email" autocomplete="email" placeholder="you@example.com">
          <span class="order-form__hint" id="orderEmailHint" hidden>На этот адрес придёт чек об оплате</span>
          <span class="order-form__error" id="orderEmailError"></span>
        </div>
        <p class="order-form__hint">Укажите телефон и/или email — как удобнее для связи</p>
        <fieldset class="order-form__nested">
          <legend>Доставка</legend>
          <div class="order-form__field">
            <label for="orderDeliveryZone">Район доставки</label>
            <select id="orderDeliveryZone" name="delivery_zone">
              <option value="" disabled selected>Выберите район</option>
              <?php foreach ($zones as $z): ?>
                <option value="<?= (int)$z['id'] ?>" data-price="<?= (int)$z['price'] ?>"><?= e($z['name']) ?> — <?= formatPrice((int)$z['price']) ?></option>
              <?php endforeach; ?>
              <option value="0" data-price="0">Самовывоз — бесплатно</option>
            </select>
          </div>
          <div class="order-form__field" id="orderDeliveryAddressField" hidden>
            <label for="orderDeliveryAddress">Адрес доставки</label>
            <input type="text" id="orderDeliveryAddress" name="delivery_address" placeholder="Улица, дом, квартира">
            <span class="order-form__error" id="orderDeliveryAddressError"></span>
          </div>
          <p class="order-form__hint">Доставим в течение дня, время согласуем по телефону</p>
        </fieldset>
        <fieldset class="order-form__payment">
          <legend>Способ оплаты</legend>
          <?php if (setting('yk_enabled', '0') === '1'): ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="online" checked><span>Картой или через СБП — сразу онлайн</span></label>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash"><span>При получении</span></label>
          <?php else: ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash" checked><span>При получении</span></label>
          <input type="hidden" name="payment_method" value="cash">
          <?php endif; ?>
        </fieldset>
        <div class="order-form__field">
          <label for="orderComment">Комментарий</label>
          <textarea id="orderComment" name="comment" rows="3" placeholder="Открытка с текстом, цветовые пожелания, время доставки"></textarea>
        </div>
        <label class="order-form__checkbox">
          <input type="checkbox" id="orderPdConsent" name="pd_consent" required>
          <span>Я даю согласие на обработку персональных данных *</span>
        </label>
        <span class="order-form__error" id="orderPdConsentError"></span>
        <p class="order-form__total" id="orderTotal"></p>
        <button type="submit" class="btn btn--accent order-form__submit" id="orderSubmit">Оплатить заказ</button>
        <p class="order-form__status" id="orderStatus" role="status" hidden></p>
      </form>
    </div>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
