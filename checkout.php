<?php
/* Оформление заказа — S11 ЗОНА 2, чекаут 2026:
   • Пустая корзина → аккуратный экран «Ваша корзина пуста» + кнопка
     «Перейти к выбору букетов» (JS-переключатель ниже).
   • Товары есть → 2 колонки на десктопе (шаги слева, липкая сводка
     справа), 1 колонка на мобильном (сводка сверху, шаги ниже).
   • Шаги: 1 Контакты → 2 Получатель → 3 Доставка по СПб → 4 Подарки →
     5 Оплата (partials/order-form.php, контракт id полей сохранён).
   URL: /checkout.php; meta robots — noindex (служебная страница воронки). */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$shopName = setting('shop_name', 'Nilov Flowers');
$pageTitle = 'Оформление заказа — ' . $shopName;
$secondaryCssV = substr((string)@md5_file(__DIR__ . '/css/secondary.css'), 0, 8);
$checkoutCssV = substr((string)@md5_file(__DIR__ . '/css/five.css'), 0, 8);
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta name="robots" content="noindex,follow">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="Оформление заказа букета с доставкой по Санкт-Петербургу — соберём за 1–2 часа и привезём сегодня.">
<?php require __DIR__ . '/partials/head.php'; ?>
<link rel="stylesheet" href="/css/secondary.css?v=<?= e($secondaryCssV) ?>">
<?php /* NILOV_CONFIG — тот же конфиг, что печатает index.php (js/order-form.js
       читает freeDeliveryThreshold для «К оплате»; на чекауте hero нет). */ ?>
<script>window.NILOV_CONFIG = {
  deadlineHour: <?= (int)(setting('order_deadline_hour', '20')) ?>,
  deadlineMinute: <?= (int)(setting('order_deadline_minute', '0')) ?>,
  openHour: <?= (int)(preg_match('/(\d{1,2})\s*:/', (string)setting('shop_hours', ''), $m) ? max(0, min(23, (int)$m[1])) : 9) ?>,
  tz: <?= json_encode(setting('shop_timezone', 'Europe/Moscow')) ?>,
  nightText: <?= json_encode(setting('countdown_night_text', 'Примем заказ сейчас — доставим с 9:00 утра'), JSON_UNESCAPED_UNICODE) ?>,
  countdownText: <?= json_encode(setting('countdown_text', 'Заказ до {D} — доставим сегодня'), JSON_UNESCAPED_UNICODE) ?>,
  closedText: <?= json_encode(setting('countdown_closed_text', 'Приём заказов на сегодня закрыт — доставим завтра с 9:00'), JSON_UNESCAPED_UNICODE) ?>,
  freeDeliveryThreshold: <?= (int) setting('free_delivery_threshold', '0') ?>
};</script>
</head>
<body class="page-checkout">
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> / <span aria-current="page">Оформление заказа</span>
      </nav>
      <div class="page-hero">
        <h1 class="page-hero__title"><em class="page-hero__accent">Оформление</em> заказа</h1>
        <p class="section-sub">Заполните шаги — соберём за 1–2 часа и привезём сегодня. Позвоним для подтверждения.</p>
      </div>
    </div>
  </section>

  <?php /* ===== СЦЕНАРИЙ 1: пустая корзина — аккуратный экран-заглушка =====
         (переключает инлайн-скрипт внизу страницы; по умолчанию скрыт).
         Возврат «Назад» из bfcache ловит pageshow — экран честен всегда. */ ?>
  <section class="fc-section" id="checkoutEmptyScreen" hidden>
    <div class="wrap">
      <div class="checkout-empty">
        <div class="checkout-empty__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h13l-1.5 8.5H7.2L5 4H2"/><circle cx="8.5" cy="19" r="1.4"/><circle cx="14.5" cy="19" r="1.4"/><path d="M10 10.5l4 4M14 10.5l-4 4"/></svg>
        </div>
        <h2 class="checkout-empty__title">Ваша корзина пуста</h2>
        <p class="checkout-empty__text">Выберите букет в каталоге — свежие цветы на любой бюджет, доставка по СПб за 1–2 часа.</p>
        <a class="btn btn--accent checkout-empty__cta" href="/#catalog">Перейти к выбору букетов</a>
      </div>
    </div>
  </section>

  <?php /* ===== СЦЕНАРИЙ 2: заказ — 2 колонки (шаги + липкая сводка) ===== */ ?>
  <div id="checkoutMain">
  <div class="wrap">
    <div class="checkout-layout">

      <?php /* Левая колонка — ШАГИ ОФОРМЛЕНИЯ (форма; partial без секции —
             $orderBare убирает обёртку fc-section/wrap, сеткой управляет
             .checkout-layout) */ ?>
      <div class="checkout-steps">
        <?php
        $orderHideTitle = true;
        $orderBare = true;
        require __DIR__ . '/partials/order-form.php';
        ?>
      </div>

      <?php /* Правая колонка — ЛИПКАЯ СВОДКА ЗАКАЗА: мини-фото букетов,
             название, цена, стоимость доставки по выбранному району и
             итоговая сумма (js/cart-ui.js renderCheckoutSummary — обновление
             на cart:change и смену района). */ ?>
      <aside class="checkout-aside" aria-label="Сводка заказа">
        <div class="checkout-summary" id="checkoutSummary">
          <div class="checkout-summary__head">
            <p class="checkout-summary__title">Ваш заказ</p>
            <a class="checkout-summary__link" href="/#catalog">Добавить букеты</a>
          </div>
          <ul class="checkout-summary__items" id="checkoutSummaryItems"></ul>
          <p class="checkout-summary__empty" id="checkoutSummaryEmpty" hidden>В корзине пока пусто — выберите букет в каталоге, и он появится здесь.</p>
          <div class="checkout-summary__rows">
            <p class="checkout-summary__row" id="checkoutSummaryPromoRow" hidden><span id="checkoutSummaryPromoLabel"></span><span id="checkoutSummaryPromoValue"></span></p>
            <p class="checkout-summary__row"><span>Букеты</span><span id="checkoutSummaryItemsTotal">—</span></p>
            <p class="checkout-summary__row"><span>Доставка</span><span id="checkoutSummaryDelivery">—</span></p>
            <p class="checkout-summary__row checkout-summary__row--total"><span>Итого</span><span id="checkoutSummaryTotal">—</span></p>
          </div>
          <p class="checkout-summary__note">Оплата — при получении: наличными, картой или по СБП.<?= setting('feature_promo', '1') === '1' ? ' Промокод можно указать в шаге 5.' : '' ?></p>
          <?php /* Мини-бейджи доверия под сводкой (5cv-паттерн) */ ?>
          <ul class="checkout-summary__trust" aria-label="Гарантии">
            <li>⚡ Доставим за 1–2 часа</li>
            <li>📷 Фото букета перед отправкой</li>
            <li>🌿 Аквабокс и Кризал в подарок</li>
          </ul>
        </div>
      </aside>

    </div>
  </div>
  </div><?php /* /#checkoutMain */ ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php /* S11: переключатель сценариев чекаута — пустая корзина против заказа.
       Тот же источник данных, что cart-ui.js (localStorage корзины);
       события cart:change + pageshow держат экран честным в любой гонке. */ ?>
<script>
(function () {
  var emptyScreen = document.getElementById('checkoutEmptyScreen');
  var main = document.getElementById('checkoutMain');
  if (!emptyScreen || !main) return;
  function sync() {
    var items = window.cart ? window.cart.getItems() : [];
    var isEmpty = items.length === 0;
    emptyScreen.hidden = !isEmpty;
    main.style.display = isEmpty ? 'none' : '';
  }
  document.addEventListener('cart:change', sync);
  window.addEventListener('pageshow', sync);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sync);
  } else {
    sync();
  }
})();
</script>
</body>
</html>
