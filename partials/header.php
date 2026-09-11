<?php
/* Шапка витрины. Настройки читает напрямую из settings (shop_name, shop_phone, shop_address). */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';

$siteName = setting('shop_name', 'Магазин цветов');
$phone = setting('shop_phone', '');
$address = setting('shop_address', '');
/* Режим корзины (settings → cart_mode): drawer | hybrid | page.
   drawer — панель открывается сама при добавлении и по клику на иконку;
   hybrid — только по клику на иконку; page — иконка ведёт к форме заказа. */
$cartMode = in_array(setting('cart_mode', 'drawer'), ['drawer', 'hybrid', 'page'], true)
    ? setting('cart_mode', 'drawer') : 'drawer';
?>
<div class="demo-banner" role="status">Демонстрационная версия. Заказы не исполняются, оплата тестовая.</div>

<header class="site-header">
  <div class="wrap">
    <a href="/" class="site-logo">
      <?php if (setting('logo_image') !== ''): ?><img class="site-logo__img" src="/img/uploads/<?= e(setting('logo_image')) ?>" alt=""><?php endif; ?>
      <span><?= e($siteName) ?></span>
    </a>
    <div class="site-header__contact">
      <?php if ($phone !== ''): ?><a href="tel:+<?= e(preg_replace('/\D/', '', $phone)) ?>" class="site-header__contact-phone"><?= e($phone) ?></a><?php endif; ?>
      <?php if ($address !== ''): ?><span> · <?= e($address) ?></span><?php endif; ?>
    </div>
    <nav class="site-nav">
      <a href="/#catalog">Каталог</a>
      <a href="/#how-it-works">Как работаем</a>
      <a href="/#order">Заказать</a>
      <button type="button" class="cart-toggle" id="cartToggle" data-cart-mode="<?= e($cartMode) ?>" aria-label="Корзина" aria-haspopup="dialog" aria-expanded="false">
        <svg class="cart-toggle__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M2.5 3h2l2.6 12.4a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6"/></svg>
        <span class="cart-toggle__count" id="cartCount" hidden>0</span>
      </button>
    </nav>
  </div>
</header>
