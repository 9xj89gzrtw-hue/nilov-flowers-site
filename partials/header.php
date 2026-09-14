<?php
/* Шапка витрины. Настройки читает напрямую из settings (shop_name, shop_phone, shop_address). */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';

$siteName = setting('shop_name', 'Nilov Flowers');
$phone = setting('shop_phone', '');
$address = setting('shop_address', '');
/* C2: короткие контакты для шапки (пусто → полные из shop_phone/shop_address; совсем пусто → скрыть) */
$headerPhone = setting('header_phone', '') !== '' ? setting('header_phone', '') : $phone;
$headerAddress = setting('header_address', '') !== '' ? setting('header_address', '') : $address;
/* Режим корзины (settings → cart_mode): drawer | hybrid | page.
   drawer — панель открывается сама при добавлении и по клику на иконку;
   hybrid — только по клику на иконку; page — иконка ведёт к форме заказа. */
$cartMode = in_array(setting('cart_mode', 'drawer'), ['drawer', 'hybrid', 'page'], true)
    ? setting('cart_mode', 'drawer') : 'drawer';
?>
<header class="site-header">
  <div class="wrap">
    <a href="/" class="site-logo">
      <?php /* Логотип-картинка: показывать, только если загружен И включён тумблером (критерий 23). */ ?>
      <?php if (setting('logo_image') !== '' && setting('logo_enabled', '1') === '1'): ?><img class="site-logo__img" src="/img/uploads/<?= e(setting('logo_image')) ?>" alt=""><?php endif; ?>
      <span><?= e($siteName) ?></span>
    </a>
    <div class="site-header__contact">
      <?php if ($headerPhone !== ''): ?><a href="tel:+<?= e(preg_replace('/\D/', '', $headerPhone)) ?>" class="site-header__contact-phone"><?= e($headerPhone) ?></a><?php endif; ?>
      <?php if ($headerAddress !== ''): ?><span> · <?= e($headerAddress) ?></span><?php endif; ?>
    </div>
    <nav class="site-nav">
      <a href="/#catalog">Каталог</a>
      <a href="/#how-it-works">Как работаем</a>
      <a href="/#order">Заказать</a>
      <?php /* Критик-покупатель B7: на мобильном телефон должен быть в шапке, не только в футере */ ?>
      <?php if ($headerPhone !== ''): ?>
      <a href="tel:+<?= e(preg_replace('/\D/', '', $headerPhone)) ?>" class="site-header__call" aria-label="Позвонить нам: <?= e($headerPhone) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1L6.6 10.8z"/></svg>
      </a>
      <?php endif; ?>
      <button type="button" class="cart-toggle" id="cartToggle" data-cart-mode="<?= e($cartMode) ?>" aria-label="Корзина" aria-haspopup="dialog" aria-expanded="false">
        <svg class="cart-toggle__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M2.5 3h2l2.6 12.4a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6"/></svg>
        <span class="cart-toggle__count" id="cartCount" hidden>0</span>
      </button>
    </nav>
  </div>
</header>
