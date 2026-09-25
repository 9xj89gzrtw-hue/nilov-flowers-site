<?php
/* Шапка витрины (редизайн 5cv, W96): логотип-цветок, кнопка «Каталог», поиск,
   город, телефон, иконки избранного и корзины. JS-контракты сохранены:
   #cartToggle/#cartCount (cart-ui.js), #fcSearch (js/five.js). */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';

$siteName = setting('shop_name', 'Nilov Flowers');
/* W97-fixB1 (B1-1): имя ЛОКАЛЬНОЙ переменной шапки не должно быть $phone — track.php
   читает $phone из GET ДО require шапки, а шапка затирала его телефоном магазина
   (поле «Телефон из заказа» на /track всегда было предзаполнено номером магазина). */
$headerShopPhone = setting('shop_phone', '');
/* C2: короткий телефон для шапки (пусто → полный shop_phone; совсем пусто → скрыть) */
$headerPhone = setting('header_phone', '') !== '' ? setting('header_phone', '') : $headerShopPhone;
/* Режим корзины (settings → cart_mode): drawer | hybrid | page.
   drawer — панель открывается сама при добавлении и по клику на иконку;
   hybrid — только по клику на иконку; page — иконка ведёт к форме заказа. */
$cartMode = in_array(setting('cart_mode', 'drawer'), ['drawer', 'hybrid', 'page'], true)
    ? setting('cart_mode', 'drawer') : 'drawer';
?>
<?php /* a11y-критик re-check: skip-link в общем header.php — есть на каждой витрина-страница
   (home, product, offer, policy, track), а не только на главной.
   W97-fixB3a (B3a-5): на ГЛАВНОЙ index.php печатает skip-link сам — до город-бара,
   первым элементом DOM (первый фокус с Tab); флаг $skipLinkRendered не даёт продублировать. */ ?>
<?php if (empty($skipLinkRendered)): ?>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<?php endif; ?>
<header class="fc-header">
  <div class="wrap fc-header__inner">
    <a href="/" class="fc-header__logo">
      <?php /* Логотип-картинка: показывать, только если загружен И включён тумблером (критерий 23). */ ?>
      <?php if (setting('logo_image') !== '' && setting('logo_enabled', '1') === '1'): ?><img class="fc-header__logo-img" src="/img/uploads/<?= e(setting('logo_image')) ?>" alt="<?= e($siteName) ?>"><?php else: ?>
        <?php /* Фолбэк: простой 5-лепестковый цветок (розовые лепестки + янтарная сердцевина) */ ?>
        <svg class="fc-header__logo-flower" width="28" height="28" viewBox="0 0 32 32" style="color:var(--pink,#ff4ea2)" aria-hidden="true">
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor"/>
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(72 16 16)"/>
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(144 16 16)"/>
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(216 16 16)"/>
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(288 16 16)"/>
          <circle cx="16" cy="16" r="3.2" style="fill:var(--amber,#f5b301)"/>
        </svg>
      <?php endif; ?>
      <span><?= e($siteName) ?></span>
    </a>
    <?php /* W96-fix1 (F2): «Каталог» работает и со вторичных страниц — абсолютный
       якорь /#catalog (на главной — тот же документ, просто скролл) */ ?>
    <a class="fc-catalog-btn" href="/#catalog">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.6"/></svg>
      <span><?= e(setting('catalog_btn_text', 'Каталог')) ?></span>
    </a>
    <?php /* Поиск по каталогу (W96-fix1/F3): action="/" + name="q" — без JS
       нативный submit уводит на главную с запросом; js/five.js на главной фильтрует
       живьём (морфология: «розы» → «роз»), со вторичных страниц — редирект /?q=…#catalog */ ?>
    <form class="fc-search" role="search" action="/" method="get">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" id="fcSearch" name="q" placeholder="<?= e(setting('search_placeholder', 'Розы, пионы, букет маме…')) ?>" aria-label="Поиск по букетам">
    </form>
    <span class="fc-header__city">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>
      <?= e(setting('city_label', 'Санкт-Петербург')) ?>
    </span>
    <?php if ($headerPhone !== ''): ?><a class="fc-header__phone" href="tel:+<?= e(preg_replace('/\D/', '', $headerPhone)) ?>"><?= e($headerPhone) ?></a><?php endif; ?>
    <div class="fc-header__icons">
      <?php /* W96-fix3b (D7): звонок — главный канал цветочного; на мобиле
         текстовый телефон скрыт (≤899px) — круглая иконка-трубка tel: рядом
         с корзиной. CSS: .fc-header__call показывается только ≤899px. */ ?>
      <?php if ($headerPhone !== ''): ?>
      <a class="fc-header__icon fc-header__call" href="tel:+<?= e(preg_replace('/\D/', '', $headerPhone)) ?>" aria-label="Позвонить">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </a>
      <?php endif; ?>
      <?php /* Избранное живёт в каталоге (сердечки на карточках) — ведём туда;
         W96-fix1 (F2): абсолютный якорь — работает и с вторичных страниц */ ?>
      <a class="fc-header__icon" href="/#catalog" aria-label="Избранное — в каталоге">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-4.6-7-10a4.5 4.5 0 0 1 7-3.7A4.5 4.5 0 0 1 19 11c0 5.4-7 10-7 10z"/></svg>
      </a>
      <?php /* Кнопка корзины — контракт cart-ui.js, разметку не меняем (CSS перекрасит) */ ?>
      <button type="button" class="cart-toggle" id="cartToggle" data-cart-mode="<?= e($cartMode) ?>" aria-label="Корзина" aria-haspopup="dialog" aria-expanded="false">
        <svg class="cart-toggle__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M2.5 3h2l2.6 12.4a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6"/></svg>
        <span class="cart-toggle__count" id="cartCount" hidden>0</span>
      </button>
    </div>
  </div>
</header>
