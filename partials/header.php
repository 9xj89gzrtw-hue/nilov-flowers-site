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
/* S3 (v2026.3): инфо-бар преимуществ над шапкой — три сегмента из настроек
   (тексты/тумблер), между ними точка-разделитель. Пустые сегменты не печатаются. */
$infobarOn = setting('infobar_enabled', '1') === '1';
$infobarItems = array_values(array_filter([
    trim(setting('infobar_text_1', '')),
    trim(setting('infobar_text_2', '')),
    trim(setting('infobar_text_3', '')),
], fn($t) => $t !== ''));
/* S3: прямые кнопки WhatsApp/Telegram в шапке (глобальные тумблеры + контакты) */
$headerWa = setting('wa_enabled', '1') === '1' ? trim(setting('shop_whatsapp', '')) : '';
$headerTg = setting('tg_enabled', '1') === '1' ? trim(setting('shop_telegram', '')) : '';
/* S3: список районов для выпадающей панели города (зоны доставки из БД) */
try {
    $headerZones = db()->query('SELECT name FROM delivery_zones ORDER BY sort, id LIMIT 10')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $headerZones = [];
}
?>
<?php /* a11y-критик re-check: skip-link в общем header.php — есть на каждой витрина-страница
   (home, product, offer, policy, track), а не только на главной.
   W97-fixB3a (B3a-5): на ГЛАВНОЙ index.php печатает skip-link сам — до город-бара,
   первым элементом DOM (первый фокус с Tab); флаг $skipLinkRendered не даёт продублировать. */ ?>
<?php if (empty($skipLinkRendered)): ?>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<?php endif; ?>
<?php if ($infobarOn && $infobarItems !== []): ?>
<div class="fc-infobar" role="note">
  <div class="wrap fc-infobar__inner">
    <?php foreach ($infobarItems as $i => $infobarText): ?>
      <?= $i > 0 ? '<span class="fc-infobar__dot" aria-hidden="true"></span>' : '' ?>
      <span class="fc-infobar__item"><?= e($infobarText) ?></span>
    <?php endforeach; ?>
  </div>
</div>
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
    <?php /* S4: тяжёлая ink-кнопка «Каталог» УБРАНА из ряда шапки (чистый
       коммерческий ряд 5cv: лого → поиск → телефон → корзина). Пункт
       «Каталог» живёт в панели бургера (мобиль) и в чипсах под баннером;
       настройка catalog_btn_text остаётся в БД/админке. */ ?>
    <?php /* Поиск по каталогу (W96-fix1/F3): action="/" + name="q" — без JS
       нативный submit уводит на главную с запросом; js/five.js на главной фильтрует
       живьём (морфология: «розы» → «роз»), со вторичных страниц — редирект /?q=…#catalog */ ?>
    <form class="fc-search" role="search" action="/" method="get">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" id="fcSearch" name="q" placeholder="<?= e(setting('search_placeholder', 'Розы, пионы, тюльпаны…')) ?>" aria-label="Поиск по букетам">
    </form>
    <?php /* W106-C1 (дизайн-дир P0-2): город тихо живёт в шапке — компактная
       кнопка-дропдаун вместо гео-топбара (вопрос «ваш город?» при первом
       визите больше не задаётся). Разметку слушает js/five.js cityMenu().
       Тот же компонент продублирован в футере (partials/footer.php) — на
       мобиле город из шапки скрыт (≤899px), футер остаётся точкой выбора. */ ?>
    <div class="fc-city">
      <button type="button" class="fc-header__city fc-city__btn" aria-expanded="false" aria-haspopup="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>
        <span class="fc-city__label"><?= e(setting('city_label', 'Санкт-Петербург')) ?></span>
        <svg class="fc-city__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="fc-city-menu" hidden>
        <?php /* D-d1 (P1, жюри): панель города содержательная — не одинокий
               «Выбрать другой»: география + вход к зонам/ценам (якорь #delivery —
               группа «Как получить» формы заказа) + честная строка про другой
               город. js/five.js cityMenu() контракт не менялся (.fc-city__btn /
               .fc-city-menu__opt[data-city] / закрытие по клику на любую ссылку).
               S3 (v2026.3): список районов доставки из БД — покупатель видит
               географию до скролла к форме (первые 10 зон, клик — к ценам). */ ?>
        <p class="fc-city-menu__note">Доставляем по Санкт-Петербургу и пригородам</p>
        <?php if ($headerZones !== []): ?>
        <div class="fc-city-menu__zones">
          <?php foreach ($headerZones as $hz): ?>
            <a class="fc-city-menu__zone" href="#delivery" title="Зоны и цены доставки"><?= e((string)preg_replace('/\s*\([^)]*\)/u', '', (string)$hz)) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <button type="button" class="fc-city-menu__opt is-current" data-city="<?= e(setting('city_label', 'Санкт-Петербург')) ?>"><?= e(setting('city_label', 'Санкт-Петербург')) ?>&nbsp;<span aria-hidden="true">✓</span></button>
        <a class="fc-city-menu__link" href="#delivery">Зоны и цены<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        <a class="fc-city-menu__other" href="#contacts"><?= e(setting('citybar_no_text', 'Другой город — самовывоз или обсудим по телефону')) ?></a>
      </div>
    </div>
    <?php if ($headerPhone !== ''): ?><a class="fc-header__phone" href="tel:+<?= e(preg_replace('/\D/', '', $headerPhone)) ?>"><?= e($headerPhone) ?></a><?php endif; ?>
    <?php /* S3 (v2026.3): прямые мессенджеры в ряду иконок шапки — раунд-кнопки
           WhatsApp (фирменный зелёный) и Telegram (фирменный синий), только
           если контакт заполнен и не выключен тумблером. */ ?>
    <?php if ($headerWa !== ''): ?>
    <a class="fc-header__icon fc-header__wa" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $headerWa)) ?>" target="_blank" rel="noopener" aria-label="Написать в WhatsApp" title="Написать в WhatsApp">
      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18.12c-1.5 0-2.97-.4-4.26-1.16l-.3-.18-3.12.82.83-3.04-.2-.31a8.26 8.26 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24s8.24 3.7 8.24 8.24-3.7 8.25-8.17 8.25zm4.52-6.16c-.25-.12-1.47-.72-1.69-.8-.23-.09-.4-.13-.56.12-.17.25-.64.8-.8.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.22-.16-.47-.28z"/></svg>
    </a>
    <?php endif; ?>
    <?php if ($headerTg !== ''): ?>
    <a class="fc-header__icon fc-header__tg" href="https://t.me/<?= e($headerTg) ?>" target="_blank" rel="noopener" aria-label="Написать в Telegram" title="Написать в Telegram">
      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.94 4.6 19.02 19.1c-.22 1-.8 1.24-1.62.77l-4.47-3.3-2.16 2.08c-.24.24-.44.44-.9.44l.32-4.55 8.28-7.48c.36-.32-.08-.5-.56-.18L7.68 13.18l-4.4-1.38c-.96-.3-.98-.96.2-1.42L20.6 3.24c.8-.3 1.5.18 1.34 1.36z"/></svg>
    </a>
    <?php endif; ?>
    <?php /* W106-C1 (моб-критик P1-12): компактный бургер ≤899px с выпадающей
       панелью (паттерн admin-гамбургера W105-b: Escape/тап-вне/клик-по-ссылке
       закрывают; js/five.js burgerMenu()). «Каталог»-иконка из первого ряда
       уходит сюда — ширина ряда на 390px не меняется; поиск и корзина
       (контракты #fcSearch/#cartToggle) не тронуты. */ ?>
    <button type="button" class="fc-burger" id="fcBurger" aria-expanded="false" aria-controls="fcBurgerPanel" aria-label="Открыть меню">
      <span class="fc-burger__bar" aria-hidden="true"></span>
      <span class="fc-burger__bar" aria-hidden="true"></span>
      <span class="fc-burger__bar" aria-hidden="true"></span>
    </button>
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
      <?php /* Кнопка корзины — контракт cart-ui.js, разметку не меняем (CSS перекрасит).
         S3 (v2026.3): под иконкой — динамическая сумма заказа (#cartSum обновляет
         cart-ui.js вместе с #cartCount) — «Корзина с бейджем количества и суммы». */ ?>
      <button type="button" class="cart-toggle" id="cartToggle" data-cart-mode="<?= e($cartMode) ?>" aria-label="Корзина" aria-haspopup="dialog" aria-expanded="false">
        <svg class="cart-toggle__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M2.5 3h2l2.6 12.4a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6"/></svg>
        <span class="cart-toggle__count" id="cartCount" hidden>0</span>
        <span class="cart-toggle__sum" id="cartSum" hidden></span>
      </button>
    </div>
  </div>
  <?php /* W106-C1 (P1-12): выпадающая панель бургера — абсолют под шапкой
         (sticky-хедер держит stacking-контекст, z-50); ссылки — абсолютные
         якоря /#…, работают и со вторичных страниц. */ ?>
  <nav class="fc-burger-panel" id="fcBurgerPanel" hidden aria-label="Меню">
    <a href="/#catalog">Каталог</a>
    <a href="/#occasions">Поводы</a>
    <a href="/checkout.php">Заказать букет</a>
    <a href="/#contacts">Контакты</a>
    <?php if ($headerPhone !== ''): ?><a class="fc-burger-panel__phone" href="tel:+<?= e(preg_replace('/\D/', '', $headerPhone)) ?>"><?php /* G-g2 (жюри P1): трубка 16px + hover-подчёркивание — телефон читается действием, а не строкой (стили five.css G-g2) */ ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><?= e($headerPhone) ?></a><?php endif; ?>
  </nav>
</header>
