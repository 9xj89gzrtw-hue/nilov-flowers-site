<?php
/* Подвал + мобильный таббар + корзина-drawer (ids согласованы с cart-ui.js) */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';

$siteName = setting('shop_name', 'Nilov Flowers');
$phone = setting('shop_phone', '');
$address = setting('shop_address', '');
$whatsapp = setting('shop_whatsapp', '');
$telegram = setting('shop_telegram', '');
$phoneDigits = preg_replace('/\D/', '', $phone) ?: '';
$waOn = setting('wa_enabled', '1') === '1' && $whatsapp !== '';
$tgOn = setting('tg_enabled', '1') === '1' && $telegram !== '';
$vkOn = setting('vk_enabled', '1') === '1' && setting('shop_vk', '') !== '';
$maxOn = setting('max_enabled', '1') === '1' && setting('shop_max_link', '') !== '';
$igOn = setting('ig_enabled', '1') === '1' && setting('shop_instagram', '') !== '';
$emailOn = setting('email_enabled', '1') === '1' && setting('shop_email', '') !== '';
$igDisclaimer = $igOn; /* пометку Meta показываем только вместе с активной ссылкой Instagram */
?>
<footer class="site-footer" id="contacts">
  <div class="wrap">
    <div>
      <p class="site-footer__name"><?= e($siteName) ?></p>
      <?php if ($address !== ''): ?><p><?= e($address) ?></p><?php endif; ?>
      <?php if ($phone !== ''): ?><p><a class="site-footer__phone" href="tel:+<?= e($phoneDigits) ?>"><?= e($phone) ?></a></p><?php endif; ?>
      <?php if ($waOn || $tgOn || $vkOn || $maxOn || $igOn || $emailOn): ?>
      <p class="site-footer__messengers">
        <?php if ($waOn): ?><a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $whatsapp)) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php if ($tgOn): ?><a href="https://t.me/<?= e($telegram) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
        <?php if ($vkOn): ?><a href="<?= e(setting('shop_vk')) ?>" target="_blank" rel="noopener">VK</a><?php endif; ?>
        <?php if ($maxOn): ?><a href="<?= e(setting('shop_max_link')) ?>" target="_blank" rel="noopener">MAX</a><?php endif; ?>
        <?php if ($igOn): ?>
          <a href="<?= e(setting('shop_instagram')) ?>" target="_blank" rel="noopener">Instagram*</a>
        <?php endif; ?>
        <?php if ($emailOn): ?><a href="mailto:<?= e(setting('shop_email')) ?>"><?= e(setting('shop_email')) ?></a><?php endif; ?>
      </p>
      <?php if ($igDisclaimer): ?>
      <p style="font-size:.72rem;color:var(--ink-soft);margin-top:4px">* Instagram принадлежит Meta, признанной экстремистской организацией, деятельность которой запрещена на территории РФ.</p>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <span>© <?= date('Y') ?> <?= e($siteName) ?> · <a href="/policy">Политика ПД</a> · <a href="/offer">Оферта</a></span>
  </div>
</footer>

<nav class="mnav" aria-label="Мобильная навигация">
  <a href="/#catalog"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-4.6-7-10a4.5 4.5 0 0 1 7-3.7A4.5 4.5 0 0 1 19 11c0 5.4-7 10-7 10z"/></svg>Каталог</a>
  <a href="/#how-it-works"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Как работаем</a>
  <a href="/#contacts"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1L6.6 10.8z"/></svg>Контакты</a>
  <a href="/#order"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>Заказать</a>
</nav>

<div class="cart-panel" id="cartPanel" hidden>
  <div class="cart-panel__backdrop" id="cartBackdrop"></div>
  <aside class="cart-panel__drawer" role="dialog" aria-modal="true" aria-label="Корзина">
    <div class="cart-panel__head">
      <h2 class="cart-panel__title">Корзина</h2>
      <button type="button" class="cart-panel__close" id="cartClose" aria-label="Закрыть корзину">&times;</button>
    </div>
    <div class="cart-panel__items" id="cartItems"></div>
    <p class="cart-panel__empty" id="cartEmpty">Корзина пуста — выберите букет в каталоге</p>
    <div class="cart-upsell" id="cartUpsell" hidden>
      <p class="cart-upsell__title">Добавить к заказу</p>
      <div class="cart-upsell__items" id="cartUpsellItems"></div>
    </div>
    <div class="cart-panel__foot">
      <p class="cart-panel__total">Итого: <span id="cartTotal">0 ₽</span></p>
      <button type="button" class="btn btn--accent" id="cartCheckout" disabled>Оформить заказ</button>
      <button type="button" class="btn btn--outline cart-panel__continue" id="cartContinue">Продолжить покупки</button>
    </div>
  </aside>
</div>

<script src="/js/cart.js"></script>
<script src="/js/cart-ui.js"></script>
<script src="/js/cart-cta.js"></script>
<script src="/js/order-form.js"></script>
<script src="/js/catalog-filter.js"></script>
<script src="/js/product-gallery.js"></script>
<script src="/js/lightbox.js"></script>
<script src="/js/lazy-images.js"></script>
<script src="/js/reveal.js"></script>
<script src="/js/cookie-banner.js"></script>
