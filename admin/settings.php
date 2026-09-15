<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/settings-history.php';
ensureAdminUser();
requireAdmin();

/* CSRF: админ-POST без валидного токена — отказ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    $back = $_SERVER['HTTP_REFERER'] ?? '/admin/index.php';
    header('Location: ' . $back);
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['vapid_action']) && !isset($_POST['hist_action'])) {
    $keys = ['shop_name','shop_phone','shop_address','pickup_address','hero_title','hero_subtitle',
        'hero_button_text','hero_button_link','steps_title','step_1','step_2','step_3',
        'guarantees_title','guarantee_1','guarantee_2','guarantee_3',
        'shop_email','shop_hours','shop_vk','shop_max_link','shop_instagram','header_phone','header_address',
        'shop_whatsapp','shop_telegram',
        'upsell_limit','upsell_title','upsell_categories',
        'legal_subject_type','legal_name','legal_number','legal_inn','legal_address','legal_contact_email',
        'vat_rate','yk_shop_id','yk_secret_key','yandex_reviews_id',
        /* SEO главной (критерий 16, P1 аудитора) */
        'seo_title','seo_description','metrika_counter_id',
        'yandex_verification','google_site_verification',
        /* Витринные тексты (критерий 16): бейдж + FAQ редактируются */
        'delivery_badge_text','faq_title',
        /* Критик functional: слоты доставки (тексты построчно) + подписи gift-блока */
        'delivery_slots',
        'reviews_sub',
        'reviews_title', 'reviews_card_text',
        'related_title',
        'faq_q1','faq_a1','faq_q2','faq_a2','faq_q3','faq_a3','faq_q4','faq_a4',
        /* Дедлайн + тексты таймера и empty-state (критерий 16) */
        'order_deadline_hour','order_deadline_minute','countdown_text','countdown_closed_text','countdown_night_text',
        'catalog_empty_title','catalog_empty_hint',
        /* Пороги фильтра цены (критерий 16) */
        'price_filter_low','price_filter_high',
        /* Тексты zone-check (критерий 16) */
        'zone_check_placeholder','zone_check_fallback',
        /* Тексты бегущей ленты (критерий 16) */
        'marquee_1','marquee_2','marquee_3','marquee_4',
        /* Заголовки секций (критерий 16) */
        'catalog_title','catalog_subtitle','order_title',
        /* Бейджи карточек + cookie-баннер (критерий 16) */
        'badge_sale_text','badge_urgent_text',
        'cookie_banner_text','cookie_accept_text','cookie_reject_text',
        /* Тексты чекаута и корзины (критерий 16) */
        'pickup_option_text','delivery_hint_text','submit_button_text','submit_nopay_text',
        'cart_title','cart_empty_text','cart_checkout_text','cart_continue_text','cart_total_note',
        /* Лейблы полей формы заказа (критерий 16, аудит-хардкоды) */
        'form_name_label','form_phone_label','form_email_label','form_delivery_zone_label',
        'fieldset_delivery_legend','fieldset_payment_legend','pay_online_label','pay_cash_label',
        /* Hero-eyebrow (критерий 16, P2 аудита) */
        'hero_eyebrow',
        /* Порог бесплатной доставки (критерий 13/16): 0 = выключено */
        'free_delivery_threshold'];
    $values = [];
    /* КЛАСС-ЗАЩИТА (критик-2): ключ из allowlist, которого нет в отправленной форме,
       НЕ должен затираеться пустотой. Текстовые поля: пишем только если ключ реально пришёл
       (пустое поле всё равно приходит '' — очистка работает). Чекбоксы: снимок галки =
       отсутствие в POST, поэтому сверяем со списком отрендеренных (cb_rendered). */
    $cbRendered = array_map('trim', explode(',', (string)($_POST['cb_rendered'] ?? '')));
    $cbTrackAll = ($cbRendered === [''] || $cbRendered === []); // JS выключен → старое поведение
    foreach ($keys as $k) {
        if (!array_key_exists($k, $_POST)) { continue; }
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }
    /* Чекбоксы: 0 если снят (не пришёл), но ТОЛЬКО для реально отрендеренных в форме */
    foreach (['yk_enabled', 'upsell_enabled', 'hero_text_enabled', 'yandex_reviews_enabled',
              'wa_enabled', 'tg_enabled', 'vk_enabled', 'ig_enabled', 'email_enabled', 'max_enabled',
              'notify_enabled', 'logo_enabled',
              /* Витринные фичи (критерий 16): каждая отключаема из админки */
              'feature_delivery_badge', 'feature_faq', 'feature_countdown', 'feature_price_filter',
              'feature_favorites', 'feature_zone_check', 'feature_track_link', 'feature_favicon_badge', 'feature_webpush',
              /* Критик functional: gift-UX и слоты доставки — отключаемы (критерий 14) */
              'feature_gift_fields', 'feature_delivery_slots', 'feature_gallery',
              /* Промокод в корзине (критик functional top#3) */
              'feature_promo',
              /* Awwwards-design D8: блок «С этим берут» на странице товара */
              'feature_related'] as $cb) {
        if (!$cbTrackAll && !in_array($cb, $cbRendered, true)) { continue; } // не в форме — не трогаем
        $values[$cb] = isset($_POST[$cb]) ? '1' : '0';
    }
    /* cart_mode — select с валидацией всех трёх режимов витрины (header.php: drawer|hybrid|page) */
    if (array_key_exists('cart_mode', $_POST)) {
        $values['cart_mode'] = in_array($_POST['cart_mode'] ?? '', ['drawer', 'hybrid', 'page'], true) ? $_POST['cart_mode'] : 'drawer';
    }
    /* снимок ДО записи — чтобы «Отменить» вернул точное предыдущее состояние (критерий 16) */
    settingsSnapshot('save');
    $hero = saveUpload($_FILES['hero_image'] ?? [], IMG_UPLOADS_DIR);
    if ($hero !== '') {
        deleteImage(setting('hero_image'), IMG_UPLOADS_DIR);
        $values['hero_image'] = $hero;
    }
    $logo = saveUpload($_FILES['logo_image'] ?? [], IMG_UPLOADS_DIR);
    if ($logo !== '') {
        deleteImage(setting('logo_image'), IMG_UPLOADS_DIR);
        $values['logo_image'] = $logo;
    }
    /* Favicon: новый файл заменяет старый; чекбокс-удаление — пустое значение */
    $fav = saveUpload($_FILES['site_favicon'] ?? [], IMG_UPLOADS_DIR);
    if ($fav !== '') {
        deleteImage(setting('site_favicon'), IMG_UPLOADS_DIR);
        $values['site_favicon'] = $fav;
    } elseif (isset($_POST['site_favicon_remove'])) {
        deleteImage(setting('site_favicon'), IMG_UPLOADS_DIR);
        $values['site_favicon'] = '';
    }
    saveSettings($values);
    flash('Настройки сохранены');
    header('Location: /admin/settings.php');
    exit;
}

/* История: «Отменить последнее изменение» / «Вернуть значения по умолчанию» (критерии 16–17).
   PRG-паттерн: обработали → редирект, чтобы форму не перезаписал back-кнопкой. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hist_action'])) {
    if (($_POST['hist_action'] ?? '') === 'undo') {
        $okUndo = settingsUndoLast();
        flash($okUndo ? 'Настройки возвращены к предыдущему изменению' : 'Отменять нечего — история пуста', !$okUndo);
    } elseif (($_POST['hist_action'] ?? '') === 'defaults') {
        settingsResetToDefaults();
        flash('Витринные тексты и тумблеры возвращены к «по умолчанию». Контакты, секретные коды и реквизиты НЕ тронуты.');
    } elseif (($_POST['hist_action'] ?? '') === 'save-defaults') {
        $n = settingsSaveCurrentAsDefaults();
        flash("Текущее состояние сохранено как «по умолчанию» ({$n} пунктов). ↩ Отмена работает и после этого.");
    }
    header('Location: /admin/settings.php');
    exit;
}

$s = allSettings();
function sv(string $k, array $s): string { return e($s[$k] ?? ''); }

adminHeader('Настройки', 'settings');
flash();
?>
<h1>Настройки магазина</h1>
<style>.card[id]{scroll-margin-top:120px}</style>

<?php /* Оглавление настроек (критик-владелец: «9 секций на одной простыне — листать всё»).
       Якоря-чипы, прыжок в один клик, sticky — всегда под рукой. */ ?>
<nav id="top-nav" class="dash-ranges settings-nav" aria-label="Разделы настроек">
  <a href="#s-common">Общие</a>
  <a href="#s-main">Главная</a>
  <a href="#s-look">Вид</a>
  <a href="#s-features">Функции</a>
  <a href="#s-steps">Этапы</a>
  <a href="#s-contacts">Контакты</a>
  <a href="#s-cart">Корзина</a>
  <a href="#s-legal">Реквизиты</a>
  <a href="#s-pay">Оплата</a>
  <a href="#s-guarantees">Гарантии</a>
</nav>
<script>
/* Критик-владелец W36 B5: scrollspy — подсвечиваем чип активной секции,
   новичок видит, где он в простыне настроек.
   W63 (владелец OPEN#1): скрипт шёл ДО секций в DOM — getElementById давал null,
   наблюдатель ни за чем не следил. Откладываем на DOMContentLoaded. */
function nilovInitSpy() {
  var links = Array.prototype.slice.call(document.querySelectorAll('#top-nav a'));
  if (!links.length || !('IntersectionObserver' in window)) return;
  function setActive(id) {
    links.forEach(function (a) {
      var on = a.getAttribute('href') === '#' + id;
      a.style.background = on ? 'var(--rose-cta,#AE4A71)' : '';
      a.style.color = on ? '#fff' : '';
      if (on) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); }
    });
  }
  var sections = links.map(function (a) { return a.getAttribute('href').slice(1); });
  function pick() {
    /* «последняя секция, начавшаяся выше 35% экрана» — честный spy:
       при скролле к features нижняя кромка s-look ещё в полосе → прежний
       «верхняя видимая» давал ложную подсветку (мок-тест W63). */
    var act = sections[0], line = innerHeight * 0.35;
    for (var i = 0; i < sections.length; i++) {
      var el = document.getElementById(sections[i]);
      if (el && el.getBoundingClientRect().top <= line) act = sections[i];
    }
    setActive(act);
  }
  var io = new IntersectionObserver(pick, { rootMargin: '-72px 0px -70% 0px' });
  sections.forEach(function (id) { var el = document.getElementById(id); if (el) io.observe(el); });
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', nilovInitSpy); } else { nilovInitSpy(); }
</script>

<form method="post" enctype="multipart/form-data" style="padding-bottom:84px">
  <?= csrf_field() ?>
  <input type="hidden" name="cb_rendered" id="cb-rendered" value="">
  <script>
  /* Класс-защита (критик-2): форма сама сообщает, какие чекбоксы в ней реально есть —
     сервер обнуляет только их; ключ вне формы (notify-до переезда, будущие) не затирается. */
  document.addEventListener('DOMContentLoaded', function(){
    var f = document.querySelector('form[method=post]');
    if (!f) return;
    f.addEventListener('submit', function(){
      var names = Array.prototype.map.call(f.querySelectorAll('input[type=checkbox][name]'), function(i){ return i.name; });
      document.getElementById('cb-rendered').value = names.join(',');
    });
  });
  </script>
  <div class="card" id="s-common">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Общие</h2>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="notify_enabled" style="width:auto" <?= ($s['notify_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
      Уведомления о новых заказах (email + Telegram)
    </label>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Главный выключатель. Секретный код бота (его «токен») и ваш Telegram-чат настраиваются в разделе «Профиль».</p>
    <div class="grid2">
      <div>
        <label class="f" for="s-name">Название магазина *</label>
        <input class="input" id="s-name" name="shop_name" required value="<?= sv('shop_name', $s) ?>">
        <label class="f" for="s-phone">Телефон</label>
        <input class="input" id="s-phone" name="shop_phone" value="<?= sv('shop_phone', $s) ?>">
        <label class="f" for="s-addr">Адрес</label>
        <input class="input" id="s-addr" name="shop_address" value="<?= sv('shop_address', $s) ?>">
      </div>
      <div>
        <label class="f" for="s-logo" style="color:var(--rose-cta,#AE4A71);font-size:.95rem">🖼 Логотип в шапке сайта</label>
        <input class="input" id="s-logo" name="logo_image" type="file" accept="image/*">
        <?php if (($s['logo_image'] ?? '') !== ''): ?>
          <?php $logoPath = (__DIR__) . '/../img/uploads/' . $s['logo_image']; $logoVer = is_file($logoPath) ? substr(md5_file($logoPath), 0, 8) : '0'; ?>
          <img class="thumb" style="margin-top:8px" src="/img/uploads/<?= e($s['logo_image']) ?>?v=<?= $logoVer ?>" alt="">
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:.9rem;cursor:pointer;padding:8px 10px;border:1px solid var(--line);border-radius:10px;background:var(--bg-alt,#faf6f0)">
            <input type="checkbox" name="logo_enabled" value="1" <?= sv('logo_enabled', $s) !== '0' ? 'checked' : '' ?>>
            Показывать картинку-логотип рядом с названием.
            <strong style="font-weight:600"><?= sv('logo_enabled', $s) !== '0' ? 'Сейчас: картинка + название' : 'Сейчас: только название текстом' ?></strong>
          </label>
          <p style="font-size:.8rem;color:var(--ink-soft);margin:6px 0 0">Выключите тумблер — в шапке останется только название сайта текстом. Загрузите новый файл (PNG/SVG, прозрачный фон) — он заменит картинку.</p>
        <?php else: ?>
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:.9rem;color:var(--ink-soft)">
            <input type="checkbox" checked disabled>
            Тумблер станет доступен после загрузки файла. Сейчас: только название текстом.
          </label>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card" id="s-main">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Главная страница</h2>
    <div class="grid2">
      <div>
        <label class="f" for="h-eyebrow">Строка над заголовком (город и подача)</label>
        <input class="input" id="h-eyebrow" name="hero_eyebrow" value="<?= sv('hero_eyebrow', $s) !== '' ? sv('hero_eyebrow', $s) : 'Санкт-Петербург · доставка в день заказа' ?>" maxlength="60">
        <label class="f" for="h-title">Заголовок на главной странице (крупный текст сверху)</label>
        <input class="input" id="h-title" name="hero_title" value="<?= sv('hero_title', $s) ?>">
        <label class="f" for="h-sub">Подзаголовок</label>
        <textarea class="input" id="h-sub" name="hero_subtitle" rows="2"><?= sv('hero_subtitle', $s) ?></textarea>
        <div style="display:flex;gap:12px">
          <div style="flex:1">
            <label class="f" for="h-btn">Текст кнопки</label>
            <input class="input" id="h-btn" name="hero_button_text" value="<?= sv('hero_button_text', $s) ?>">
          </div>
          <div style="flex:1">
            <label class="f" for="h-link">Ссылка кнопки</label>
            <input class="input" id="h-link" name="hero_button_link" value="<?= sv('hero_button_link', $s) ?>">
          </div>
        </div>
      </div>
      <div>
        <label class="f" for="h-img">Фото для главной (заменить)</label>
        <input class="input" id="h-img" name="hero_image" type="file" accept="image/*">
        <?php if (($s['hero_image'] ?? '') !== ''): ?>
          <img class="thumb" style="margin-top:8px;width:120px;height:80px" src="/img/uploads/<?= e($s['hero_image']) ?>" alt="">
        <?php endif; ?>
      </div>
    </div>
    <?php /* Бегущая лента (критерий 16): тексты редактируются, пустые не показываются */ ?>
    <p style="font-size:.85rem;font-weight:600;margin:14px 0 8px">Бегущая лента под шапкой (тексты через ✿)</p>
    <div class="grid2">
      <?php for ($mq = 1; $mq <= 4; $mq++):
          $mqDefault = ['Доставка по Санкт-Петербургу в день заказа','Свежие цветы с утренней поставки','Фото букета перед отправкой','Заменяем увядшие в день доставки'][$mq - 1];
      ?>
      <div>
        <label class="f" for="mq-<?= $mq ?>">Фраза <?= $mq ?></label>
        <input class="input" id="mq-<?= $mq ?>" name="marquee_<?= $mq ?>" value="<?= sv("marquee_{$mq}", $s) !== '' ? sv("marquee_{$mq}", $s) : $mqDefault ?>" maxlength="90">
      </div>
      <?php endfor; ?>
    </div>
    <?php /* Заголовки секций главной (критерий 16) */ ?>
    <p style="font-size:.85rem;font-weight:600;margin:14px 0 8px">Заголовки разделов на главной</p>
    <div class="grid2">
      <div>
        <label class="f" for="cat-t">Заголовок каталога</label>
        <input class="input" id="cat-t" name="catalog_title" value="<?= sv('catalog_title', $s) !== '' ? sv('catalog_title', $s) : 'Каталог' ?>" maxlength="40">
        <label class="f" for="cat-s" style="margin-top:8px">Подпись каталога</label>
        <input class="input" id="cat-s" name="catalog_subtitle" value="<?= sv('catalog_subtitle', $s) !== '' ? sv('catalog_subtitle', $s) : 'Соберём и доставим букет в день заказа' ?>" maxlength="90">
      </div>
      <div>
        <label class="f" for="ord-t">Заголовок формы заказа</label>
        <input class="input" id="ord-t" name="order_title" value="<?= sv('order_title', $s) !== '' ? sv('order_title', $s) : 'Оформление заказа' ?>" maxlength="40">
      </div>
    </div>
  </div>

  <div class="card" id="s-look">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Внешний вид</h2>
    <div class="grid2">
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="hero_text_enabled" style="width:auto" <?= sv('hero_text_enabled', $s) === '1' ? 'checked' : '' ?>>
          Показывать текст и кнопку поверх фото на главной
        </label>
        <p style="font-size:.82rem;color:var(--ink-soft);margin:4px 0 12px">Выключите, если хотите оставить только фотографию без заголовка и кнопки.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="yandex_reviews_enabled" style="width:auto" <?= sv('yandex_reviews_enabled', $s) === '1' ? 'checked' : '' ?>>
          Секция отзывов Яндекс Карт
        </label>
        <?php if (sv('yandex_reviews_enabled', $s) === '1' && trim(sv('yandex_reviews_id', $s)) === ''): ?>
        <p style="font-size:.85rem;color:#a05a00;background:#fdf3e3;border:1px solid #ecd9ae;border-radius:10px;padding:8px 12px;margin:8px 0">⚠ Галочка включена, но ID организации пуст — на сайте секция не появится, пока не впишете ID.</p>
        <?php endif; ?>
        <label class="f" for="yandex-reviews-id" style="margin-top:8px">ID организации на Яндекс Картах</label>
        <input class="input" id="yandex-reviews-id" name="yandex_reviews_id" value="<?= sv('yandex_reviews_id', $s) ?>" placeholder="133112293950">
        <label class="f" for="reviews-title" style="margin-top:8px">Заголовок секции отзывов</label>
        <input class="input" id="reviews-title" name="reviews_title" maxlength="120" value="<?= e(sv('reviews_title', $s) !== '' ? sv('reviews_title', $s) : 'Отзывы о нас на Яндекс Картах') ?>">
        <label class="f" for="reviews-sub" style="margin-top:8px">Подзаголовок секции отзывов</label>
        <input class="input" id="reviews-sub" name="reviews_sub" maxlength="120" value="<?= e(sv('reviews_sub', $s) !== '' ? sv('reviews_sub', $s) : 'Реальные отзывы покупателей — на карте города') ?>">
        <label class="f" for="reviews-card" style="margin-top:8px">Текст карточки «отзывы на карте»</label>
        <textarea class="input" id="reviews-card" name="reviews_card_text" rows="2" maxlength="400"><?= e(sv('reviews_card_text', $s) !== '' ? sv('reviews_card_text', $s) : 'Мы не публикуем отзывы на сайте — пусть их пишут за нас. Все оценки и слова покупателей живут в профиле на Яндекс Картах: там же вы сможете оставить своё впечатление после доставки.') ?></textarea>
        <p style="font-size:.82rem;color:var(--ink-soft);margin:4px 0 0">Цифры можно взять в Яндекс Бизнесе: ссылка на карточку организации вида yandex.ru/maps/org/133112293950 — нужен только номер.</p>
      </div>
      <div>
        <label class="f" for="s-favicon">Картинка-значок сайта (иконка во вкладке браузера)</label>
        <input class="input" id="s-favicon" name="site_favicon" type="file" accept="image/png,image/jpeg,image/webp,image/x-icon,image/svg+xml">
        <?php if (($s['site_favicon'] ?? '') !== ''): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-top:8px">
            <img class="thumb" style="width:32px;height:32px" src="/img/uploads/<?= e($s['site_favicon']) ?>" alt="Текущий favicon">
            <label class="f" style="display:flex;gap:6px;align-items:center;font-weight:400;font-size:.85rem;margin:0">
              <input type="checkbox" name="site_favicon_remove" value="1" style="width:auto">
              Удалить favicon
            </label>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php /* Функции витрины (критерий 16): каждый новый блок сайта — тумблер */ ?>
  <div class="card" id="s-features">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Функции витрины</h2>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 14px">Включайте и выключайте блоки сайта — изменения появятся на сайте сразу после сохранения.</p>
    <div class="grid2">
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_delivery_badge" style="width:auto" <?= sv('feature_delivery_badge', $s) === '1' ? 'checked' : '' ?>>
          Бейдж «Доставка 0 ₽» на карточках букетов
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Зелёная плачка про бесплатную доставку по Приморскому на каждой карточке каталога.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_countdown" style="width:auto" <?= sv('feature_countdown', $s) === '1' ? 'checked' : '' ?>>
          Таймер «успейте заказать до 20:00»
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Живые часы обратного отсчёта в шапке главной страницы.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_price_filter" style="width:auto" <?= sv('feature_price_filter', $s) === '1' ? 'checked' : '' ?>>
          Фильтр букетов по цене
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Выпадающий список «до 2 500 ₽ / 2 500–4 000 / от 4 000 ₽» над каталогом.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_zone_check" style="width:auto" <?= sv('feature_zone_check', $s) === '1' ? 'checked' : '' ?>>
          Проверка зоны доставки в каталоге
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 6px 26px">Поле «Например: Приморский» — покупатель сразу видит стоимость для своего района.</p>
        <div style="margin-left:26px">
          <label class="f" for="zc-ph">Серый текст-пример внутри поля «Район»</label>
          <input class="input" id="zc-ph" name="zone_check_placeholder" value="<?= sv('zone_check_placeholder', $s) !== '' ? sv('zone_check_placeholder', $s) : 'Например: Приморский' ?>" maxlength="60">
          <label class="f" for="zc-fb" style="margin-top:8px">Если район не найден</label>
          <input class="input" id="zc-fb" name="zone_check_fallback" value="<?= sv('zone_check_fallback', $s) !== '' ? sv('zone_check_fallback', $s) : 'не нашли — уточним по телефону' ?>" maxlength="80">
        </div>
      </div>
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_favorites" style="width:auto" <?= sv('feature_favorites', $s) === '1' ? 'checked' : '' ?>>
          Сердечки «Избранное» на карточках
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 0 26px">Покупатель отмечает букеты сердечком и может показать только их.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:8px">
          <input type="checkbox" name="feature_favicon_badge" style="width:auto" <?= sv('feature_favicon_badge', $s) !== '0' ? 'checked' : '' ?>>
          Счётчик корзины на иконке сайта (вкладка браузера)
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 0 26px">На иконке во вкладке появляется розовый кружок с числом товаров, когда корзина не пуста.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:8px">
          <input type="checkbox" name="feature_webpush" style="width:auto" <?= sv('feature_webpush', $s) === '1' ? 'checked' : '' ?>>
          Push-уведомления о новых заказах на телефон
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 0 26px">Включите, чтобы кнопка «Включить уведомления» внизу страницы настроек работала (нужны созданные ключи).</p>
        <div style="margin-left:26px;margin-top:6px">
          <label class="f" for="bd-sale">Бейдж со скидкой</label>
          <input class="input" id="bd-sale" name="badge_sale_text" value="<?= sv('badge_sale_text', $s) !== '' ? sv('badge_sale_text', $s) : 'Скидка до конца недели' ?>" maxlength="40">
          <label class="f" for="bd-urg" style="margin-top:8px">Бейдж «успеть сегодня» (ограниченные букеты)</label>
          <input class="input" id="bd-urg" name="badge_urgent_text" value="<?= sv('badge_urgent_text', $s) !== '' ? sv('badge_urgent_text', $s) : 'Успеть сегодня' ?>" maxlength="40">
        </div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_faq" style="width:auto" <?= sv('feature_faq', $s) === '1' ? 'checked' : '' ?>>
          Блок «Частые вопросы» на главной
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">4 вопроса-ответа про доставку и оплату (помогают и в поиске Яндекса).</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_track_link" style="width:auto" <?= sv('feature_track_link', $s) === '1' ? 'checked' : '' ?>>
          Ссылка «Где мой заказ?» в подвале
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Покупатель сам проверяет статус по телефону — меньше звонков «где букет?».</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_gift_fields" style="width:auto" <?= sv('feature_gift_fields', $s) !== '0' ? 'checked' : '' ?>>
          Блок «Кому дарим» в заказе (получатель + открытка)
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Покупатель укажет имя получателя, его телефон и текст открытки — вы не будете выспрашивать по звонку.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_delivery_slots" style="width:auto" <?= sv('feature_delivery_slots', $s) === '1' ? 'checked' : '' ?>>
          Выбор даты и интервала доставки в заказе
        </label>
          <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin:8px 0 2px">
            <input type="checkbox" name="feature_gallery" style="width:auto" <?= sv('feature_gallery', $s) !== '0' ? 'checked' : '' ?>>
            Галерея видов на странице букета (общий план + крупный; отключите — останется одно фото)
          </label>

        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Покупатель сам выберет день и время (утро/день/вечер) — меньше согласований по телефону.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_promo" style="width:auto" <?= sv('feature_promo', $s) !== '0' ? 'checked' : '' ?>>
          Поле «Промокод» в корзине
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Коды создаются в разделе «Промокоды». Скидка считается на сервере — обмануть нельзя.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_related" style="width:auto" <?= sv('feature_related', $s) !== '0' ? 'checked' : '' ?>>
          Блок «С этим берут» на странице товара
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Показывает 3 других букета этой же категории (или случайные) — покупателю есть куда идти дальше, средний чек выше.</p>
        <label class="f" for="m-related-title">Заголовок блока рекомендаций</label>
        <input class="input" id="m-related-title" name="related_title" value="<?= sv('related_title', $s) !== '' ? sv('related_title', $s) : 'С этим берут' ?>" maxlength="60">
        <div style="margin:6px 0 10px 26px">
          <label class="f" for="slots-ta">Интервалы доставки (по одному в строке)</label>
          <textarea class="input" id="slots-ta" name="delivery_slots" rows="3" style="width:100%;font:inherit"><?= e(sv('delivery_slots', $s) !== '' ? sv('delivery_slots', $s) : "Утро 9:00–14:00\nДень 14:00–18:00\nВечер 18:00–22:00") ?></textarea>
        </div>
      </div>
    </div>
    <?php /* Тексты редактируемых фич (критерий 16): бейдж + FAQ */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <div class="grid2">
      <div>
        <label class="f" for="badge-text">Текст бейджа на карточках</label>
        <input class="input" id="badge-text" name="delivery_badge_text" value="<?= sv('delivery_badge_text', $s) !== '' ? sv('delivery_badge_text', $s) : 'Доставка 0₽ · Приморский' ?>" maxlength="60">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Зелёная плашка на каждой карточке букета в каталоге. Оставьте как есть или впишите свой тариф.</p>
      </div>
      <div>
        <label class="f" for="faq-t">Заголовок блока вопросов</label>
        <input class="input" id="faq-t" name="faq_title" value="<?= sv('faq_title', $s) !== '' ? sv('faq_title', $s) : 'Частые вопросы' ?>" maxlength="60">
      </div>
    </div>
    <p style="font-size:.85rem;font-weight:600;margin:16px 0 8px">Вопросы и ответы (пустая пара не показывается на сайте)</p>
    <?php for ($i = 1; $i <= 4; $i++): ?>
    <div class="grid2" style="margin-bottom:10px">
      <div>
        <label class="f" for="faq-q<?= $i ?>">Вопрос <?= $i ?></label>
        <input class="input" id="faq-q<?= $i ?>" name="faq_q<?= $i ?>" value="<?= sv("faq_q{$i}", $s) ?>" maxlength="120" placeholder="<?= $i === 1 ? 'Сколько стоит доставка?' : '' ?>">
      </div>
      <div>
        <label class="f" for="faq-a<?= $i ?>">Ответ <?= $i ?></label>
        <input class="input" id="faq-a<?= $i ?>" name="faq_a<?= $i ?>" value="<?= sv("faq_a{$i}", $s) ?>" maxlength="300" placeholder="<?= $i === 1 ? 'По Приморскому району — бесплатно…' : '' ?>">
      </div>
    </div>
    <?php endfor; ?>
    <?php /* Дедлайн приёма заказов (критерий 16): время + тексты таймера */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">До которого часа принимаем заказы на сегодня</p>
    <div class="grid2">
      <div>
        <label class="f" for="dl-h">Час (0–23)</label>
        <input class="input" id="dl-h" name="order_deadline_hour" type="number" min="0" max="23" value="<?= sv('order_deadline_hour', $s) !== '' ? sv('order_deadline_hour', $s) : '20' ?>">
      </div>
      <div>
        <label class="f" for="dl-m">Минуты (0–59)</label>
        <input class="input" id="dl-m" name="order_deadline_minute" type="number" min="0" max="59" value="<?= sv('order_deadline_minute', $s) !== '' ? sv('order_deadline_minute', $s) : '0' ?>">
      </div>
    </div>
    <label class="f" for="cd-t" style="margin-top:12px">Текст таймера (до дедлайна)</label>
    <input class="input" id="cd-t" name="countdown_text" value="<?= sv('countdown_text', $s) !== '' ? sv('countdown_text', $s) : 'Успейте заказать сегодня — осталось {T} до {D}' ?>" maxlength="120">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 12px">Вместо <code>{T}</code> подставится время («2 ч 15 мин»), вместо <code>{D}</code> — ваш дедлайн. Ночью (до открытия) и после дедлайна показывается соответствующий текст ниже.</p>
    <label class="f" for="cd-c">Текст после дедлайна</label>
    <input class="input" id="cd-c" name="countdown_closed_text" value="<?= sv('countdown_closed_text', $s) !== '' ? sv('countdown_closed_text', $s) : 'Приём заказов на сегодня закрыт — доставим завтра с 9:00' ?>" maxlength="120">
    <label class="f" for="cd-n" style="margin-top:10px">Текст ночью (с дедлайна до открытия)</label>
    <input class="input" id="cd-n" name="countdown_night_text" value="<?= sv('countdown_night_text', $s) !== '' ? sv('countdown_night_text', $s) : 'Ночь. Заказ примем сейчас — доставим сегодня после 9:00' ?>" maxlength="120">
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Пороги фильтра цены в каталоге</p>
    <div class="grid2">
      <div>
        <label class="f" for="pf-low">«До …» — граница, ₽</label>
        <input class="input" id="pf-low" name="price_filter_low" type="number" min="100" max="100000" step="100" value="<?= sv('price_filter_low', $s) !== '' ? sv('price_filter_low', $s) : '2500' ?>">
      </div>
      <div>
        <label class="f" for="pf-high">«От …» — граница, ₽</label>
        <input class="input" id="pf-high" name="price_filter_high" type="number" min="200" max="200000" step="100" value="<?= sv('price_filter_high', $s) !== '' ? sv('price_filter_high', $s) : '4000' ?>">
      </div>
    </div>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:6px 0 0">Покупатель увидит три варианта: «до [нижняя]», «[нижняя]–[верхняя]», «от [верхняя]».</p>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Бесплатная доставка от суммы заказа</p>
    <div class="grid2">
      <div>
        <label class="f" for="fd-th">Порог, ₽ (0 — выключить)</label>
        <input class="input" id="fd-th" name="free_delivery_threshold" type="number" min="0" max="100000" step="100" value="<?= sv('free_delivery_threshold', $s) !== '' ? sv('free_delivery_threshold', $s) : '0' ?>">
      </div>
      <div>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:14px 0 0">Например, 3000: заказы от 3 000 ₽ получают бесплатную доставку в любой район. Покупатель увидит подсказку «добавьте ещё N ₽ — и доставка бесплатно». Сейчас: <?= (int)(sv('free_delivery_threshold', $s) ?: '0') > 0 ? 'включено' : 'выключено' ?>.</p>
      </div>
    </div>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Сообщения, если фильтры не нашли букетов</p>
    <div class="grid2">
      <div>
        <label class="f" for="ce-t">Заголовок</label>
        <input class="input" id="ce-t" name="catalog_empty_title" value="<?= sv('catalog_empty_title', $s) !== '' ? sv('catalog_empty_title', $s) : 'По этим фильтрам букетов не нашлось 🌷' ?>" maxlength="100">
      </div>
      <div>
        <label class="f" for="ce-h">Подсказка</label>
        <input class="input" id="ce-h" name="catalog_empty_hint" value="<?= sv('catalog_empty_hint', $s) !== '' ? sv('catalog_empty_hint', $s) : 'Попробуйте убрать фильтр цены или выбрать другую категорию' ?>" maxlength="160">
      </div>
    </div>
    <?php /* Cookie-баннер (критерий 16): юр-тексты редактируются */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Окно про cookie (появляется при первом визите)</p>
    <label class="f" for="cb-text">Текст</label>
    <input class="input" id="cb-text" name="cookie_banner_text" value="<?= sv('cookie_banner_text', $s) !== '' ? sv('cookie_banner_text', $s) : 'Сайт использует cookie и Яндекс.Метрику для работы и анализа трафика. Подробнее — в <a href="/policy" target="_blank" rel="noopener">Политике обработки персональных данных</a>.' ?>" maxlength="260">
    <div class="grid2" style="margin-top:8px">
      <div>
        <label class="f" for="cb-acc">Кнопка «согласиться»</label>
        <input class="input" id="cb-acc" name="cookie_accept_text" value="<?= sv('cookie_accept_text', $s) !== '' ? sv('cookie_accept_text', $s) : 'Принять' ?>" maxlength="30">
      </div>
      <div>
        <label class="f" for="cb-rej">Кнопка «только необходимые»</label>
        <input class="input" id="cb-rej" name="cookie_reject_text" value="<?= sv('cookie_reject_text', $s) !== '' ? sv('cookie_reject_text', $s) : 'Только необходимые' ?>" maxlength="30">
      </div>
    </div>
    <?php /* Чекаут + корзина (критерий 16) */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Оформление заказа и корзина</p>
    <div class="grid2">
      <div>
        <label class="f" for="pk-opt">Пункт самовывоза в списке</label>
        <input class="input" id="pk-opt" name="pickup_option_text" value="<?= sv('pickup_option_text', $s) !== '' ? sv('pickup_option_text', $s) : 'Самовывоз · 0 ₽' ?>" maxlength="40">
        <label class="f" for="pk-addr" style="margin-top:10px">Точный адрес самовывоза (улица, дом)</label>
        <input class="input" id="pk-addr" name="pickup_address" value="<?= sv('pickup_address', $s) ?>" placeholder="Например: Полевая Сабировская ул., 47, корп. 1" maxlength="120">
        <label class="f" for="dl-hint" style="margin-top:8px">Подсказка под доставкой</label>
        <input class="input" id="dl-hint" name="delivery_hint_text" value="<?= sv('delivery_hint_text', $s) !== '' ? sv('delivery_hint_text', $s) : 'Доставим в течение дня, время согласуем по телефону' ?>" maxlength="90">
        <label class="f" for="sb-btn" style="margin-top:8px">Кнопка отправки заказа (при онлайн-оплате)</label>
        <input class="input" id="sb-btn" name="submit_button_text" value="<?= sv('submit_button_text', $s) !== '' ? sv('submit_button_text', $s) : 'Оплатить заказ' ?>" maxlength="30">
        <label class="f" for="sb-np" style="margin-top:8px">Кнопка (при оплате при получении)</label>
        <input class="input" id="sb-np" name="submit_nopay_text" value="<?= sv('submit_nopay_text', $s) !== '' ? sv('submit_nopay_text', $s) : 'Отправить заказ' ?>" maxlength="30">
      </div>
      <div>
        <label class="f" for="ct-t">Заголовок корзины</label>
        <input class="input" id="ct-t" name="cart_title" value="<?= sv('cart_title', $s) !== '' ? sv('cart_title', $s) : 'Корзина' ?>" maxlength="30">
        <label class="f" for="ct-e" style="margin-top:8px">«Корзина пуста»</label>
        <input class="input" id="ct-e" name="cart_empty_text" value="<?= sv('cart_empty_text', $s) !== '' ? sv('cart_empty_text', $s) : 'Корзина пуста — выберите букет в каталоге' ?>" maxlength="90">
        <label class="f" for="ct-c" style="margin-top:8px">Кнопка «оформить» в корзине</label>
        <input class="input" id="ct-c" name="cart_checkout_text" value="<?= sv('cart_checkout_text', $s) !== '' ? sv('cart_checkout_text', $s) : 'Оформить заказ' ?>" maxlength="30">
        <label class="f" for="ct-n" style="margin-top:8px">Кнопка «продолжить покупки»</label>
        <input class="input" id="ct-n" name="cart_continue_text" value="<?= sv('cart_continue_text', $s) !== '' ? sv('cart_continue_text', $s) : 'Продолжить покупки' ?>" maxlength="30">
        <label class="f" for="ct-note" style="margin-top:8px">Сноска под итогом корзины (про доставку)</label>
        <input class="input" id="ct-note" name="cart_total_note" value="<?= sv('cart_total_note', $s) ?>" maxlength="140" placeholder="Пусто = скрыть сноску">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Поясняет, что «Итого» в корзине — это только букеты: доставка добавится в форме. Оставьте пустым, чтобы скрыть.</p>
      </div>
    </div>
    <?php /* Лейблы полей формы заказа (критерий 16, аудит-хардкоды) */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Подписи полей в форме заказа</p>
    <div class="grid2">
      <div>
        <label class="f" for="fl-name">Поле имени</label>
        <input class="input" id="fl-name" name="form_name_label" value="<?= sv('form_name_label', $s) !== '' ? sv('form_name_label', $s) : 'Ваше имя *' ?>" maxlength="30">
        <label class="f" for="fl-phone" style="margin-top:8px">Поле телефона</label>
        <input class="input" id="fl-phone" name="form_phone_label" value="<?= sv('form_phone_label', $s) !== '' ? sv('form_phone_label', $s) : 'Телефон' ?>" maxlength="30">
        <label class="f" for="fl-email" style="margin-top:8px">Поле email</label>
        <input class="input" id="fl-email" name="form_email_label" value="<?= sv('form_email_label', $s) !== '' ? sv('form_email_label', $s) : 'Email' ?>" maxlength="30">
        <label class="f" for="fl-zone" style="margin-top:8px">Список получения</label>
        <input class="input" id="fl-zone" name="form_delivery_zone_label" value="<?= sv('form_delivery_zone_label', $s) !== '' ? sv('form_delivery_zone_label', $s) : 'Как получить букет' ?>" maxlength="40">
      </div>
      <div>
        <label class="f" for="fl-dl">Заголовок блока доставки</label>
        <input class="input" id="fl-dl" name="fieldset_delivery_legend" value="<?= sv('fieldset_delivery_legend', $s) !== '' ? sv('fieldset_delivery_legend', $s) : 'Доставка' ?>" maxlength="30">
        <label class="f" for="fl-pl" style="margin-top:8px">Заголовок блока оплаты</label>
        <input class="input" id="fl-pl" name="fieldset_payment_legend" value="<?= sv('fieldset_payment_legend', $s) !== '' ? sv('fieldset_payment_legend', $s) : 'Способ оплаты' ?>" maxlength="30">
        <label class="f" for="fl-on" style="margin-top:8px">Вариант «онлайн»</label>
        <input class="input" id="fl-on" name="pay_online_label" value="<?= sv('pay_online_label', $s) !== '' ? sv('pay_online_label', $s) : 'Картой или через СБП — сразу онлайн' ?>" maxlength="60">
        <label class="f" for="fl-cash" style="margin-top:8px">Вариант «при получении»</label>
        <input class="input" id="fl-cash" name="pay_cash_label" value="<?= sv('pay_cash_label', $s) !== '' ? sv('pay_cash_label', $s) : 'При получении' ?>" maxlength="40">
      </div>
    </div>
  </div>

  <div class="card" id="s-steps">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px"><?= sv('steps_title', $s) !== '' ? 'Этапы работы' : 'Этапы работы' ?></h2>
    <label class="f" for="st-t">Заголовок блока</label>
    <input class="input" id="st-t" name="steps_title" value="<?= sv('steps_title', $s) ?>">
    <div class="grid2">
      <div>
        <label class="f" for="st-1">Шаг 1</label>
        <input class="input" id="st-1" name="step_1" value="<?= sv('step_1', $s) ?>">
        <label class="f" for="st-2">Шаг 2</label>
        <input class="input" id="st-2" name="step_2" value="<?= sv('step_2', $s) ?>">
      </div>
      <div>
        <label class="f" for="st-3">Шаг 3</label>
        <input class="input" id="st-3" name="step_3" value="<?= sv('step_3', $s) ?>">
      </div>
    </div>
  </div>

  <div class="card" id="s-contacts">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Контакты и мессенджеры</h2>
    <div class="grid2">
      <div>
        <label class="f" for="c-phone2">Телефон для сайта</label>
        <input class="input" id="c-phone2" name="shop_phone" value="<?= sv('shop_phone', $s) ?>">
        <label class="f" for="c-email">Email магазина</label>
        <input class="input" id="c-email" name="shop_email" value="<?= sv('shop_email', $s) ?>">
        <label class="f" for="c-hours">Часы работы</label>
        <input class="input" id="c-hours" name="shop_hours" value="<?= sv('shop_hours', $s) ?>" placeholder="напр. Ежедневно 9:00–21:00">
        <label class="f" for="c-hphone">Телефон для шапки (короткий)</label>
        <input class="input" id="c-hphone" name="header_phone" value="<?= sv('header_phone', $s) ?>">
        <label class="f" for="c-haddr">Короткий адрес для шапки</label>
        <input class="input" id="c-haddr" name="header_address" value="<?= sv('header_address', $s) ?>">
      </div>
      <div>
        <p class="f" style="margin:0 0 6px;font-weight:600">Мессенджеры</p>
        <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 10px">Одни и те же контакты можно продублировать — покупатель выберет, чем удобно написать.</p>
        <label class="f" for="m-wa">WhatsApp, номер (только цифры)</label>
        <input class="input" id="m-wa" name="shop_whatsapp" value="<?= sv('shop_whatsapp', $s) ?>" placeholder="79119417205">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="wa_enabled" style="width:auto" <?= ($s['wa_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
          Показывать WhatsApp на сайте
        </label>
        <label class="f" for="m-tg">Telegram, имя канала (без @)</label>
        <input class="input" id="m-tg" name="shop_telegram" value="<?= sv('shop_telegram', $s) ?>">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="tg_enabled" style="width:auto" <?= ($s['tg_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
          Показывать Telegram на сайте
        </label>
        <label class="f" for="m-vk">VK, ссылка на сообщество</label>
        <input class="input" id="m-vk" name="shop_vk" value="<?= sv('shop_vk', $s) ?>">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="vk_enabled" style="width:auto" <?= ($s['vk_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
          Показывать VK на сайте
        </label>
        <label class="f" for="m-max">MAX, готовая ссылка</label>
        <input class="input" id="m-max" name="shop_max_link" value="<?= sv('shop_max_link', $s) ?>">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="max_enabled" style="width:auto" <?= ($s['max_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
          Показывать MAX на сайте
        </label>
        <label class="f" for="m-ig">Instagram, ссылка на профиль</label>
        <input class="input" id="m-ig" name="shop_instagram" value="<?= sv('shop_instagram', $s) ?>" placeholder="https://www.instagram.com/...">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="ig_enabled" style="width:auto" <?= ($s['ig_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
          Показывать Instagram на сайте (вместе с пометкой)
        </label>
        <label class="f" for="m-email">Email в списке мессенджеров</label>
        <input class="input" id="m-email" name="shop_email" value="<?= sv('shop_email', $s) ?>">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="email_enabled" style="width:auto" <?= ($s['email_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
          Показывать email на сайте
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 0">Снимите флажок — контакт полностью скроется с сайта, даже если поле заполнено.</p>
      </div>
    </div>
  </div>

  <div class="card" id="s-cart">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Корзина и апсейл</h2>
    <label class="f" for="cm-mode">Как открывается корзина</label>
    <select id="cm-mode" name="cart_mode">
      <option value="drawer" <?= sv('cart_mode', $s) === 'drawer' ? 'selected' : '' ?>>Выдвижная панель справа</option>
      <option value="hybrid" <?= sv('cart_mode', $s) === 'hybrid' ? 'selected' : '' ?>>Панель на телефоне, страница на компьютере</option>
      <option value="page" <?= sv('cart_mode', $s) === 'page' ? 'selected' : '' ?>>Отдельная страница</option>
    </select>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 10px">Блок «Возможно, пригодится» в корзине: предлагайте товары, которых нет в заказе. Источники — отмеченные категории и галочка «в апсейле» у товара.</p>
    <div class="grid2">
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="upsell_enabled" style="width:auto" <?= sv('upsell_enabled', $s) === '1' ? 'checked' : '' ?>> Показывать блок «Возможно, пригодится» в корзине
        </label>
        <label class="f" for="u-limit">Сколько позиций показывать (1–6)</label>
        <input class="input" id="u-limit" name="upsell_limit" type="number" min="1" max="6" value="<?= sv('upsell_limit', $s) !== '' ? sv('upsell_limit', $s) : '3' ?>">
      </div>
      <div>
        <label class="f" for="u-title">Заголовок блока</label>
        <input class="input" id="u-title" name="upsell_title" value="<?= sv('upsell_title', $s) !== '' ? sv('upsell_title', $s) : 'Возможно, пригодится' ?>">
        <label class="f" for="u-cats">Из каких категорий показывать («1,2» — id категорий из списка ниже)</label>
        <input class="input" id="u-cats" name="upsell_categories" value="<?= sv('upsell_categories', $s) ?>" placeholder="1,2">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Пусто — все активные товары. Плюс всегда добавляются товары с галочкой «в апсейле».</p>
      </div>
    </div>
  </div>

  <div class="card" id="s-legal">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Юридические реквизиты</h2>
    <?php if (trim((string)($s['legal_name'] ?? '')) === '' || trim((string)($s['legal_number'] ?? '')) === ''): ?>
    <p style="font-size:.85rem;color:#a05a00;background:#fdf3e3;border:1px solid #ecd9ae;border-radius:10px;padding:8px 12px;margin:0 0 10px">⚠ Реквизиты не заполнены — покупатели не видят, кому платят. Впишите название и ОГРН/ОГРНИП — они появятся в оферте, политике и подвале сайта автоматически.</p>
    <?php endif; ?>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 10px">Показываются на страницах «Политика ПДн» и «Публичная оферта» и в подвале сайта.</p>
    <div class="grid2">
      <div>
        <label class="f" for="l-type">Тип субъекта</label>
        <select id="l-type" name="legal_subject_type">
          <option value="ip" <?= sv('legal_subject_type', $s) === 'ip' ? 'selected' : '' ?>>ИП</option>
          <option value="ooo" <?= sv('legal_subject_type', $s) === 'ooo' ? 'selected' : '' ?>>ООО</option>
          <option value="self" <?= sv('legal_subject_type', $s) === 'self' ? 'selected' : '' ?>>Самозанятый</option>
        </select>
        <label class="f" for="l-name">ФИО ИП / название ООО</label>
        <input class="input" id="l-name" name="legal_name" value="<?= sv('legal_name', $s) ?>">
        <label class="f" for="l-num">ОГРНИП / ОГРН</label>
        <input class="input" id="l-num" name="legal_number" value="<?= sv('legal_number', $s) ?>">
        <?php
        /* Legal-критик W41: ОГРНИП проходит контрольную сумму по алгоритму ЕГРИП:
           первые 14 цифр делят на 13, последняя цифра ЧАСТНОГО = 15-я цифра номера.
           (тот же алгоритм, что ОГРН/11). Не блокируем сохранение — предупреждаем. */
        $__lnum = preg_replace('/\D/', '', sv('legal_number', $s));
        if ($__lnum !== '' && sv('legal_subject_type', $s) === 'ip' && strlen($__lnum) === 15) {
            $__expect = (int)substr((string)(intdiv((int)substr($__lnum, 0, 14), 13)), -1);
            if ((int)$__lnum[14] !== $__expect) {
                echo '<p style="margin:4px 0 0;font-size:.8rem;color:#9E2626;background:#fbe3e3;border:1px solid #eab5b5;border-radius:8px;padding:6px 10px">⚠ Проверьте ОГРНИП: контрольная цифра не сходится — по алгоритму ЕГРИП номер должен заканчиваться на ' . $__expect . '. Сверьте с выпиской из ЕГРИП.</p>';
            }
        }
        ?>
        <label class="f" for="l-inn">ИНН</label>
        <input class="input" id="l-inn" name="legal_inn" value="<?= sv('legal_inn', $s) ?>" placeholder="781433059704" inputmode="numeric" maxlength="12">
      </div>
      <div>
        <label class="f" for="l-addr">Юридический/фактический адрес</label>
        <input class="input" id="l-addr" name="legal_address" value="<?= sv('legal_address', $s) ?>">
        <label class="f" for="l-mail">Email по вопросам ПДн</label>
        <input class="input" id="l-mail" name="legal_contact_email" value="<?= sv('legal_contact_email', $s) ?>">
      </div>
    </div>
    <?php /* SEO главной + Метрика (критерий 16, P1 аудита) */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Продвижение: заголовок и описание для поисковиков</p>
    <label class="f" for="seo-t">Title (виден во вкладке и в Яндексе)</label>
    <input class="input" id="seo-t" name="seo_title" value="<?= sv('seo_title', $s) !== '' ? sv('seo_title', $s) : 'Доставка цветов в СПб — ' . sv('shop_name', $s) . ' | Свежие букеты с доставкой сегодня' ?>" maxlength="80">
    <label class="f" for="seo-d" style="margin-top:8px">Description (описание в результатах поиска)</label>
    <input class="input" id="seo-d" name="seo_description" value="<?= sv('seo_description', $s) !== '' ? sv('seo_description', $s) : 'Доставка букетов по Санкт-Петербургу в день заказа. Свежие цветы с утренней поставки, фото перед отправкой, бесплатная доставка по Приморскому району. Заказы до 20:00 — доставим сегодня.' ?>" maxlength="200">
    <label class="f" for="mk-id" style="margin-top:8px">Счётчик Яндекс.Метрики (номер)</label>
    <input class="input" id="mk-id" name="metrika_counter_id" value="<?= sv('metrika_counter_id', $s) ?>" placeholder="12345678" inputmode="numeric" maxlength="12">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Метрика грузится только после согласия на cookie. Номер — из личного кабинета Метрики. Пусто = счётчик не ставится.</p>
    <details style="margin-top:10px" <?= trim(sv('yandex_verification', $s)) !== '' || trim(sv('google_site_verification', $s)) !== '' ? 'open' : '' ?>>
      <summary style="font-size:.82rem;font-weight:600;cursor:pointer">Подтверждение прав для Яндекса и Google (вебмастер)</summary>
      <p style="font-size:.78rem;color:var(--ink-soft);margin:6px 0 4px">Когда подключаете сайт в Яндекс.Вебмастере или Search Console — система покажет «метатег». Скопируйте оттуда длинный код в поле ниже и сохраните. Ничего устанавливать на сервер не нужно, сайт сам подтвердит права.</p>
      <label class="f" for="yav">Код подтверждения Яндекс.Вебмастера</label>
      <input class="input" id="yav" name="yandex_verification" value="<?= sv('yandex_verification', $s) ?>" placeholder="например: 1234567890abcdef" maxlength="64">
      <label class="f" for="gav" style="margin-top:8px">Код подтверждения Google Search Console</label>
      <input class="input" id="gav" name="google_site_verification" value="<?= sv('google_site_verification', $s) ?>" placeholder="например: AbCdEf123..." maxlength="64">
    </details>
  </div>

  <div class="card" id="s-pay">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Оплата: ЮKassa / при получении</h2>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 10px">Выключено — сайт принимает только оплату при получении. Включите и заполните ключи из личного кабинета ЮKassa (Интеграция → Ключи API), чтобы принимать карты и СБП онлайн.</p>
    <div class="grid2">
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="yk_enabled" style="width:auto" <?= sv('yk_enabled', $s) === '1' ? 'checked' : '' ?>>
          Принимать онлайн-оплату через ЮKassa
        </label>
        <label class="f" for="yk-id">Идентификатор магазина ЮKassa (shopId, из письма от ЮKassa)</label>
        <input class="input" id="yk-id" name="yk_shop_id" value="<?= sv('yk_shop_id', $s) ?>">
        <label class="f" for="yk-key">Секретный ключ</label>
        <input class="input" id="yk-key" name="yk_secret_key" type="password" value="<?= sv('yk_secret_key', $s) ?>">
      </div>
      <div>
        <label class="f" for="vat">Ставка НДС в чеке</label>
        <select id="vat" name="vat_rate">
          <option value="none" <?= sv('vat_rate', $s) === 'none' ? 'selected' : '' ?>>Без НДС (УСН)</option>
          <option value="0" <?= sv('vat_rate', $s) === '0' ? 'selected' : '' ?>>0%</option>
          <option value="10" <?= sv('vat_rate', $s) === '10' ? 'selected' : '' ?>>10%</option>
          <option value="20" <?= sv('vat_rate', $s) === '20' ? 'selected' : '' ?>>20%</option>
        </select>
        <p style="font-size:.82rem;color:var(--ink-soft);margin-top:8px">Неверная ставка — риск вопросов от налоговой. «Без НДС» — стандарт для ИП на УСН.</p>
      </div>
    </div>
  </div>

  <div class="card" id="s-guarantees">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Гарантии</h2>
    <label class="f" for="g-t">Заголовок блока</label>
    <input class="input" id="g-t" name="guarantees_title" value="<?= sv('guarantees_title', $s) ?>">
    <div class="grid2">
      <div>
        <label class="f" for="g-1">Гарантия 1</label>
        <input class="input" id="g-1" name="guarantee_1" value="<?= sv('guarantee_1', $s) ?>">
        <label class="f" for="g-2">Гарантия 2</label>
        <input class="input" id="g-2" name="guarantee_2" value="<?= sv('guarantee_2', $s) ?>">
      </div>
      <div>
        <label class="f" for="g-3">Гарантия 3</label>
        <input class="input" id="g-3" name="guarantee_3" value="<?= sv('guarantee_3', $s) ?>">
      </div>
    </div>
  </div>

  <?php /* Критерий 25: кнопка сохранения прижата к низу окна — не надо листать до неё
          через всю форму; sticky bottom работает внутри form-контейнера. */ ?>
  <div style="position:sticky;bottom:12px;z-index:40;display:flex;align-items:center;gap:12px;margin:24px 0 0;padding:10px 12px;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border:1px solid var(--line);border-radius:16px;box-shadow:0 8px 28px -12px rgba(43,45,47,.35)">
    <span id="dirty-hint" style="display:none;font-size:.82rem;font-weight:600;color:var(--rose-cta,#AE4A71)">● Есть несохранённые изменения</span>
    <button class="btn btn--accent" type="submit" style="padding:14px 32px;font-size:.95rem;margin-left:auto">💾 Сохранить настройки</button>
    <a class="btn btn--outline" href="#top-nav" style="padding:14px 18px;font-size:.85rem;text-decoration:none">↑ Наверх</a>
  </div>
  <script>
  /* Критерий 25: подсветка «есть несохранённые изменения» + Ctrl/Cmd+S = сохранить. */
  (function(){
    var form = document.currentScript.closest('form');
    if (!form) return;
    var hint = document.getElementById('dirty-hint');
    function dirty(){ if (hint) hint.style.display = ''; }
    form.addEventListener('input', dirty);
    form.addEventListener('change', dirty);
    form.addEventListener('submit', function(){ if (hint) hint.style.display = 'none'; });
    document.addEventListener('keydown', function(e){
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); form.requestSubmit(); }
    });
  })();
  </script>
</form>

<form method="post" style="margin-top:12px">
  <?= csrf_field() ?>
  <input type="hidden" name="hist_action" value="undo">
  <button class="btn btn--outline" type="submit" style="padding:12px 22px;font-size:.85rem" <?= settingsCanUndo() ? '' : 'disabled title="Отменять пока нечего"' ?>>↩ Отменить последнее изменение</button>
</form>
<details style="margin-top:14px;background:var(--bg-alt,#F1EAD9);border:1px dashed var(--ink-soft);border-radius:12px;padding:10px 14px">
  <summary style="cursor:pointer;font-size:.85rem;font-weight:600">⚙️ Расширенные: значения по умолчанию (редко)</summary>
  <p style="font-size:.8rem;color:var(--ink-soft);margin:8px 0 6px">Эти кнопки <strong>не сохраняют</strong> ваши правки — они работают с «эталоном». Обычное сохранение — синяя кнопка выше.</p>
<form method="post" style="display:inline;margin-top:8px" onsubmit="return confirm('Вернуть витринные тексты и тумблеры к значению по умолчанию? Ваши контакты, секретные коды и реквизиты останутся как есть.');">
  <?= csrf_field() ?>
  <input type="hidden" name="hist_action" value="defaults">
  <button class="btn btn--outline" type="submit" style="padding:12px 22px;font-size:.85rem">⎌ Вернуть все значения по умолчанию</button>
</form>
<form method="post" style="display:inline;margin-top:8px" onsubmit="return confirm('Запомнить текущие витринные тексты и тумблеры как новое «по умолчанию»? Теперь кнопка ⎌ будет возвращать к этому состоянию.');">
  <?= csrf_field() ?>
  <input type="hidden" name="hist_action" value="save-defaults">
  <button class="btn btn--outline" type="submit" style="padding:12px 22px;font-size:.85rem">💾 Сохранить текущее как «по умолчанию»</button>
</form>
</details>
<?php if (($du = settingsDefaultsUpdatedAt()) !== ''): ?><p style="font-size:.75rem;color:var(--ink-soft);margin:6px 0 0">Сейчас «по умолчанию» = ваш сохранённый вариант от <?= e($du) ?></p><?php endif; ?>
<?php if (count($hist = settingsHistoryList(5)) > 0): ?>
<details style="margin-top:10px"><summary style="font-size:.82rem;color:var(--ink-soft);cursor:pointer">Последние изменения (<?= count($hist) ?>)</summary>
<ol style="font-size:.78rem;color:var(--ink-soft);margin:6px 0 0 18px">
  <?php foreach ($hist as $hh): ?>
  <li><?= e($hh['ts']) ?> — <?= e(['save' => 'сохранение', 'undo' => 'отмена', 'defaults' => 'сброс к „по умолчанию“', 'defaults-save' => 'эталон обновлён текущим', 'defaults-reset' => 'сброс к „по умолчанию“'][$hh['source']] ?? $hh['source']) ?></li>
  <?php endforeach; ?>
</ol></details>
<?php endif; ?>

<?php
/* --- Web Push (VAPID): отдельная мини-форма, не трогает основную --- */
require_once __DIR__ . '/../includes/vapid.php';
$msgPush = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['vapid_action'])) {
    /* CSRF уже проверён глобальным гейтом в начале файла (строка ~10) */
    if (($_POST['vapid_action'] ?? '') === 'gen') {
        $r = vapidGenerate();
        $msgPush = isset($r['error']) ? 'Не удалось: ' . $r['error'] : 'Ключи созданы ✅';
    } elseif (($_POST['vapid_action'] ?? '') === 'test') {
        require_once __DIR__ . '/../includes/notify.php';
        $r = webpushSendAll('Тест Nilov Flowers', 'Если видите это уведомление — push работает 🌸', '/admin/');
        $msgPush = isset($r['error']) ? 'Ошибка: ' . $r['error'] : "Отправлено {$r['sent']} из {$r['total']} подписок" . ($r['dead'] ? ", удалено мёртвых: {$r['dead']}" : '');
    }
    $s = allSettings();
}
$pushCount = db()->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn();
$hasKeys = vapidKeysExist();
?>
<div class="card" id="s-push" style="margin-top:16px">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Push-уведомления на телефон (PWA)</h2>
  <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 10px">Открываете сайт на телефоне → «В добавить на главный экран» → включаете уведомления здесь. Тогда о новых заказах телефон получит всплывающее сообщение даже с закрытым браузером. iOS: только из добавленного на экран приложения. Android/Chrome и Firefox: прямо с сайта после согласия.</p>
  <?php if ($msgPush !== ''): ?><p style="background:var(--bg-alt);border-radius:10px;padding:8px 12px;font-size:.85rem"><?= e($msgPush) ?></p><?php endif; ?>
  <p style="font-size:.85rem;margin:6px 0">Статус: ключи <b><?= $hasKeys ? 'созданы ✅' : 'не созданы ⚠️' ?></b> · подписок на устройствах: <b><?= (int)$pushCount ?></b></p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <?php if (!$hasKeys): ?>
    <form method="post" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="vapid_action" value="gen">
      <button class="btn btn--accent" type="submit" style="padding:10px 18px;font-size:.85rem">Создать ключи уведомлений</button>
    </form>
    <?php else: ?>
    <form method="post" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="vapid_action" value="test">
      <button class="btn btn--outline" type="submit" style="padding:10px 18px;font-size:.85rem">Отправить тест всем</button>
    </form>
    <button class="btn btn--accent" type="button" id="push-enable" style="padding:10px 18px;font-size:.85rem">🔔 Включить уведомления на этом устройстве</button>
    <script>
    (function(){
      var btn = document.getElementById('push-enable');
      btn.addEventListener('click', function(){
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) { alert('Браузер не поддерживает push. На iOS: добавьте сайт на главный экран и откройте оттуда.'); return; }
        navigator.serviceWorker.ready.then(function(reg){
          return fetch('/api/push-vapid-public.php').then(function(r){return r.json();}).then(function(d){
            if (!d.publicKey) { alert('Сначала создайте ключи (кнопка выше).'); return null; }
            return reg.pushManager.subscribe({ userVisibleOnly:true, applicationServerKey: d.publicKey });
          }).then(function(sub){
            if (!sub) return;
            return fetch('/api/push-subscribe.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(sub.toJSON()) })
              .then(function(r){ return r.json(); });
          }).then(function(res){ if (res && res.ok) alert('Готово! Устройства зарегистрировано.'); });
        }).catch(function(e){ alert('Не получилось: ' + (e && e.message ? e.message : e)); });
      });
    })();
    </script>
    <?php endif; ?>
  </div>
</div>
<?php adminFooter(); ?>
