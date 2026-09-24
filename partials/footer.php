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
      <?php
      /* Реквизиты продавца в футере (152-ФЗ + доверие скептика-покупателя).
         Показываются только если владелец заполнил legal_* в настройках. */
      $legalType = mb_strtolower(trim(setting('legal_subject_type', '')));
      $legalName = setting('legal_name', '');
      $legalNum = setting('legal_number', '');
      $legalAddr = setting('legal_address', '');
      if ($legalName !== '' && $legalNum !== ''): ?>
      <?php $legalInn = setting('legal_inn', ''); ?>
      <p style="font-size:.78rem;color:var(--ink-soft)"><?= e($legalName) ?><?= $legalInn !== '' ? ' · ИНН ' . e($legalInn) : '' ?> · <?= e($legalType === 'ip' ?  'ОГРНИП' : 'ОГРН') ?> <?= e($legalNum) ?><?= $legalAddr !== '' ? ' · ' . e($legalAddr) : '' ?></p>
      <?php endif; ?>
      <?php if ($address !== ''): ?><p><?= e($address) ?></p><?php endif; ?>
      <?php if (setting('shop_hours', '') !== ''): ?><p style="color:var(--ink-soft);font-size:.92rem"><?= e(setting('shop_hours')) ?></p><?php endif; ?>
      <?php if ($phone !== ''): ?><p><a class="site-footer__phone" href="tel:+<?= e($phoneDigits) ?>"><?= e($phone) ?></a></p><?php endif; ?>
      <?php if ($waOn || $tgOn || $vkOn || $maxOn || $igOn || $emailOn): ?>
      <p class="site-footer__messengers">
        <?php if ($waOn): ?><a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $whatsapp)) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php if ($tgOn): ?><a href="https://t.me/<?= e($telegram) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
        <?php if ($vkOn): ?><a href="<?= e(preg_match('#^https?://#i', setting('shop_vk')) ? setting('shop_vk') : 'https://vk.com/' . ltrim(setting('shop_vk'), '/')) ?>" target="_blank" rel="noopener me">VK</a><?php endif; ?>
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
    <?php try { $__occLinks = db()->query('SELECT title, slug FROM occasions WHERE active = 1 ORDER BY sort, id LIMIT 3')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $__occLinks = []; } ?>
    <span>© <?= date('Y') ?> <?= e($siteName) ?><?php $ocStr=''; foreach ($__occLinks as $__o) { $ocStr .= ' · <a href="/occasion/' . rawurlencode($__o['slug']) . '">' . e($__o['title']) . '</a>'; } echo setting('feature_track_link','1')==='1' ? ' · <a href="/track">Где мой заказ?</a>' : ''; echo $ocStr; ?> · <a href="/policy">Конфиденциальность</a> · <a href="/offer">Оферта</a> · <a href="#" onclick="if(window.cookieSettings){window.cookieSettings();}return false">Настройки cookie</a></span>
  </div>
</footer>

<nav class="mnav" aria-label="Мобильная навигация">
  <a href="/#catalog"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-4.6-7-10a4.5 4.5 0 0 1 7-3.7A4.5 4.5 0 0 1 19 11c0 5.4-7 10-7 10z"/></svg>Каталог</a>
  <?php /* W96 (5cv): «Как работаем» → «Поводы» (how-it-works на витрине больше нет) */ ?>
  <a href="/#occasions"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>Поводы</a>
  <a href="/#contacts"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1L6.6 10.8z"/></svg>Контакты</a>
  <a href="/#order"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h13l-1.5 8.5H7.2L5 4H2"/><circle cx="8.5" cy="19" r="1.4"/><circle cx="14.5" cy="19" r="1.4"/></svg>Заказать</a>
</nav>

<div class="cart-panel" id="cartPanel" hidden>
  <div class="cart-panel__backdrop" id="cartBackdrop"></div>
  <aside class="cart-panel__drawer" role="dialog" aria-modal="true" aria-label="Корзина">
    <div class="cart-panel__head">
      <h2 class="cart-panel__title"><?= e(setting('cart_title', 'Корзина')) ?></h2>
      <button type="button" class="cart-panel__close" id="cartClose" aria-label="Закрыть корзину">&times;</button>
    </div>
    <div class="cart-panel__items" id="cartItems"></div>
    <p class="cart-panel__empty" id="cartEmpty"><?= e(setting('cart_empty_text', 'Корзина пуста — выберите букет в каталоге')) ?></p>
    <div class="cart-upsell" id="cartUpsell" hidden>
      <p class="cart-upsell__title"><?= e(setting('upsell_title', 'Возможно, пригодится')) ?></p>
      <div class="cart-upsell__items" id="cartUpsellItems"></div>
    </div>
    <div class="cart-panel__foot">
      <?php /* Промокод (критик functional top#3): применяется по серверной проверке /api/promo */ ?>
      <?php if (setting('feature_promo', '1') === '1'): ?>
      <div class="cart-promo" id="cartPromo">
        <input type="text" id="cartPromoInput" maxlength="32" placeholder="Промокод" aria-label="Промокод" autocomplete="off" style="flex:1;min-width:0;padding:10px 12px;border:1px solid var(--line);border-radius:var(--radius,12px);font:inherit">
        <button type="button" id="cartPromoApply" class="cart-promo__btn" style="min-height:44px;padding:11px 16px;border:1px solid var(--line);border-radius:10px;background:#fff;font:600 .85rem var(--font-ui);cursor:pointer">Применить</button>
      </div>
      <p id="cartPromoMsg" class="cart-promo__msg" style="margin:4px 0 0;font-size:.8rem;color:var(--ink-soft)" aria-live="polite"></p>
      <?php endif; ?>
      <p class="cart-panel__total">Итого: <span id="cartTotal">0 ₽</span></p>
      <?php /* Логика-критик W34: «Итого» в корзине ≠ «К оплате» в форме (drawer не знает район).
         Честная сноска вместо расхождения; текст правится в админке, пустая строка = скрыть. */ ?>
      <?php
        $znMin = null; $znMax = null;
        try {
          $zr = db()->query('SELECT MIN(price) mn, MAX(price) mx FROM delivery_zones')->fetch();
          $znMin = (int)($zr['mn'] ?? 0); $znMax = (int)($zr['mx'] ?? 0);
        } catch (Throwable $e) { $znMin = 0; $znMax = 0; }
        $totalNoteDefault = ($znMax > 0)
          ? sprintf('Доставка по вашему району — от %s до %s ₽, точную стоимость покажем в заказе.', $znMin === 0 ? '0' : number_format($znMin, 0, ',', ' '), number_format($znMax, 0, ',', ' '))
          : '';
        $totalNote = setting('cart_total_note', '__DEFAULT__');
        if ($totalNote === '__DEFAULT__') $totalNote = $totalNoteDefault;
      ?>
      <?php if ($totalNote !== ''): ?><p class="cart-panel__note" style="font-size:.76rem;color:var(--ink-soft);margin:2px 0 0"><?= e($totalNote) ?></p><?php endif; ?>
      <button type="button" class="btn btn--accent" id="cartCheckout" disabled><?= e(setting('cart_checkout_text', 'Оформить заказ')) ?></button>
      <button type="button" class="btn btn--outline cart-panel__continue" id="cartContinue"><?= e(setting('cart_continue_text', 'Продолжить покупки')) ?></button>
    </div>
  </aside>
</div>

<script src="/js/cart.js"></script>
<script>window.UPSELL_LIMIT = <?= max(1, min(6, (int) setting('upsell_limit', '3'))) ?>; window.UPSELL_ENABLED = <?= setting('upsell_enabled', '1') === '1' ? 1 : 0 ?>; window.UPSELL_CATEGORIES = <?= json_encode(array_filter(array_map('trim', explode(',', setting('upsell_categories', ''))))) ?>;</script>
<script src="/js/cart-ui.js"></script>
<?php if (setting('feature_favicon_badge', '1') === '1'): ?><script src="/js/favicon-badge.js"></script><?php endif; ?>
<script src="/js/cart-cta.js"></script>
<script src="/js/order-form.js"></script>
<script src="/js/catalog-filter.js"></script>
<?php /* W96 (5cv): город-бар, карусели, чипы цен, поиск — поверх catalog-filter.js */ ?>
<script src="/js/five.js"></script>
<script src="/js/product-gallery.js"></script>
<script src="/js/lightbox.js"></script>
<script src="/js/lazy-images.js"></script>
<script src="/js/reveal.js"></script>
<script src="/js/nilov.js" defer></script>
<script>window.COOKIE_BANNER_CONFIG = {
  text: <?= json_encode(sanitize_rich_text(setting('cookie_banner_text', 'Сайт использует cookie и Яндекс.Метрику для работы и анализа трафика. Подробнее — в <a href="/policy" target="_blank" rel="noopener">Политике обработки персональных данных</a>.'), 260), JSON_UNESCAPED_UNICODE) ?>,
  accept: <?= json_encode(setting('cookie_accept_text', 'Принять'), JSON_UNESCAPED_UNICODE) ?>,
  reject: <?= json_encode(setting('cookie_reject_text', 'Только необходимые'), JSON_UNESCAPED_UNICODE) ?>
};</script>
<script src="/js/cookie-banner.js"></script>
