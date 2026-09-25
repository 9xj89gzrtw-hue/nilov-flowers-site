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

/* W96-fix3b (D2): колоночный футер — категории каталога (вкладки) и поводы.
   Запросы обёрнуты в try — футер не должен падать на старой/повреждённой БД. */
try {
    $__footerCats = db()->query('SELECT id, name FROM categories ORDER BY sort, id')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $__footerCats = [];
}
try {
    $__occLinks = db()->query('SELECT title, slug FROM occasions WHERE active = 1 ORDER BY sort, id LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $__occLinks = [];
}
$shopHours = setting('shop_hours', '');
$shopEmail = setting('shop_email', '');

/* ---------- W97-fixA (A9): версионирование JS + условная загрузка ----------
   1) .htaccess отдаёт .js с Cache-Control immutable на год — БЕЗ версий в URL
      вернувшиеся посетители месяцами сидят на старом JS. Каждой script-ссылке —
      ?v={md5_file 8 символов} (тот же паттерн, что head.php/T4 для CSS; считаем
      один раз здесь, @-guard: пропавший файл → пустая версия, страница живёт).
   2) Условная загрузка по типу страницы (REQUEST_URI + переменная страницы
      в скоупе require): каталог-фильтры и форма заказа — только главная
      (#catalogGrid/#orderForm рендерит index.php); галерея/лайтбокс — только
      страница товара (product.php; [data-lightbox-trigger] живёт там).
      five.js/nilov.js/cart.js, cart-ui.js, cart-cta.js и cookie-banner нужны на всех страницах
      (js-ready, drawer, редирект поиска с вторичных страниц) — без условий. */
$__vjs = static function (string $name): string {
    return substr((string)@md5_file(__DIR__ . '/../js/' . $name), 0, 8);
};
$__nfPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__nfIsHome = ($__nfPath === '/' || $__nfPath === '/index.php');
$__nfIsProduct = (bool)preg_match('#^/product(/|$)#', $__nfPath)
    || (isset($product) && is_array($product));
?>
<footer class="site-footer" id="contacts">
  <?php /* W96-fix3b (D2): 4 колонки — бренд / каталог / поводы / контакты.
     Реквизиты и юр-ссылки — в нижней строке (как раньше по содержанию).
     data-tab/data-chip у ссылок каталога обрабатывает js/five.js (делегирование
     по всей странице) — клик из футера включает вкладку/чип на главной. */ ?>
  <div class="wrap fc-footer__grid">
    <div class="fc-footer__brand">
      <p class="site-footer__name">
        <svg width="22" height="22" viewBox="0 0 32 32" style="color:var(--pink,#ff4ea2);flex:none" aria-hidden="true">
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor"/>
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(72 16 16)"/>
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(144 16 16)"/>
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(216 16 16)"/>
          <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(288 16 16)"/>
          <circle cx="16" cy="16" r="3.2" style="fill:var(--amber,#f5b301)"/>
        </svg>
        <?= e($siteName) ?>
      </p>
      <p class="fc-footer__about"><?= e(setting('footer_about', 'Свежие букеты с доставкой по Санкт-Петербургу в день заказа')) ?></p>
      <?php if ($waOn || $tgOn || $vkOn || $maxOn || $igOn || $emailOn): ?>
      <p class="site-footer__messengers">
        <?php if ($waOn): ?><a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $whatsapp)) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php if ($tgOn): ?><a href="https://t.me/<?= e($telegram) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
        <?php if ($vkOn): ?><a href="<?= e(safe_url(preg_match('#^https?://#i', setting('shop_vk')) ? setting('shop_vk') : 'https://vk.com/' . ltrim(setting('shop_vk'), '/'))) ?>" target="_blank" rel="noopener me">VK</a><?php endif; ?>
        <?php if ($maxOn): ?><a href="<?= e(safe_url(setting('shop_max_link'))) ?>" target="_blank" rel="noopener">MAX</a><?php endif; ?>
        <?php if ($igOn): ?>
          <a href="<?= e(safe_url(setting('shop_instagram'))) ?>" target="_blank" rel="noopener">Instagram*</a>
        <?php endif; ?>
        <?php if ($emailOn): ?><a href="mailto:<?= e(setting('shop_email')) ?>"><?= e(setting('shop_email')) ?></a><?php endif; ?>
      </p>
      <?php if ($igDisclaimer): ?>
      <p style="font-size:.72rem;color:rgba(255,255,255,.55);margin-top:4px">* Instagram принадлежит Meta, признанной экстремистской организацией, деятельность которой запрещена на территории РФ.</p>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <nav class="fc-footer__col" aria-label="Каталог">
      <p class="fc-footer__heading">Каталог</p>
      <ul class="fc-footer__links">
        <?php /* W97-fixB3b (B3b-1e): категории — на посадочные /category/{slug}
               (SEO: собственный URL вместо вкладки без индексации; data-tab убран —
               это больше не JS-переключатель). «Хиты продаж» — как было, data-chip. */ ?>
        <?php foreach ($__footerCats as $__fc): ?>
        <li><a href="/category/<?= e(rawurlencode(slugify((string)$__fc['name']))) ?>"><?= e($__fc['name']) ?></a></li>
        <?php endforeach; ?>
        <li><a href="/#catalog" data-chip="hit"><?= e(setting('section_hits_title', 'Хиты продаж')) ?></a></li>
      </ul>
    </nav>
    <nav class="fc-footer__col" aria-label="Поводы">
      <p class="fc-footer__heading">Поводы</p>
      <?php if ($__occLinks !== []): ?>
      <ul class="fc-footer__links">
        <?php foreach ($__occLinks as $__o): ?>
        <li><a href="/occasion/<?= e(rawurlencode($__o['slug'])) ?>"><?= e((string)preg_replace('/\s+в Санкт-Петербурге$/u', '', (string)$__o['title'])) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?><p class="fc-footer__about">Скоро добавим</p><?php endif; ?>
    </nav>
    <div class="fc-footer__col fc-footer__contacts">
      <p class="fc-footer__heading">Контакты</p>
      <ul class="fc-footer__links">
        <?php if ($phone !== ''): ?><li><a class="site-footer__phone" href="tel:+<?= e($phoneDigits) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($shopHours !== ''): ?><li><span class="fc-footer__muted"><?= e($shopHours) ?></span></li><?php endif; ?>
        <?php if ($address !== ''): ?><li><span class="fc-footer__muted"><?= e($address) ?></span></li><?php endif; ?>
        <?php if ($shopEmail !== ''): ?><li><a href="mailto:<?= e($shopEmail) ?>"><?= e($shopEmail) ?></a></li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="wrap">
    <?php /* Реквизиты продавца (152-ФЗ): только если владелец заполнил legal_* */ ?>
    <?php
      $legalType = mb_strtolower(trim(setting('legal_subject_type', '')));
      $legalName = setting('legal_name', '');
      $legalNum = setting('legal_number', '');
      $legalAddr = setting('legal_address', '');
      $legalInn = setting('legal_inn', '');
    ?>
    <?php if ($legalName !== '' && $legalNum !== ''): ?>
    <p class="fc-footer__legal"><?= e($legalName) ?><?= $legalInn !== '' ? ' · ИНН ' . e($legalInn) : '' ?> · <?= e($legalType === 'ip' ?  'ОГРНИП' : 'ОГРН') ?> <?= e($legalNum) ?><?= $legalAddr !== '' ? ' · ' . e($legalAddr) : '' ?></p>
    <?php endif; ?>
    <span>© <?= date('Y') ?> <?= e($siteName) ?><?= setting('feature_track_link','1')==='1' ? ' · <a href="/track">Где мой заказ?</a>' : '' ?> · <a href="/policy">Политика обработки персональных данных</a> · <a href="/offer">Оферта</a> · <a href="#" onclick="if(window.cookieSettings){window.cookieSettings();}return false">Настройки cookie</a></span>
  </div>
</footer>

<?php /* W99-fixG (G13): aria-current="page" на активной ссылке таббара. Роутинга
       в partial нет — матчим REQUEST_URI-префиксами (переменная $__nfPath выше):
       главная и /product|/category → «Каталог»; /occasion → «Поводы»;
       /track|/policy|/offer → «Контакты»; /order-thanks → «Заказать». */ ?>
<?php
$__mnavActive = '';
if (preg_match('#^/occasion(\.php)?(/|$)#', $__nfPath)) { $__mnavActive = 'occasions'; }
elseif (preg_match('#^/(track|policy|offer)(\.php)?(/|$)#', $__nfPath)) { $__mnavActive = 'contacts'; }
elseif (preg_match('#^/order-thanks(\.php)?(/|$)#', $__nfPath)) { $__mnavActive = 'order'; }
elseif ($__nfIsHome || preg_match('#^/(product|category)(\.php)?(/|$)#', $__nfPath)) { $__mnavActive = 'catalog'; }
?>
<nav class="mnav" aria-label="Мобильная навигация">
  <?php /* W96-fix3b (D4): «Каталог» — иконка-грид (сердце неверно семантически) */ ?>
  <a href="/#catalog"<?= $__mnavActive === 'catalog' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Каталог</a>
  <?php /* W96 (5cv): «Как работаем» → «Поводы» (how-it-works на витрине больше нет) */ ?>
  <a href="/#occasions"<?= $__mnavActive === 'occasions' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>Поводы</a>
  <a href="/#contacts"<?= $__mnavActive === 'contacts' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1L6.6 10.8z"/></svg>Контакты</a>
  <a href="/#order"<?= $__mnavActive === 'order' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h13l-1.5 8.5H7.2L5 4H2"/><circle cx="8.5" cy="19" r="1.4"/><circle cx="14.5" cy="19" r="1.4"/></svg>Заказать</a>
</nav>

<div class="cart-panel" id="cartPanel" hidden>
  <div class="cart-panel__backdrop" id="cartBackdrop"></div>
  <aside class="cart-panel__drawer" role="dialog" aria-modal="true" aria-label="Корзина">
    <div class="cart-panel__head">
      <?php /* W97-fixB3b (B3b-5г): заголовок корзины — вне H-контура (в drawer он
             рядом с h1/h2 страницы и портит иерархию заголовков; селекторы CSS —
             только по классу .cart-panel__title, тег безопасно сменён) */ ?>
      <div class="cart-panel__title"><?= e(setting('cart_title', 'Корзина')) ?></div>
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
         W100-fixH1 (I15): смена семантики cart_total_note — ПУСТОЕ значение в БД больше
         НЕ прячет сноску: пусто = автотекст из зон доставки (мин–макс), непустое =
         кастом владельца как есть. Скрыть автотекст можно только обнулив зоны. */ ?>
      <?php
        $znMin = null; $znMax = null;
        try {
          $zr = db()->query('SELECT MIN(price) mn, MAX(price) mx FROM delivery_zones')->fetch();
          $znMin = (int)($zr['mn'] ?? 0); $znMax = (int)($zr['mx'] ?? 0);
        } catch (Throwable $e) { $znMin = 0; $znMax = 0; }
        $totalNote = trim(setting('cart_total_note', ''));
        if ($totalNote === '') {
            $totalNote = ($znMax > 0)
              ? sprintf('Доставка по вашему району — от %s до %s ₽, точная сумма — при оформлении', $znMin === 0 ? '0' : formatSum($znMin), formatSum($znMax))
              : '';
        }
      ?>
      <?php if ($totalNote !== ''): ?><p class="cart-panel__note" style="font-size:.76rem;color:var(--ink-soft);margin:2px 0 0"><?= e($totalNote) ?></p><?php endif; ?>
      <button type="button" class="btn btn--accent" id="cartCheckout" disabled><?= e(setting('cart_checkout_text', 'Оформить заказ')) ?></button>
      <button type="button" class="btn btn--outline cart-panel__continue" id="cartContinue"><?= e(setting('cart_continue_text', 'Продолжить покупки')) ?></button>
    </div>
  </aside>
</div>

<script src="/js/cart.js?v=<?= e($__vjs('cart.js')) ?>"></script>
<script>window.UPSELL_LIMIT = <?= max(1, min(6, (int) setting('upsell_limit', '3'))) ?>; window.UPSELL_ENABLED = <?= setting('upsell_enabled', '1') === '1' ? 1 : 0 ?>; window.UPSELL_CATEGORIES = <?= json_encode(array_filter(array_map('trim', explode(',', setting('upsell_categories', ''))))) ?>;</script>
<script src="/js/cart-ui.js?v=<?= e($__vjs('cart-ui.js')) ?>"></script>
<?php if (setting('feature_favicon_badge', '1') === '1'): ?><script src="/js/favicon-badge.js?v=<?= e($__vjs('favicon-badge.js')) ?>"></script><?php endif; ?>
<script src="/js/cart-cta.js?v=<?= e($__vjs('cart-cta.js')) ?>"></script>
<?php /* A9: #orderForm и #catalogGrid/#catalogTabs есть только на главной (index.php) —
         на вторичных страницах скрипты self-guard'ом возвращались сразу, теперь
         их просто не грузим. */ ?>
<?php if ($__nfIsHome): ?>
<script src="/js/order-form.js?v=<?= e($__vjs('order-form.js')) ?>"></script>
<script src="/js/catalog-filter.js?v=<?= e($__vjs('catalog-filter.js')) ?>"></script>
<?php endif; ?>
<?php /* W96 (5cv): город-бар, карусели, чипы цен, поиск — поверх catalog-filter.js */ ?>
<script src="/js/five.js?v=<?= e($__vjs('five.js')) ?>"></script>
<?php /* A9: галерея/лайтбокс — только страница товара (разметку рендерит product.php) */ ?>
<?php if ($__nfIsProduct): ?>
<script src="/js/product-gallery.js?v=<?= e($__vjs('product-gallery.js')) ?>"></script>
<script src="/js/lightbox.js?v=<?= e($__vjs('lightbox.js')) ?>"></script>
<?php endif; ?>
<?php /* W97-fixA (A9): js/lazy-images.js — dead code (ищет img[data-src], которых
         нет нигде в репо — проверено rg "data-src" перед удалением); подключение
         убрано, файл в /js оставлен без изменений. */ ?>
<script src="/js/reveal.js?v=<?= e($__vjs('reveal.js')) ?>"></script>
<script src="/js/nilov.js?v=<?= e($__vjs('nilov.js')) ?>" defer></script>
<script>window.COOKIE_BANNER_CONFIG = {
  text: <?= json_encode(sanitize_rich_text(setting('cookie_banner_text', 'Сайт использует cookie и Яндекс.Метрику для работы и анализа трафика. Подробнее — в <a href="/policy" target="_blank" rel="noopener">Политике обработки персональных данных</a>.'), 260), JSON_UNESCAPED_UNICODE) ?>,
  accept: <?= json_encode(setting('cookie_accept_text', 'Принять'), JSON_UNESCAPED_UNICODE) ?>,
  reject: <?= json_encode(setting('cookie_reject_text', 'Только необходимые'), JSON_UNESCAPED_UNICODE) ?>
};</script>
<script src="/js/cookie-banner.js?v=<?= e($__vjs('cookie-banner.js')) ?>"></script>
