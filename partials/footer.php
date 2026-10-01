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
/* W106-E1: /checkout.php — вторая страница с формой заказа (#orderForm рендерит
   partials/order-form.php) — order-form.js нужен и ей; каталог-фильтры —
   по-прежнему только главная (#catalogGrid там нет). */
$__nfIsCheckout = (bool)preg_match('#^/checkout(\.php)?$#', $__nfPath);
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
      <p class="fc-footer__about"><?= e(setting('footer_about', 'Свежие букеты с доставкой по всему Санкт-Петербургу')) /* W104-λ (C4-T4): убрано «в день заказа» — эхо SEO-блока прямо над футером */ ?></p>
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
      <?php /* W106-C1 (P0-2): селектор города — тихо в футере (мобильная
             точка выбора: город шапки на ≤899px скрыт). Тот же компонент
             .fc-city, что в шапке — js/five.js cityMenu() слушает оба. */ ?>
      <div class="fc-city fc-city--footer">
        <button type="button" class="fc-city__btn" aria-expanded="false" aria-haspopup="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>
          <span class="fc-city__label"><?= e(setting('city_label', 'Санкт-Петербург')) ?></span>
          <svg class="fc-city__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="fc-city-menu fc-city-menu--up" hidden>
          <?php /* D-d1 (P1, жюри): та же содержательная панель, что в шапке
                 (js/five.js cityMenu() слушает оба). */ ?>
          <p class="fc-city-menu__note">Доставляем по Санкт-Петербургу и пригородам</p>
          <button type="button" class="fc-city-menu__opt is-current" data-city="<?= e(setting('city_label', 'Санкт-Петербург')) ?>"><?= e(setting('city_label', 'Санкт-Петербург')) ?>&nbsp;<span aria-hidden="true">✓</span></button>
          <a class="fc-city-menu__link" href="#delivery">Зоны и цены<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          <a class="fc-city-menu__other" href="#contacts"><?= e(setting('citybar_no_text', 'Другой город — самовывоз или обсудим по телефону')) ?></a>
        </div>
      </div>
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
    <span>© <?= date('Y') ?> <?= e($siteName) ?><?= setting('feature_track_link','1')==='1' ? ' · <a href="/track">Где мой заказ?</a>' : '' ?> · <a href="/policy">Политика обработки персональных данных</a> · <a href="/offer">Оферта</a> · <a href="/policy" onclick="if(window.cookieSettings){window.cookieSettings();}return false">Настройки cookie</a></span>
  </div>
</footer>

<?php /* W99-fixG (G13): aria-current="page" на активной ссылке таббара. Роутинга
       в partial нет — матчим REQUEST_URI-префиксами (переменная $__nfPath выше):
       главная и /product|/category → «Каталог».
       S3 (v2026.3): состав панели — как у 5cv: Каталог / Поиск / Корзина с суммой /
       WhatsApp (фолбэк без WhatsApp — звонок). Тумблер mobilebar_enabled. */ ?>
<?php
$__mnavActive = '';
if (preg_match('#^/occasion(\.php)?(/|$)#', $__nfPath)) { $__mnavActive = 'occasions'; }
elseif (preg_match('#^/(track|policy|offer)(\.php)?(/|$)#', $__nfPath)) { $__mnavActive = 'contacts'; }
elseif (preg_match('#^/order-thanks(\.php)?(/|$)#', $__nfPath) || $__nfIsCheckout) { $__mnavActive = 'order'; }
elseif ($__nfIsHome || preg_match('#^/(product|category)(\.php)?(/|$)#', $__nfPath)) { $__mnavActive = 'catalog'; }
$__mnavOn = setting('mobilebar_enabled', '1') === '1';
$__mnavWa = setting('wa_enabled', '1') === '1' ? trim(setting('shop_whatsapp', '')) : '';
?>
<?php if ($__mnavOn): ?>
<nav class="mnav" aria-label="Мобильная навигация">
  <a href="/#catalog"<?= $__mnavActive === 'catalog' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Каталог</a>
  <?php /* S3: «Поиск» — фокус в поисковую пилюлю шапки (#fcSearch; на вторичных
         страницах она там же) — обработчик в js/five.js (делегирование .mnav__search). */ ?>
  <button type="button" class="mnav__search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>Поиск</button>
  <?php /* S3: «Корзина» — открывает drawer (контракт #cartToggle в cart-ui.js —
         дублируем клик), сумма заказа — #mnavCartSum рядом с подписью. */ ?>
  <button type="button" class="mnav__cart" id="mnavCartBtn" aria-label="Открыть корзину">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h13l-1.5 8.5H7.2L5 4H2"/><circle cx="8.5" cy="19" r="1.4"/><circle cx="14.5" cy="19" r="1.4"/></svg>Корзина<span class="mnav__cart-sum" id="mnavCartSum" hidden></span>
  </button>
  <?php if ($__mnavWa !== ''): ?>
  <a class="mnav__wa" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $__mnavWa)) ?>" target="_blank" rel="noopener" aria-label="Написать в WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18.12c-1.5 0-2.97-.4-4.26-1.16l-.3-.18-3.12.82.83-3.04-.2-.31a8.26 8.26 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24s8.24 3.7 8.24 8.24-3.7 8.25-8.17 8.25zm4.52-6.16c-.25-.12-1.47-.72-1.69-.8-.23-.09-.4-.13-.56.12-.17.25-.64.8-.8.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.22-.16-.47-.28z"/></svg>WhatsApp
  </a>
  <?php else: ?>
  <a class="mnav__wa" href="tel:<?= e(preg_replace('/\D/', '', $phone)) ?>" aria-label="Позвонить">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Позвонить
  </a>
  <?php endif; ?>
</nav>
<?php endif; ?>

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
    <?php /* S3 (v2026.3): прогресс до бесплатной доставки (порог free_delivery_threshold,
           рендер и обновление — js/cart-ui.js по контракту #cartFreeBar/#cartFreeText). */ ?>
    <?php
      $freeThreshold = (int)setting('free_delivery_threshold', '0');
      if (setting('cart_free_progress_enabled', '1') === '1' && $freeThreshold > 0):
    ?>
    <div class="cart-free" id="cartFreeBar" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $freeThreshold ?>" aria-valuenow="0" aria-label="Прогресс до бесплатной доставки">
      <div class="cart-free__track"><div class="cart-free__fill" id="cartFreeFill"></div></div>
      <p class="cart-free__text" id="cartFreeText"></p>
    </div>
    <?php endif; ?>
    <div class="cart-panel__items" id="cartItems"></div>
    <p class="cart-panel__empty" id="cartEmpty"><?= e(setting('cart_empty_text', 'Корзина пуста — выберите букет в каталоге')) ?></p>
    <?php /* S3 (v2026.3): бесплатные допы — открытка с текстом (0 ₽) и подкормка
           Chrysal (0 ₽). Состояние — localStorage корзины (cart-ui.js), открытка
           синхронизируется с полем «Текст открытки» формы заказа; в заказ уходит
           через extras (api/orders.php) и card_text. */ ?>
    <?php
      $postcardOn = setting('cart_extra_postcard_enabled', '1') === '1';
      $chrysalOn = setting('cart_extra_chrysal_enabled', '1') === '1';
    ?>
    <?php if ($postcardOn || $chrysalOn): ?>
    <div class="cart-extras" id="cartExtras">
      <p class="cart-extras__title"><?= e(setting('cart_extras_title', 'Дополните букет')) ?></p>
      <?php if ($postcardOn): ?>
      <div class="cart-extra<?= $chrysalOn ? ' cart-extra--last' : '' ?>" id="cartExtraPostcard">
        <label class="cart-extra__check">
          <input type="checkbox" id="cartExtraPostcardOn">
          <span class="cart-extra__info">
            <span class="cart-extra__name"><?= e(setting('cart_extra_postcard_title', 'Открытка с вашим текстом')) ?> <em class="cart-extra__price">0&nbsp;₽</em></span>
            <span class="cart-extra__note"><?= e(setting('cart_extra_postcard_text', 'Напишем от руки и вложим в букет')) ?></span>
          </span>
        </label>
        <div class="cart-extra__text" id="cartExtraPostcardWrap" hidden>
          <textarea id="cartExtraPostcardText" maxlength="500" rows="2" placeholder="С днём рождения! — от Евгения" aria-label="Текст открытки"></textarea>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($chrysalOn): ?>
      <div class="cart-extra cart-extra--last" id="cartExtraChrysal">
        <label class="cart-extra__check">
          <input type="checkbox" id="cartExtraChrysalOn">
          <span class="cart-extra__info">
            <span class="cart-extra__name"><?= e(setting('cart_extra_chrysal_title', 'Подкормка Chrysal')) ?> <em class="cart-extra__price">0&nbsp;₽</em></span>
            <span class="cart-extra__note"><?= e(setting('cart_extra_chrysal_text', 'Питательный гель — букет простоит дольше')) ?></span>
          </span>
        </label>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="cart-upsell" id="cartUpsell" hidden>
      <p class="cart-upsell__title"><?= e(setting('upsell_title', 'Добавьте к букету')) ?></p>
      <div class="cart-upsell__items" id="cartUpsellItems"></div>
    </div>
    <div class="cart-panel__foot">
      <?php /* Промокод (критик functional top#3): применяется по серверной проверке /api/promo */ ?>
      <?php if (setting('feature_promo', '1') === '1'): ?>
      <div class="cart-promo" id="cartPromo">
        <input type="text" id="cartPromoInput" maxlength="32" placeholder="Промокод" aria-label="Промокод" autocomplete="off" style="flex:1;min-width:0;padding:10px 12px;border:1px solid var(--line);border-radius:var(--radius,12px);font:inherit">
        <button type="button" id="cartPromoApply" class="cart-promo__btn" style="min-height:44px;padding:11px 16px;border:1px solid var(--line);border-radius:10px;background:#fff;font:600 .85rem var(--font-ui);cursor:pointer">Применить</button>
      </div>
      <p id="cartPromoMsg" class="cart-promo__msg" style="margin:4px 0 0;font-size:.8125rem;color:var(--ink-soft)" aria-live="polite"></p> <?php /* W105-7fix1 (тип-критик 7-b P2e): 12.8→13px — микротексты корзины ≥13 */ ?>
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
      <?php if ($totalNote !== ''): ?><p class="cart-panel__note" style="font-size:.8125rem;color:var(--ink-soft);margin:2px 0 0"><?= e($totalNote) /* W105-7fix1 (7-b P2e): 12.16→13px — сноска «Итого» в паре с promo-msg */ ?></p><?php endif; ?>
      <button type="button" class="btn btn--accent" id="cartCheckout" disabled><?= e(setting('cart_checkout_text', 'Оформить заказ')) ?></button>
      <button type="button" class="btn btn--outline cart-panel__continue" id="cartContinue"><?= e(setting('cart_continue_text', 'Продолжить покупки')) ?></button>
    </div>
  </aside>
</div>

<?php /* W105-6fix1: хвост ряда каталога — ДОПОЛНИТЕЛЬ последней строки грида
       (.is-tail-feature / CTA-плитка [data-grid-tail]). Грузится на всех
       витрин-страницах до catalog-filter.js (вызов из его apply())
       и до category.js (defer, ниже по документу); без [data-grid-tail] в
       гриде — no-op.
       E-e2 (P0-1, перф-критик волны 3: 9 sync-скриптов в конце body →
       парсер ждёт их скачивания/исполнения, DCL 2.6с): ВСЕ внешние скрипты
       футера — defer. Defer-скрипты исполняются СТРОГО в порядке документа —
       относительный порядок цепочки не меняется (grid-tail → cart → cart-ui
       → favicon-badge → cart-cta → order-form → catalog-filter → five →
       lenis → kinetic → reveal → nilov → glue → petals → cookie-banner).
       Инлайн-конфиги между ними (UPSELL_*, COOKIE_BANNER_CONFIG, NILOV_CONFIG
       в index.php) исполняются на парсинге — раньше отложенных скриптов,
       как и раньше. Никаких document.write и вызовов API соседей на верхнем
       уровне нет (проверено: cart-ui/cart-cta/five только читают глобалы
       ПОСЛЕ своей инициализации). */ ?>
<script src="/js/grid-tail.js?v=<?= e($__vjs('grid-tail.js')) ?>" defer></script>
<script src="/js/cart.js?v=<?= e($__vjs('cart.js')) ?>" defer></script>
<script>window.UPSELL_LIMIT = <?= max(1, min(6, (int) setting('upsell_limit', '3'))) ?>; window.UPSELL_ENABLED = <?= setting('upsell_enabled', '1') === '1' ? 1 : 0 ?>; window.UPSELL_CATEGORIES = <?= json_encode(array_filter(array_map('trim', explode(',', setting('upsell_categories', ''))))) ?>; window.FREE_DELIVERY_FROM = <?= (int)setting('free_delivery_threshold', '0') ?>; window.CART_FREE_PROGRESS_UNDER = <?= json_encode(setting('cart_free_progress_under', 'Добавьте ещё {left} ₽ — и доставка бесплатна'), JSON_UNESCAPED_UNICODE) ?>; window.CART_FREE_PROGRESS_REACHED = <?= json_encode(setting('cart_free_progress_reached', 'Доставка бесплатно 🎉'), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/js/cart-ui.js?v=<?= e($__vjs('cart-ui.js')) ?>" defer></script>
<?php if (setting('feature_favicon_badge', '1') === '1'): ?><script src="/js/favicon-badge.js?v=<?= e($__vjs('favicon-badge.js')) ?>" defer></script><?php endif; ?>
<script src="/js/cart-cta.js?v=<?= e($__vjs('cart-cta.js')) ?>" defer></script>
<?php /* S3 (v2026.3): «Купить в 1 клик» — модалка имя+телефон → POST /api/orders
       (кнопки [data-oneclick] на карточках каталога/каруселей и PDP). */ ?>
<script src="/js/oneclick.js?v=<?= e($__vjs('oneclick.js')) ?>" defer></script>
<?php /* A9: #orderForm живёт на главной и /checkout.php (W106-E1 — частичный
       order-form.php); #catalogGrid/#catalogTabs — только главная. */ ?>
<?php if ($__nfIsHome || $__nfIsCheckout): ?>
<script src="/js/order-form.js?v=<?= e($__vjs('order-form.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($__nfIsHome): ?>
<script src="/js/catalog-filter.js?v=<?= e($__vjs('catalog-filter.js')) ?>" defer></script>
<?php endif; ?>
<?php /* S5 (ЭТАП 5): QUICK VIEW — модалка быстрого просмотра (главная +
       посадочные категории): слайдер ракурсов, состав поштучно, бесплатная
       открытка, Кризал 0 ₽ и сладкие подарки (живые товары из БД — их чекбоксы
       добавляются в корзину вместе с букетом). Тумблер — feature_quickview. */ ?>
<?php $__nfIsCategory = (bool)preg_match('#^/category(/|$)#', $__nfPath); ?>
<?php if (setting('feature_quickview', '1') === '1' && ($__nfIsHome || $__nfIsCategory)): ?>
<?php
$__qvSweets = [];
try {
    $__qvRows = db()->query("SELECT id, name, price, image FROM products
        WHERE is_active = 1 AND (tags LIKE '%сладости%' OR tags LIKE '%сладост%') AND image != ''
        ORDER BY sort, id LIMIT 3")->fetchAll();
    foreach ($__qvRows as $__qvR) {
        $__qvSweets[] = [
            'id' => (int)$__qvR['id'],
            'name' => (string)$__qvR['name'],
            'price' => productPrice($__qvR) ?: (int)$__qvR['price'],
            'image' => static_img_v('/img/products/' . rawurlencode((string)$__qvR['image'])),
        ];
    }
} catch (Throwable $__qvE) {
    $__qvSweets = [];
}
?>
<script>window.NF_QUICKVIEW = {
  enabled: 1,
  tocart: <?= json_encode(setting('card_btn_cart', 'В корзину'), JSON_UNESCAPED_UNICODE) ?>,
  oneclick: <?= json_encode(mb_strimwidth(trim((string)preg_replace('/^купить\s+(в\s+)?/iu', '', (string)setting('card_btn_oneclick', 'Купить в 1 клик'))) ?: 'В 1 клик', 0, 12, '…'), JSON_UNESCAPED_UNICODE) ?>,
  hitText: <?= json_encode(setting('badge_hit_text', 'Хит'), JSON_UNESCAPED_UNICODE) ?>,
  splitChip: <?= json_encode(setting('split_chip_text', 'Сплит'), JSON_UNESCAPED_UNICODE) ?>,
  compTitle: <?= json_encode(setting('quickview_comp_title', 'Состав'), JSON_UNESCAPED_UNICODE) ?>,
  extrasTitle: <?= json_encode(setting('quickview_extras_title', 'Дополнить букет'), JSON_UNESCAPED_UNICODE) ?>,
  chrysalText: <?= json_encode(setting('quickview_chrysal_text', 'Кризал — подкормка для свежести'), JSON_UNESCAPED_UNICODE) ?>,
  cardLabel: <?= json_encode(setting('quickview_card_label', 'Открытка в подарок — напишем от руки'), JSON_UNESCAPED_UNICODE) ?>,
  cardPlaceholder: <?= json_encode(setting('quickview_card_placeholder', 'Текст открытки'), JSON_UNESCAPED_UNICODE) ?>,
  fullLink: <?= json_encode(setting('quickview_full_link', 'Полное описание букета'), JSON_UNESCAPED_UNICODE) ?>,
  sweets: <?= json_encode($__qvSweets, JSON_UNESCAPED_UNICODE) ?>
};</script>
<script src="/js/quickview.js?v=<?= e($__vjs('quickview.js')) ?>" defer></script>
<?php endif; ?>
<?php /* W96 (5cv): город-бар, карусели, чипы цен, поиск — поверх catalog-filter.js */ ?>
<script src="/js/five.js?v=<?= e($__vjs('five.js')) ?>" defer></script>
<?php /* A9: галерея/лайтбокс — только страница товара (разметку рендерит product.php) */ ?>
<?php if ($__nfIsProduct): ?>
<script src="/js/product-gallery.js?v=<?= e($__vjs('product-gallery.js')) ?>" defer></script>
<script src="/js/lightbox.js?v=<?= e($__vjs('lightbox.js')) ?>" defer></script>
<?php endif; ?>
<?php /* W97-fixA (A9): js/lazy-images.js — dead code (ищет img[data-src], которых
         нет нигде в репо — проверено rg "data-src" перед удалением); подключение
         убрано, файл в /js оставлен без изменений. */ ?>
<?php /* S7: эффект-слой отключён — lenis (smooth-scroll) и kinetic
         (анимация слов) больше не грузятся: стерильный ритейл 5cv
         без спецэффектов (motion-w104.css тоже отключён в head.php).
         reveal.js ОСТАЁТСЯ — контракт .reveal/.reveal--visible
         (без базового opacity:0 в css он инертен; watchdog страхует). */ ?>
<script src="/js/reveal.js?v=<?= e($__vjs('reveal.js')) ?>" defer></script>
<script src="/js/nilov.js?v=<?= e($__vjs('nilov.js')) ?>" defer></script>
<?php /* W105-6fix2: NBSP-склейка «2 500 ₽» + предлоги — после nilov.js, до лепестков
       (админ не подключает footer.php — там склейка не нужна) */ ?>
<script src="/js/glue.js?v=<?= e($__vjs('glue.js')) ?>" defer></script>
<script>window.COOKIE_BANNER_CONFIG = {
  text: <?= json_encode(sanitize_rich_text(setting('cookie_banner_text', 'Сайт использует cookie и Яндекс.Метрику для работы и анализа трафика. Подробнее — в <a href="/policy" target="_blank" rel="noopener">Политике обработки персональных данных</a>.'), 260), JSON_UNESCAPED_UNICODE) ?>,
  accept: <?= json_encode(setting('cookie_accept_text', 'Принять'), JSON_UNESCAPED_UNICODE) ?>,
  reject: <?= json_encode(setting('cookie_reject_text', 'Только необходимые'), JSON_UNESCAPED_UNICODE) ?>
};</script>
<script src="/js/cookie-banner.js?v=<?= e($__vjs('cookie-banner.js')) ?>" defer></script>
