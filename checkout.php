<?php
/* Оформление заказа — отдельная страница (W106-E1, Нильсен P0 + спешащий P1:
   «чекаут заперт в лендинге» — кнопка «Оформить заказ» с вторичных страниц
   телепортировала на главную #order). Форма — тот же partials/order-form.php,
   что рендет главная (единый контракт id полей для js/order-form.js).
   Сверху — сводка корзины: корзина живёт в localStorage (JS), поэтому блок —
   плейсхолдер, который заполняет js/cart-ui.js (renderCheckoutSummary) при
   загрузке и на каждое cart:change / смену района доставки.
   URL: /checkout.php (роутер отдаёт физический файл; ЧПУ /checkout потребовал
   бы правки router.php — вне зоны волны). meta robots — noindex: дублирующий
   функционал главной, канонический путь покупки индексировать не нужно. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$shopName = setting('shop_name', 'Nilov Flowers');
$pageTitle = 'Оформление заказа — ' . $shopName;
/* Сводка/форма не тянут тяжёлых расчётов — зоны достанет сам partial */
$secondaryCssV = substr((string)@md5_file(__DIR__ . '/css/secondary.css'), 0, 8);
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta name="robots" content="noindex,follow">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="Оформление заказа букета с доставкой по Санкт-Петербургу — соберём за 1–2 часа и привезём сегодня.">
<?php require __DIR__ . '/partials/head.php'; ?>
<link rel="stylesheet" href="/css/secondary.css?v=<?= e($secondaryCssV) ?>">
<?php /* NILOV_CONFIG — тот же конфиг, что печатает index.php в hero (js/order-form.js
       читает freeDeliveryThreshold для «К оплате»; на чекауте hero нет — конфиг здесь). */ ?>
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
<?php /* F2 (P0-3, моб-критик волны 4): класс страницы — CSS (five.css F2-3)
       делает хедер компактным (лого + трубка + корзина, 64/56px вместо
       72/145px) и прячет мобильный таб-бар (в чекауте — шум, паттерн
       body.page-product). */ ?>
<body class="page-checkout">
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> / <span aria-current="page">Оформление заказа</span>
      </nav>
      <?php /* page-hero — паттерн вторичных страниц (category.php): Playfair H1,
             первое слово — italic-акцент pink-deep. */ ?>
      <div class="page-hero">
        <h1 class="page-hero__title"><em class="page-hero__accent">Оформление</em> заказа</h1>
        <?php /* W106-E1: единая SLA-формула сайта (корректор P0) */ ?>
        <p class="section-sub">Проверьте букеты и заполните форму — соберём за 1–2 часа и привезём сегодня. Позвоним для подтверждения.</p>
      </div>
    </div>
  </section>

  <?php /* СВОДКА КОРЗИНЫ (W106-E1): корзина в localStorage → блок-плейсхолдер,
         товары/цены/промо/итог рисует js/cart-ui.js (renderCheckoutSummary):
         как drawer, только компактной таблицей. Район доставки берётся из
         селекта формы ниже (делегированный change) — итог живой. */ ?>
  <section class="fc-section checkout-summary-section" id="checkoutSummarySection" aria-label="Корзина">
    <div class="wrap">
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
        <?php /* G-g2 (редактор P1: «Промокод применяется в корзине» путало —
               поле промо теперь реально живёт ниже, в форме заказа:
               partials/order-form.php → js/cart-ui.js, тот же PROMO_STATE,
               что drawer) */ ?>
        <p class="checkout-summary__note">Оплата — курьеру при получении: наличными или картой.<?= setting('feature_promo', '1') === '1' ? ' Промокод можно указать ниже, в форме.' : '' ?></p>
      </div>
    </div>
  </section>

  <?php /* Форма заказа — тот же partial, что на главной. H2 секции не печатаем:
         его роль несёт page-hero H1 выше ($orderHideTitle). */ ?>
  <?php $orderHideTitle = true; ?>
  <?php require __DIR__ . '/partials/order-form.php'; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
