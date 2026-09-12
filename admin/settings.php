<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

/* CSRF: админ-POST без валидного токена — отказ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    $back = $_SERVER['HTTP_REFERER'] ?? '/admin/index.php';
    header('Location: ' . $back);
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['shop_name','shop_phone','shop_address','hero_title','hero_subtitle',
        'hero_button_text','hero_button_link','steps_title','step_1','step_2','step_3',
        'guarantees_title','guarantee_1','guarantee_2','guarantee_3',
        'shop_email','shop_hours','shop_vk','shop_max_link','shop_instagram','header_phone','header_address',
        'shop_whatsapp','shop_telegram',
        'upsell_limit','upsell_title','upsell_categories',
        'legal_subject_type','legal_name','legal_number','legal_address','legal_contact_email',
        'vat_rate','yk_shop_id','yk_secret_key','yandex_reviews_id',
        /* Витринные тексты (критерий 16): бейдж + FAQ редактируются */
        'delivery_badge_text','faq_title',
        'faq_q1','faq_a1','faq_q2','faq_a2','faq_q3','faq_a3','faq_q4','faq_a4',
        /* Дедлайн + тексты таймера и empty-state (критерий 16) */
        'order_deadline_hour','order_deadline_minute','countdown_text','countdown_closed_text',
        'catalog_empty_title','catalog_empty_hint'];
    $values = [];
    foreach ($keys as $k) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }
    /* Чекбоксы: 0 если не пришли (снятие галочки = пусто в POST) */
    foreach (['yk_enabled', 'upsell_enabled', 'hero_text_enabled', 'yandex_reviews_enabled',
              'wa_enabled', 'tg_enabled', 'vk_enabled', 'ig_enabled', 'email_enabled', 'max_enabled',
              'notify_enabled',
              /* Витринные фичи (критерий 16): каждая отключаема из админки */
              'feature_delivery_badge', 'feature_faq', 'feature_countdown', 'feature_price_filter',
              'feature_favorites', 'feature_zone_check', 'feature_track_link'] as $cb) {
        $values[$cb] = isset($_POST[$cb]) ? '1' : '0';
    }
    /* cart_mode — select, не чекбокс: валидируем значение из POST */
    $values['cart_mode'] = in_array($_POST['cart_mode'] ?? '', ['drawer', 'page'], true) ? $_POST['cart_mode'] : 'drawer';
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

$s = allSettings();
function sv(string $k, array $s): string { return e($s[$k] ?? ''); }

adminHeader('Настройки', 'settings');
flash();
?>
<h1>Настройки магазина</h1>
<style>.card[id]{scroll-margin-top:120px}</style>

<?php /* Оглавление настроек (критик-владелец: «9 секций на одной простыне — листать всё»).
       Якоря-чипы, прыжок в один клик, sticky — всегда под рукой. */ ?>
<nav class="dash-ranges" style="margin-bottom:16px;position:sticky;top:64px;z-index:30;background:var(--bg,#F6F1E6);padding:8px 0;border-radius:0 0 12px 12px" aria-label="Разделы настроек">
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

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="card" id="s-common">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Общие</h2>
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
        <label class="f" for="s-logo">Логотип (заменить)</label>
        <input class="input" id="s-logo" name="logo_image" type="file" accept="image/*">
        <?php if (($s['logo_image'] ?? '') !== ''): ?>
          <img class="thumb" style="margin-top:8px" src="/img/uploads/<?= e($s['logo_image']) ?>" alt="">
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card" id="s-main">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Главная страница</h2>
    <div class="grid2">
      <div>
        <label class="f" for="h-title">Заголовок (hero)</label>
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
        <p style="font-size:.82rem;color:var(--ink-soft);margin:4px 0 0">Цифры можно взять в Яндекс Бизнесе: ссылка на карточку организации вида yandex.ru/maps/org/133112293950 — нужен только номер.</p>
      </div>
      <div>
        <label class="f" for="s-favicon">Favicon (заменить)</label>
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
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 0 26px">Поле «Мой район доставки…» — покупатель сразу видит стоимость для своего района.</p>
      </div>
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_favorites" style="width:auto" <?= sv('feature_favorites', $s) === '1' ? 'checked' : '' ?>>
          Сердечки «Избранное» на карточках
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Покупатель отмечает букеты сердечком и может показать только их.</p>
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
    <input class="input" id="cd-t" name="countdown_text" value="<?= sv('countdown_text', $s) !== '' ? sv('countdown_text', $s) : 'Успейте заказать сегодня — осталось {T} до 20:00' ?>" maxlength="120">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 12px">Вместо <code>{T}</code> подставится время («2 ч 15 мин 30 с»), «20:00» заменится на ваш дедлайн.</p>
    <label class="f" for="cd-c">Текст после дедлайна</label>
    <input class="input" id="cd-c" name="countdown_closed_text" value="<?= sv('countdown_closed_text', $s) !== '' ? sv('countdown_closed_text', $s) : 'Сегодня заказы уже закрыты — доставим завтра с утра' ?>" maxlength="120">
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
        <label class="f" for="u-cats">Категории-источники (id через запятую)</label>
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
      </div>
      <div>
        <label class="f" for="l-addr">Юридический/фактический адрес</label>
        <input class="input" id="l-addr" name="legal_address" value="<?= sv('legal_address', $s) ?>">
        <label class="f" for="l-mail">Email по вопросам ПДн</label>
        <input class="input" id="l-mail" name="legal_contact_email" value="<?= sv('legal_contact_email', $s) ?>">
      </div>
    </div>
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
        <label class="f" for="yk-id">shopId</label>
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

  <button class="btn btn--accent" type="submit" style="padding:14px 32px;font-size:.95rem">Сохранить настройки</button>
</form>
<?php adminFooter(); ?>
