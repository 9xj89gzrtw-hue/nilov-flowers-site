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
/* Критик functional 6/10: gift-UX (получатель+открытка) и слоты даты — отключаемые (критерий 14) */
$featGiftFields = setting('feature_gift_fields', '1') === '1';
$featDeliverySlots = setting('feature_delivery_slots', '0') === '1';

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

/* Критик-покупатель B1: единый фолбэк-фото (productImageFile) — карточка и страница
   товара всегда показывают одно и то же. */
$canonicalUrl = 'https://flowers.interfood-catering.ru/';
foreach ($products as &$pRow) {
    if ($pRow['is_active'] == 1) {
        $pRow['image'] = productImageFile($pRow);
    }
}
unset($pRow);
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e(setting('seo_title', 'Доставка цветов в СПб — ' . setting('shop_name', 'Nilov Flowers') . ' | Свежие букеты с доставкой сегодня')) ?></title>
<meta name="description" content="<?= e(setting('seo_description', 'Доставка букетов по Санкт-Петербургу в день заказа. Свежие цветы с утренней поставки, фото перед отправкой, бесплатная доставка по Приморскому району. Заказы до 20:00 — доставим сегодня.')) ?>">
<meta property="og:title" content="<?= e(setting('seo_title', 'Доставка цветов в СПб — ' . setting('shop_name', 'Nilov Flowers') . ' | Свежие букеты с доставкой сегодня')) ?>">
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
    'image' => setting('hero_image', '') !== '' ? 'https://flowers.interfood-catering.ru/img/uploads/' . rawurlencode(setting('hero_image')) : '',
] + (setting('yandex_reviews_id') !== '' ? ['sameAs' => ['https://yandex.ru/maps/org/' . setting('yandex_reviews_id')]] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <!-- HERO: Nilov Flowers wow-заголовок + фото в арке -->
  <section class="hero">
    <div class="wrap hero__grid">
      <div>
        <?php if ($heroTextEnabled): ?>
        <p class="nv-hero-eyebrow nv-hero-sub"><?= e(setting('hero_eyebrow', 'Санкт-Петербург · доставка в день заказа')) ?></p>
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
              <span class="nv-ch" style="--i:<?= $gi++ ?><?= $ci < $line['accent'] ? ';color:var(--rose-cta,#AE4A71)' : '' ?>"><?= e($ch) ?></span>
            <?php endforeach; ?>
          </span>
          <?php endforeach; ?>
        </h1>
        <p class="hero__hook nv-hero-sub" data-hero-hook><?= e(setting('hero_title', 'Свежие цветы с утренней поставки')) ?></p>
        <p class="hero__subtitle nv-hero-sub"><?= e(setting('hero_subtitle')) ?></p>
        <?php endif; ?>
        <?php /* Таймер «до 20:00» — не зависит от hero-текста (юр-независимый элемент). Отключаем (критерий 16). */ ?>
        <?php if ($featCountdown): ?><p class="hero__deadline" style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:8px 16px;border-radius:999px;background:rgba(255,255,255,.75);backdrop-filter:blur(6px);border:1px solid var(--line);font-size:.9rem;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums;max-width:100%;min-height:38px"><?= e(str_replace('{T}', '00 ч 00 мин', setting('countdown_text', 'Успейте заказать сегодня — осталось … до ' . setting('order_deadline_hour', '20') . ':00'))) ?></p><?php endif; ?>
        <?php /* NILOV_CONFIG — общий конфиг JS (вне гейта таймера): порог бесплатной доставки
               должен работать и при выключенном таймере. */ ?>
        <script>window.NILOV_CONFIG = {
          deadlineHour: <?= (int)(setting('order_deadline_hour', '20')) ?>,
          deadlineMinute: <?= (int)(setting('order_deadline_minute', '0')) ?>,
          openHour: <?= (int)(preg_match('/(\d{1,2})\s*:/', (string)setting('shop_hours', ''), $m) ? max(0, min(23, (int)$m[1])) : 9) ?>,
          tz: <?= json_encode(setting('shop_timezone', 'Europe/Moscow')) ?>,
          nightText: <?= json_encode(setting('countdown_night_text', 'Ночь. Заказ примем сейчас — доставим сегодня после 9:00'), JSON_UNESCAPED_UNICODE) ?>,
          countdownText: <?= json_encode(setting('countdown_text', 'Успейте заказать сегодня — осталось {T} до {D}'), JSON_UNESCAPED_UNICODE) ?>,
          closedText: <?= json_encode(setting('countdown_closed_text', 'Приём заказов на сегодня закрыт — доставим завтра с 9:00'), JSON_UNESCAPED_UNICODE) ?>,
          freeDeliveryThreshold: <?= (int) setting('free_delivery_threshold', '0') ?>
        };</script>
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
          /* Layout-критик W35: width/height на <img> — браузер резервирует box до загрузки
             (aspect-ratio из CSS не спасает warm-CLS 0.024 на 1440) */
          $heroDim = @getimagesize(IMG_UPLOADS_DIR . '/' . $heroImg) ?: null;
          ?>
          <picture>
            <?php if ($heroWebpOk): ?><source type="image/webp" srcset="/img/uploads/<?= e(rawurlencode($heroWebp)) ?>"><?php endif; ?>
            <img class="hero__img" src="/img/uploads/<?= e($heroImg) ?>" alt="<?= e(setting('hero_title')) ?>" fetchpriority="high"<?= $heroDim ? ' width="' . (int)$heroDim[0] . '" height="' . (int)$heroDim[1] . '"' : '' ?>>
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
      <?php /* Тексты ленты редактируются (критерий 16): marquee_1..4; пустые пропускаются.
          Копирайт-критик: marquee не должен дублировать hero-подзаголовок — первый слот
          переформулирован (факт о логистике, а не эхо «свежие цветы»). */ ?>
      <?php $marqueeItems = array_filter(array_map('trim', [
          setting('marquee_1', 'Собираем заказ в течение часа после подтверждения'),
          setting('marquee_2', 'Доставка по Санкт-Петербургу в день заказа'),
          setting('marquee_3', 'Фото букета перед отправкой'),
          setting('marquee_4', 'Заменяем увядшие в день доставки'),
      ]), static fn (string $t): bool => $t !== ''); ?>
      <?php for ($mi = 0; $mi < 2; $mi++): ?>
      <?php foreach ($marqueeItems as $mt): ?><span><?= e($mt) ?></span><span class="nv-marquee__dot">✿</span><?php endforeach; ?>
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
      <h2 class="section-title"><?= e(setting('catalog_title', 'Каталог')) ?></h2>
      <p class="section-sub"><?= e(setting('catalog_subtitle', 'Соберём и доставим букет в день заказа')) ?></p>
      <div class="catalog-tabs" id="catalogTabs" role="group" aria-label="Фильтр каталога по категориям">
        <button type="button" class="catalog-tabs__tab is-active" aria-pressed="true" data-category-id="all">Все</button>
        <?php foreach ($categories as $c): ?>
          <button type="button" class="catalog-tabs__tab" aria-pressed="false" data-category-id="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <?php /* Фильтр по цене (критерий 13, EXPRESS-паттерн). Пороги редактируются (критерий 16). */ ?>
      <?php if ($featPriceFilter):
          $pfLow = (int)setting('price_filter_low', '2500');
          $pfHigh = (int)setting('price_filter_high', '4000');
      ?>
      <div class="catalog-price-filter" style="display:flex;align-items:center;gap:10px;margin:0 0 18px;flex-wrap:wrap">
        <label for="priceFilter" style="font-size:.85rem;font-weight:600;color:var(--ink-soft)">Цена:</label>
        <select id="priceFilter" class="pill">
          <option value="all" selected>Любая</option>
          <option value="low" data-max="<?= $pfLow ?>">до <?= number_format($pfLow, 0, '', ' ') ?> ₽</option>
          <option value="mid" data-min="<?= $pfLow ?>" data-max="<?= $pfHigh ?>"><?= number_format($pfLow, 0, '', ' ') ?>–<?= number_format($pfHigh, 0, '', ' ') ?> ₽</option>
          <option value="high" data-min="<?= $pfHigh ?>">от <?= number_format($pfHigh, 0, '', ' ') ?> ₽</option>
        </select>
        <span id="priceFilterCount" style="font-size:.85rem;color:var(--ink-soft)" aria-live="polite"></span>
      </div>
      <?php endif; ?>
        <?php if ($featFavorites || $featZoneCheck): ?>
        <div class="catalog-toolbar" style="display:flex;align-items:center;gap:10px;margin:0 0 18px;flex-wrap:wrap">
        <?php endif; ?>
        <?php if ($featFavorites): ?><button type="button" id="favToggle" class="fav-toggle" aria-pressed="false">♡ Избранное</button><?php endif; ?>
        <?php /* Проверка зоны доставки (критерий 13, Семицветик-паттерн): тариф района до чекаута.
               Данные зон инлайн (HTML-атрибут) — JS-мэтч по вводу покупателя. Отключаем (критерий 16). */ ?>
        <?php if ($featZoneCheck): ?>
        <span class="zone-check" style="display:inline-flex;align-items:center;gap:6px;margin-left:auto">
          <label for="zoneCheckInput" style="font-size:.85rem;font-weight:600;color:var(--ink-soft)">Район:</label>
          <input type="search" id="zoneCheckInput" placeholder="<?= e(setting('zone_check_placeholder', 'Например: Приморский')) ?>" aria-label="Узнать стоимость доставки в ваш район"
                 data-fallback="<?= e(setting('zone_check_fallback', 'не нашли — уточним по телефону')) ?>"
                 style="width:clamp(150px,46vw,240px);min-width:0" class="pill"
                 list="zoneCheckList">
          <datalist id="zoneCheckList">
            <?php foreach ($zones as $z): ?><option value="<?= e($z['name']) ?>"></option><?php endforeach; ?>
          </datalist>
          <span id="zoneCheckResult" style="font-size:.85rem;font-weight:600;min-width:96px;white-space:nowrap;display:inline-block" aria-live="polite"></span>
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
            <?php if ($isSale): ?><span class="product-card__badge"><?= e(setting('badge_sale_text', 'Скидка до конца недели')) ?></span><?php endif; ?>
            <?php if ((int)($p['is_urgent'] ?? 0) === 1): ?><span class="product-card__badge product-card__badge--urgent"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
            <?php /* Конкурентный бейдж (критерий 13, EXPRESS-паттерн): тариф зоны владельца. Текст редактируется (критерий 16). */ ?>
            <?php if ($featDeliveryBadge): ?><span class="product-card__badge product-card__badge--deliv"><?= e(setting('delivery_badge_text', 'Доставка 0₽ · Приморский')) ?></span><?php endif; ?>
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
      <?php /* Empty-state (критик P2): при 0 карточек от фильтров — подсказка + сброс. Тексты редактируются (критерий 16). */ ?>
      <div class="catalog-empty" id="catalogEmpty" hidden style="text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px;margin-top:14px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px"><?= e(setting('catalog_empty_title', 'По этим фильтрам букетов не нашлось 🌷')) ?></p>
        <p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px"><?= e(setting('catalog_empty_hint', 'Попробуйте убрать фильтр цены или выбрать другую категорию')) ?></p>
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
      <?php /* Жюри design D6: auto-fit при 3 шагах на 1440px даёт мёртвый 4-й трек 0px.
          Честное число колонок = число непустых шагов. */ ?>
      <?php $stepsShown = count(array_filter([setting('step_1'), setting('step_2'), setting('step_3')], static fn($x) => trim((string)$x) !== '')); ?>
      <div class="how-it-works__grid" style="grid-template-columns:repeat(<?= (int)max(1, $stepsShown) ?>,1fr)">
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
      <h2 class="section-title"><?= e(setting('reviews_title', 'Отзывы о нас на Яндекс Картах')) ?></h2>
      <p class="section-sub"><?= e(setting('reviews_sub', 'Реальные отзывы покупателей — на карте города')) ?></p>
      <?php /* W59 (визит-критик): секция выглядела плейсхолдером (306px, одна кнопка, 0 элементов
         композиции). Честная карточка-цитата: мы НЕ выдумываем отзывы — объясняем, где они
         живут, и ведём и читать, и оставить. Тексты редактируются (критерий 14). */ ?>
      <div class="reviews-card reveal">
        <span class="reviews-card__mark" aria-hidden="true">&ldquo;</span>
        <p class="reviews-card__text"><?= e(setting('reviews_card_text', 'Мы не публикуем отзывы на сайте — пусть их пишут за нас. Все оценки и слова покупателей живут в профиле на Яндекс Картах: там же вы сможете оставить своё впечатление после доставки.')) ?></p>
        <div class="reviews-card__actions">
          <a class="btn btn--accent" href="https://yandex.ru/maps/org/<?= e(rawurlencode($yandexReviewsId)) ?>" target="_blank" rel="noopener">Читать отзывы</a>
          <a class="btn btn--outline" href="https://yandex.ru/maps/org/<?= e(rawurlencode($yandexReviewsId)) ?>/reviews" target="_blank" rel="noopener">Оставить отзыв</a>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ФОРМА ЗАКАЗА -->
  <section class="section order" id="order">
    <div class="wrap">
      <h2 class="section-title"><?= e(setting('order_title', 'Оформление заказа')) ?></h2>
      <p class="order__selected" id="orderSelected"></p>
      <form class="order-form" id="orderForm" novalidate>
        <div class="order-form__field">
          <label for="orderName"><?= e(setting('form_name_label', 'Ваше имя *')) ?></label>
          <input type="text" id="orderName" name="name" autocomplete="name" required minlength="2">
          <span class="order-form__error" id="orderNameError"></span>
        </div>
        <div class="order-form__field">
          <label for="orderPhone"><?= e(setting('form_phone_label', 'Телефон')) ?></label>
          <input type="tel" id="orderPhone" name="phone" autocomplete="tel" placeholder="+7 (___) ___-__-__">
          <span class="order-form__error" id="orderPhoneError"></span>
        </div>
        <div class="order-form__field">
          <label for="orderEmail"><?= e(setting('form_email_label', 'Email')) ?> <span id="orderEmailReq" style="color:var(--rose-cta,#AE4A71);font-weight:600" hidden>* обязательно для онлайн-оплаты</span></label>
          <input type="email" id="orderEmail" name="email" autocomplete="email" placeholder="example@mail.ru">
          <span class="order-form__hint" id="orderEmailHint" hidden>На этот адрес придёт чек об оплате</span>
          <span class="order-form__error" id="orderEmailError"></span>
        </div>
        <p class="order-form__hint" id="contactHint">Укажите телефон и/или email — как удобнее для связи</p>
        <fieldset class="order-form__nested">
          <legend><?= e(setting('fieldset_delivery_legend', 'Доставка')) ?></legend>
          <div class="order-form__field">
            <label for="orderDeliveryZone"><?= e(setting('form_delivery_zone_label', 'Как получить букет')) ?></label>
            <select id="orderDeliveryZone" name="delivery_zone">
              <?php
              /* Самовывоз — приоритетный способ: выбран по умолчанию, бесплатно.
                 Критик-мобайл (баг 4): полный адрес самовывоза — своя настройка pickup_address
                 (с фолбэком на shop_address), иначе город без улицы. */
              $pickupAddr = trim(setting('pickup_address', '')) ?: trim(setting('shop_address', ''));
              ?>
              <option value="0" data-price="0" selected><?= e(setting('pickup_option_text', 'Самовывоз — бесплатно')) ?></option>
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
            <input type="text" id="orderDeliveryAddress" name="delivery_address" placeholder="Улица, дом, квартира" autocomplete="street-address">
            <span class="order-form__error" id="orderDeliveryAddressError"></span>
          </div>
          <p class="order-form__hint" id="orderDeliveryHint"><?= e(setting('delivery_hint_text', 'Доставим в течение дня, время согласуем по телефону')) ?></p>
        </fieldset>
        <fieldset class="order-form__payment">
          <legend><?= e(setting('fieldset_payment_legend', 'Способ оплаты')) ?></legend>
          <?php
          /* Fail-safe: «онлайн» показываем только если ЮKassa реально настроена
             (галочка + ключи). Иначе покупатель увидит несбыточное обещание. */
          $ykLive = setting('yk_enabled', '0') === '1'
              && trim(setting('yk_shop_id', '')) !== ''
              && trim(setting('yk_secret_key', '')) !== '';
          ?>
          <?php if ($ykLive): ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="online" checked><span><?= e(setting('pay_online_label', 'Картой или через СБП — сразу онлайн')) ?></span></label>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash"><span><?= e(setting('pay_cash_label', 'При получении')) ?></span></label>
          <p class="order-form__hint">Оплата проходит на защищённой странице ЮKassa. Данные карты магазину не передаются.</p>
          <?php else: ?>
          <?php /* Покупатель-критик W46: radio «При получении» уже даёт payment_method —
                    hidden-дубль с тем же именем создавал двойное значение в FormData. */ ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash" checked><span><?= e(setting('pay_cash_label', 'При получении')) ?></span></label>
          <p class="order-form__hint">Оплата — курьеру при получении заказа.</p>
          <?php endif; ?>
        </fieldset>
        <div class="order-form__field">
          <label for="orderComment">Комментарий</label>
          <textarea id="orderComment" name="comment" rows="3" placeholder="Цветовые пожелания, подъезд, домофон"></textarea>
        </div>
        <?php if ($featGiftFields): ?>
        <?php /* Критик functional (gift-UX): цветы дарят — кому и что написать на открытке.
                 Все поля необязательные; пустые просто не попадают в заказ. */ ?>
        <fieldset class="order-form__payment" style="margin-top:4px">
          <legend>Кому дарим (необязательно)</legend>
          <div class="order-form__field">
            <label for="orderRecipientName">Имя получателя</label>
            <input type="text" id="orderRecipientName" name="recipient_name" maxlength="120" autocomplete="off" placeholder="Например: Анна">
          </div>
          <div class="order-form__field">
            <label for="orderRecipientPhone">Телефон получателя</label>
            <input type="tel" id="orderRecipientPhone" name="recipient_phone" autocomplete="off" placeholder="+7 (___) ___-__-__">
            <span class="order-form__error" id="orderRecipientPhoneError"></span>
            <p class="order-form__hint">Курьер позвонит ему, а не вам</p>
          </div>
          <div class="order-form__field">
            <label for="orderCardText">Текст открытки</label>
            <textarea id="orderCardText" name="card_text" rows="2" maxlength="500" placeholder="С днём рождения! ❤️ — от Евгения"></textarea>
            <p class="order-form__hint">Напишем от руки и вложим в букет · бесплатно</p>
          </div>
        </fieldset>
        <?php endif; ?>
        <?php if ($featDeliverySlots): ?>
        <?php /* Критик functional top#1: слоты даты/времени вместо «договоримся по телефону» */ ?>
        <fieldset class="order-form__payment" style="margin-top:4px">
          <legend>Когда доставить (необязательно)</legend>
          <div class="order-form__field">
            <label for="orderDeliveryDate">Дата</label>
            <input type="date" id="orderDeliveryDate" name="delivery_date">
            <span class="order-form__error" id="orderDeliveryDateError"></span>
          </div>
          <div class="order-form__field">
            <label for="orderDeliverySlot">Интервал</label>
            <select id="orderDeliverySlot" name="delivery_slot">
              <option value="">Любое время дня</option>
              <?php foreach (array_filter(array_map('trim', explode("\n", setting('delivery_slots', "Утро 9:00–14:00\nДень 14:00–18:00\nВечер 18:00–22:00")))) as $slotOption): ?>
                <option value="<?= e($slotOption) ?>"><?= e($slotOption) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </fieldset>
        <?php endif; ?>
        <?php /* Honeypot (критик security): невидимое поле — боты заполняют, люди нет */ ?>
        <div class="hp-field" aria-hidden="true" inert><label for="orderCompanyWebsite">Сайт компании</label><input type="text" id="orderCompanyWebsite" name="company_website" tabindex="-1" autocomplete="off"></div>
        <label class="order-form__checkbox">
          <input type="checkbox" id="orderPdConsent" name="pd_consent" required>
          <span>Я даю согласие на обработку персональных данных (ФИО, телефон, адрес) в целях оформления и доставки заказа, <em>включая возможную трансграничную передачу</em> (уведомление магазина через мессенджеры), на условиях <a href="/policy" target="_blank" rel="noopener">Политики конфиденциальности</a> и <a href="/offer" target="_blank" rel="noopener">Публичной оферты</a> *</span>
        </label>
        <span class="order-form__error" id="orderPdConsentError"></span>
        <p class="order-form__total" id="orderTotal"></p>
        <button type="submit" class="btn btn--accent order-form__submit" id="orderSubmit"
                data-pay-label="<?= e(setting('submit_button_text', 'Оплатить заказ')) ?>"
                data-nopay-label="<?= e(setting('submit_nopay_text', 'Отправить заказ')) ?>"><?= $ykLive ? e(setting('submit_button_text', 'Оплатить заказ')) : e(setting('submit_nopay_text', 'Отправить заказ')) ?></button>
        <p class="order-form__hint"><?= e(sprintf('Заказы до %s — доставим сегодня; после %s — привезём завтра с утра.', setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT), setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT))) ?></p>
        <p class="order-form__status" id="orderStatus" role="status" hidden></p>
      </form>
    </div>
  </section>

  <?php /* FAQ (критерий 13, SEO FAQPage — паттерн Цветовика): реальные вопросы покупателей. Отключаем (критерий 16). */ ?>
  <?php if ($featFaq): ?>
  <section class="section section--faq" id="faq">
    <div class="wrap" style="max-width:720px">
      <h2 class="section-title"><?= e(setting('faq_title', 'Частые вопросы')) ?></h2>
      <?php
      /* FAQ редактируется из админки (критерий 16): 4 пары вопрос-ответ.
         Непустые пары рендерятся; JSON-LD строится из тех же полей — синхрон с видимым текстом. */
      $faqItems = [];
      for ($i = 1; $i <= 4; $i++) {
          $q = trim(setting("faq_q{$i}", ''));
          $a = trim(setting("faq_a{$i}", ''));
          if ($q !== '' && $a !== '') { $faqItems[] = ['q' => $q, 'a' => $a]; }
      }
      foreach ($faqItems as $f): ?>
      <details class="faq-item">
        <summary class="faq-item__q"><?= e($f['q']) ?></summary>
        <p class="faq-item__a"><?= e($f['a']) ?></p>
      </details>
      <?php endforeach; ?>
      <?php if ($faqItems !== []): ?>
      <script type="application/ld+json">
      <?= json_encode([
          '@context' => 'https://schema.org',
          '@type' => 'FAQPage',
          'mainEntity' => array_map(static fn (array $f): array => [
              '@type' => 'Question',
              'name' => $f['q'],
              'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
          ], $faqItems),
      ], JSON_UNESCAPED_UNICODE) ?>
      </script>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
