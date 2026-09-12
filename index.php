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

/* Функции витрины (критерий 16): каждый блок отключаем из админки */
$featDeliveryBadge = setting('feature_delivery_badge', '1') === '1';
$featFaq = setting('feature_faq', '1') === '1';
$featCountdown = setting('feature_countdown', '1') === '1';
$featPriceFilter = setting('feature_price_filter', '1') === '1';
$featFavorites = setting('feature_favorites', '1') === '1';
$featZoneCheck = setting('feature_zone_check', '1') === '1';

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

/* WebP-вариант того же фото (если сгенерирован рядом: name.jpg → name.webp).
   Идемпотентно: файла нет — вернём пустую строку и <source> не напечатаем. */
function product_img_webp(array $p): string
{
    if ($p['image'] === '') return '';
    $webp = '/img/products/' . rawurlencode(preg_replace('/\.(jpe?g|png)$/i', '.webp', $p['image']));
    return is_file(BASE_PATH . urldecode($webp)) ? $webp : '';
}

/* Демо-фото: активным товарам без своего фото подставляем файлы из img/products,
   чтобы витрина не выглядела пустой. Свои фото (загруженные в админке) не трогаем. */
$canonicalUrl = 'https://flowers.interfood-catering.ru/';
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
<title>Доставка цветов в СПб — <?= e(setting('shop_name', 'Nilov Flowers')) ?> | Свежие букеты с доставкой сегодня</title>
<meta name="description" content="Доставка букетов по Санкт-Петербургу в день заказа. Свежие цветы с утренней поставки, фото перед отправкой, бесплатная доставка по Приморскому району. Заказы до 20:00 — доставим сегодня.">
<meta property="og:title" content="<?= e(setting('shop_name', 'Nilov Flowers')) ?> — свежие цветы с доставкой в СПб">
<meta property="og:description" content="Букеты с доставкой в день заказа по Санкт-Петербургу. Фото перед отправкой, свежие цветы с утренней поставки.">
<meta property="og:url" content="https://flowers.interfood-catering.ru/">
<?= setting('hero_image') !== '' ? '<meta property="og:image" content="https://flowers.interfood-catering.ru/img/uploads/' . e(rawurlencode(setting('hero_image'))) . '">' : '' ?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* JSON-LD Florist — canonical 2026 (hanafloristpos.com/schema-guide, thestacc.com/local-business-schema) */ ?>
<?php
/* LCP-preload: hero.webp если существует (фолбэк — jpg) */
$__heroPre = setting('hero_image');
if ($__heroPre !== '') {
    $__heroWebp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $__heroPre);
    $__heroPreHref = ($__heroWebp !== $__heroPre && is_file(IMG_UPLOADS_DIR . '/' . $__heroWebp))
        ? '/img/uploads/' . rawurlencode($__heroWebp)
        : '/img/uploads/' . rawurlencode($__heroPre);
    echo '<link rel="preload" as="image" href="' . e($__heroPreHref) . '" fetchpriority="high">' . "\n";
}
?>
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
    'geo' => [
        '@type' => 'GeoCoordinates',
        'latitude' => 59.9970675,
        'longitude' => 30.2727226,
    ],
    'openingHoursSpecification' => [[
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        'opens' => '09:00',
        'closes' => '21:00',
    ]],
    'image' => setting('hero_image', '') !== '' ? '/img/uploads/' . rawurlencode(setting('hero_image')) : '',
] + (setting('yandex_reviews_id') !== '' ? ['sameAs' => ['https://yandex.ru/maps/org/' . setting('yandex_reviews_id')]] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main>
  <!-- HERO: Nilov Flowers wow-заголовок + фото в арке -->
  <section class="hero">
    <div class="wrap hero__grid">
      <div>
        <?php if ($heroTextEnabled): ?>
        <p class="nv-hero-eyebrow nv-hero-sub">Санкт-Петербург · доставка в день заказа</p>
        <h1 class="nv-hero-brand" id="nvBrand" aria-label="Nilov Flowers">
          <?php
          /* По-буквенный stagger (spec H1): «Nilov» прямой, «Flow» розовым курсивом + «ers».
             Буквы — статический PHP-split, JS не нужен; aria-label сохраняет доступность. */
          $brandLines = [
              ['text' => 'Nilov', 'accent' => 0],
              ['text' => 'Flowers', 'accent' => 7], // всё слово — мягкий розовый акцент, без курсива
          ];
          $gi = 0; // глобальный индекс буквы — каскад идёт по буквам, а не по строкам
          foreach ($brandLines as $line):
          ?>
          <span class="nv-line">
            <?php foreach (mb_str_split($line['text']) as $ci => $ch): ?>
              <span class="nv-ch" style="--i:<?= $gi++ ?><?= $ci < $line['accent'] ? ';color:var(--rose-deep,#E2799C)' : '' ?>"><?= e($ch) ?></span>
            <?php endforeach; ?>
          </span>
          <?php endforeach; ?>
        </h1>
        <p class="hero__hook nv-hero-sub" data-hero-hook><?= e(setting('hero_title', 'Свежие цветы с утренней поставки')) ?></p>
        <p class="hero__subtitle nv-hero-sub"><?= e(setting('hero_subtitle')) ?></p>
        <?php endif; ?>
        <?php /* Таймер «до 20:00» — не зависит от hero-текста (юр-независимый элемент). Отключаем (критерий 16). */ ?>
        <?php if ($featCountdown): ?><p class="hero__deadline" style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:8px 16px;border-radius:999px;background:rgba(255,255,255,.75);backdrop-filter:blur(6px);border:1px solid var(--line);font-size:.9rem;font-weight:600;color:var(--ink)"></p><?php endif; ?>
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
        <div class="nv-petals" aria-hidden="true">
          <svg class="nv-petal--a" viewBox="0 0 100 100" fill="currentColor"><path d="M50 4C66 22 82 40 82 60c0 20-14 36-32 36S18 80 18 60C18 40 34 22 50 4z" opacity=".55"/><path d="M50 4c-16 18-32 36-32 56 0 20 14 36 32 36" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
          <svg class="nv-petal--b" viewBox="0 0 100 100" fill="currentColor"><path d="M50 4C66 22 82 40 82 60c0 20-14 36-32 36S18 80 18 60C18 40 34 22 50 4z" opacity=".55"/><path d="M50 4c-16 18-32 36-32 56 0 20 14 36 32 36" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
          <svg viewBox="0 0 100 100" fill="currentColor"><path d="M50 4C66 22 82 40 82 60c0 20-14 36-32 36S18 80 18 60C18 40 34 22 50 4z" opacity=".55"/><path d="M50 4c-16 18-32 36-32 56 0 20 14 36 32 36" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
        </div>
        <?php if (setting('hero_image') !== ''): ?>
          <?php
          /* Hero в WebP если есть (LCP-критично: 163KB webp vs 509KB jpg), фолбэк jpg */
          $heroImg = setting('hero_image');
          $heroWebp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $heroImg);
          $heroWebpOk = $heroWebp !== $heroImg && is_file(IMG_UPLOADS_DIR . '/' . $heroWebp);
          ?>
          <picture>
            <?php if ($heroWebpOk): ?><source type="image/webp" srcset="/img/uploads/<?= e(rawurlencode($heroWebp)) ?>"><?php endif; ?>
            <img class="hero__img" src="/img/uploads/<?= e($heroImg) ?>" alt="<?= e(setting('hero_title')) ?>" fetchpriority="high">
          </picture>
        <?php else: ?>
          <div class="hero__img" role="img" aria-label="Букет цветов">
            <svg viewBox="0 0 80 94" style="width:34%;margin:auto;color:#fff;opacity:.85" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- MARQUEE: доставка по СПб (CSS-only, дублируемая лента aria-hidden) -->
  <div class="nv-marquee" aria-hidden="true">
    <div class="nv-marquee__track">
      <?php for ($mi = 0; $mi < 2; $mi++): ?>
      <span>Доставка по Санкт-Петербургу в день заказа</span><span class="nv-marquee__dot">✿</span>
      <span>Свежие цветы с утренней поставки</span><span class="nv-marquee__dot">✿</span>
      <span>Фото букета перед отправкой</span><span class="nv-marquee__dot">✿</span>
      <span>Заменяем увядшие в день доставки</span><span class="nv-marquee__dot">✿</span>
      <?php endfor; ?>
    </div>
  </div>

  <!-- TRUST STRIP: скрыт на главной (дублирует marquee выше), живёт на product.php -->
  <?php if (false && $guarantees !== []): ?>
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
      <?php /* Фильтр по цене (критерий 13, EXPRESS-паттерн): JS-фильтрация карточек по data-атрибуту. Отключаем (критерий 16). */ ?>
      <?php if ($featPriceFilter): ?>
      <div class="catalog-price-filter" style="display:flex;align-items:center;gap:10px;margin:0 0 18px;flex-wrap:wrap">
        <label for="priceFilter" style="font-size:.85rem;font-weight:600;color:var(--ink-soft)">Цена:</label>
        <select id="priceFilter" style="padding:8px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;font-size:.85rem;cursor:pointer">
          <option value="all" selected>Любая</option>
          <option value="u2500">до 2 500 ₽</option>
          <option value="2500-4000">2 500–4 000 ₽</option>
          <option value="o4000">от 4 000 ₽</option>
        </select>
        <span id="priceFilterCount" style="font-size:.85rem;color:var(--ink-soft)" aria-live="polite"></span>
      </div>
      <?php endif; ?>
        <?php if ($featFavorites): ?><button type="button" id="favToggle" class="fav-toggle" aria-pressed="false">♡ Избранное</button><?php endif; ?>
        <?php /* Проверка зоны доставки (критерий 13, Семицветик-паттерн): тариф района до чекаута.
               Данные зон инлайн (HTML-атрибут) — JS-мэтч по вводу покупателя. Отключаем (критерий 16). */ ?>
        <?php if ($featZoneCheck): ?>
        <span class="zone-check" style="display:inline-flex;align-items:center;gap:6px;margin-left:auto">
          <input type="search" id="zoneCheckInput" placeholder="Мой район доставки…" aria-label="Проверить зону доставки"
                 style="padding:8px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;font-size:.85rem;width:170px"
                 list="zoneCheckList">
          <datalist id="zoneCheckList">
            <?php foreach ($zones as $z): ?><option value="<?= e($z['name']) ?>"></option><?php endforeach; ?>
          </datalist>
          <span id="zoneCheckResult" style="font-size:.85rem;font-weight:600" aria-live="polite"></span>
        </span>
        <?php endif; ?>
      </div>
      <div class="catalog__grid" id="catalogGrid">
        <?php foreach ($products as $p):
            $price = productPrice($p);
            $isSale = $price !== (int)($p['price'] ?? $p['price']);
            $img = product_img_url($p);
            $imgWebp = product_img_webp($p);
            $link = '/product/' . rawurlencode($p['slug']);
        ?>
        <article class="product-card reveal" data-category-id="<?= (int)($p['category_id'] ?? 0) ?>" data-price="<?= (int)$price ?>">
          <div class="product-card__media">
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>">
              <?php if ($img !== ''): ?>
                <picture>
                  <?php if ($imgWebp !== ''): ?><source type="image/webp" srcset="<?= e($imgWebp) ?>"><?php endif; ?>
                  <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
                </picture>
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
            </a>
            <?php if ($isSale): ?><span class="product-card__badge">Скидка до конца недели</span><?php endif; ?>
            <?php if ((int)($p['is_urgent'] ?? 0) === 1): ?><span class="product-card__badge product-card__badge--urgent">Успеть сегодня</span><?php endif; ?>
            <?php /* Конкурентный бейдж (критерий 13, EXPRESS-паттерн): тариф зоны владельца на каждой карточке. Отключаем (критерий 16). */ ?>
            <?php if ($featDeliveryBadge): ?><span class="product-card__badge product-card__badge--deliv">Доставка 0₽ · Приморский</span><?php endif; ?>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$p['id'] ?>"
              data-product-name="<?= e($p['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="В корзину">+</button>
            <?php /* Избранное (критерий 13, Русский Букет-паттерн): сердечко на карточке, localStorage. Отключаем (критерий 16). */ ?>
            <?php if ($featFavorites): ?><button type="button" class="product-card__fav" data-fav-id="<?= (int)$p['id'] ?>" data-fav-name="<?= e($p['name']) ?>" aria-label="В избранное: <?= e($p['name']) ?>" title="В избранное">♡</button><?php endif; ?>
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
        <?php endforeach; ?>
      </div>
      <?php /* Empty-state (критик P2): при 0 карточек от фильтров — подсказка + сброс */ ?>
      <div class="catalog-empty" id="catalogEmpty" hidden style="text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px;margin-top:14px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px">По этим фильтрам букетов не нашлось 🌷</p>
        <p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px">Попробуйте убрать фильтр цены или выбрать другую категорию</p>
        <button type="button" class="btn btn--outline" id="catalogEmptyReset">Сбросить фильтры</button>
      </div>
    </div>
  </section>

  <!-- КАК ЭТО РАБОТАЕТ -->
  <?php if (setting('step_1') !== ''): ?>
  <section class="section how-it-works" id="how-it-works">
    <div class="wrap">
      <h2 class="section-title"><?= e(setting('steps_title', 'Как это работает')) ?></h2>
      <?php /* Цветок-блум: раскрывается по скроллу (CSS animation-timeline: view(), Chrome 115+;
               без поддержки и при reduced-motion — просто статичный цветок. aria-hidden: декор. */ ?>
      <svg class="nv-bloom" viewBox="0 0 200 120" fill="none" aria-hidden="true">
        <g class="nv-bloom-petals">
          <ellipse cx="100" cy="58" rx="22" ry="44" fill="currentColor" opacity=".32" transform="rotate(-38 100 88)"/>
          <ellipse cx="100" cy="58" rx="22" ry="44" fill="currentColor" opacity=".32" transform="rotate(-14 100 88)"/>
          <ellipse cx="100" cy="58" rx="22" ry="44" fill="currentColor" opacity=".32" transform="rotate(14 100 88)"/>
          <ellipse cx="100" cy="58" rx="22" ry="44" fill="currentColor" opacity=".32" transform="rotate(38 100 88)"/>
        </g>
        <g class="nv-bloom-core">
          <circle cx="100" cy="74" r="15" fill="currentColor" opacity=".55"/>
          <path d="M100 96c-14-8-30-18-30-32 0-8 5-14 12-16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
        </g>
      </svg>
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
          <label for="orderEmail">Email <span id="orderEmailReq" style="color:var(--rose-deep,#E2799C);font-weight:600" hidden>* обязательно для онлайн-оплаты</span></label>
          <input type="email" id="orderEmail" name="email" autocomplete="email" placeholder="you@example.com">
          <span class="order-form__hint" id="orderEmailHint" hidden>На этот адрес придёт чек об оплате</span>
          <span class="order-form__error" id="orderEmailError"></span>
        </div>
        <p class="order-form__hint" id="contactHint">Укажите телефон и/или email — как удобнее для связи</p>
        <fieldset class="order-form__nested">
          <legend>Доставка</legend>
          <div class="order-form__field">
            <label for="orderDeliveryZone">Как получить букет</label>
            <select id="orderDeliveryZone" name="delivery_zone">
              <?php
              /* Самовывоз — приоритетный способ: выбран по умолчанию, бесплатно.
                 Адрес самовывоза = адрес магазина из настроек. */
              $pickupAddr = trim(setting('shop_address', ''));
              ?>
              <option value="0" data-price="0" selected>Самовывоз — бесплатно</option>
              <?php foreach ($zones as $z): ?>
                <option value="<?= (int)$z['id'] ?>" data-price="<?= (int)$z['price'] ?>">Доставка: <?= e($z['name']) ?> — <?= formatPrice((int)$z['price']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($pickupAddr !== ''): ?>
            <p class="order-form__hint" id="orderPickupAddr" style="margin-top:6px">Адрес самовывоза: <?= e($pickupAddr) ?> · букет будет готов в течение дня, предупредим по телефону</p>
            <?php endif; ?>
          </div>
          <div class="order-form__field" id="orderDeliveryAddressField" hidden>
            <label for="orderDeliveryAddress">Адрес доставки</label>
            <input type="text" id="orderDeliveryAddress" name="delivery_address" placeholder="Улица, дом, квартира">
            <span class="order-form__error" id="orderDeliveryAddressError"></span>
          </div>
          <p class="order-form__hint" id="orderDeliveryHint">Доставим в течение дня, время согласуем по телефону</p>
        </fieldset>
        <fieldset class="order-form__payment">
          <legend>Способ оплаты</legend>
          <?php
          /* Fail-safe: «онлайн» показываем только если ЮKassa реально настроена
             (галочка + ключи). Иначе покупатель увидит несбыточное обещание. */
          $ykLive = setting('yk_enabled', '0') === '1'
              && trim(setting('yk_shop_id', '')) !== ''
              && trim(setting('yk_secret_key', '')) !== '';
          ?>
          <?php if ($ykLive): ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="online" checked><span>Картой или через СБП — сразу онлайн</span></label>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash"><span>При получении</span></label>
          <p class="order-form__hint">Оплата проходит на защищённой странице ЮKassa. Данные карты магазину не передаются.</p>
          <?php else: ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash" checked><span>При получении</span></label>
          <input type="hidden" name="payment_method" value="cash">
          <p class="order-form__hint">Оплата — курьеру при получении заказа.</p>
          <?php endif; ?>
        </fieldset>
        <div class="order-form__field">
          <label for="orderComment">Комментарий</label>
          <textarea id="orderComment" name="comment" rows="3" placeholder="Открытка с текстом, цветовые пожелания, время доставки"></textarea>
        </div>
        <label class="order-form__checkbox">
          <input type="checkbox" id="orderPdConsent" name="pd_consent" required>
          <span>Я даю согласие на обработку персональных данных (ФИО, телефон, адрес) в целях оформления и доставки заказа на условиях <a href="/policy" target="_blank" rel="noopener">Политики конфиденциальности</a> и <a href="/offer" target="_blank" rel="noopener">Публичной оферты</a> *</span>
        </label>
        <span class="order-form__error" id="orderPdConsentError"></span>
        <p class="order-form__total" id="orderTotal"></p>
        <button type="submit" class="btn btn--accent order-form__submit" id="orderSubmit">Оплатить заказ</button>
        <p class="order-form__hint">Заказы принимаем ежедневно до 20:00 — оформленные сегодня доставим сегодня же.</p>
        <p class="order-form__status" id="orderStatus" role="status" hidden></p>
      </form>
    </div>
  </section>

  <?php /* FAQ (критерий 13, SEO FAQPage — паттерн Цветовика): реальные вопросы покупателей. Отключаем (критерий 16). */ ?>
  <?php if ($featFaq): ?>
  <section class="section" id="faq" style="padding-top:0">
    <div class="wrap" style="max-width:720px">
      <h2 class="section-title">Частые вопросы</h2>
      <details style="border:1px solid var(--line);border-radius:14px;padding:14px 18px;margin-bottom:10px;background:#fff">
        <summary style="font-weight:600;cursor:pointer">Сколько стоит доставка?</summary>
        <p style="margin-top:8px;color:var(--ink-soft);font-size:.92rem">По Приморскому району — бесплатно, это наш район. Центр СПб — 300 ₽, остальные районы 350–600 ₽. Самовывоз с Полевой Сабировской, 47 — всегда бесплатно.</p>
      </details>
      <details style="border:1px solid var(--line);border-radius:14px;padding:14px 18px;margin-bottom:10px;background:#fff">
        <summary style="font-weight:600;cursor:pointer">Успею ли заказать сегодня?</summary>
        <p style="margin-top:8px;color:var(--ink-soft);font-size:.92rem">Да — заказы до 20:00 доставим в тот же день. После 20:00 доставим на следующее утро.</p>
      </details>
      <details style="border:1px solid var(--line);border-radius:14px;padding:14px 18px;margin-bottom:10px;background:#fff">
        <summary style="font-weight:600;cursor:pointer">Как понять, что пришёл именно мой букет?</summary>
        <p style="margin-top:8px;color:var(--ink-soft);font-size:.92rem">Перед отправкой курьером пришлём фото собранного букета. Не понравится вживую — заменим в день доставки, без вопросов.</p>
      </details>
      <details style="border:1px solid var(--line);border-radius:14px;padding:14px 18px;background:#fff">
        <summary style="font-weight:600;cursor:pointer">Как оплатить?</summary>
        <p style="margin-top:8px;color:var(--ink-soft);font-size:.92rem">При получении — курьеру. Онлайн-оплата картой подключается (ЮKassa).</p>
      </details>
      <script type="application/ld+json">
      <?= json_encode([
          '@context' => 'https://schema.org',
          '@type' => 'FAQPage',
          'mainEntity' => [
              ['@type' => 'Question', 'name' => 'Сколько стоит доставка?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'По Приморскому району — бесплатно. Центр СПб — 300 ₽, остальные районы 350–600 ₽. Самовывоз — всегда бесплатно.']],
              ['@type' => 'Question', 'name' => 'Успею ли заказать сегодня?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Заказы до 20:00 доставим в тот же день.']],
              ['@type' => 'Question', 'name' => 'Как понять, что пришёл именно мой букет?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Перед отправкой пришлём фото собранного букета. Не понравится — заменим в день доставки.']],
              ['@type' => 'Question', 'name' => 'Как оплатить?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'При получении курьеру; онлайн-оплата подключается.']],
          ],
      ], JSON_UNESCAPED_UNICODE) ?>
      </script>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
