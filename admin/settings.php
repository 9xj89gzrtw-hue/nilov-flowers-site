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
    /* Стресс-критик W88: отказ = HTTP 400, а не 302-редирект (семантика ошибки запроса) */
    http_response_code(400);
    exit('Неверный CSRF-токен. Обновите страницу.');
}

$pdo = db();

/* W98-fixD (D5): роли есть (admin_users.role: owner/staff — admin/users.php).
   Платёжные ключи и каналы уведомлений — только владельцу; тексты и витрина
   остаются доступными сотруднику (так решила владелица). */
$me = currentAdmin();
$isOwner = $me !== null && (string)($me['role'] ?? 'owner') === 'owner';

/* W105 (3-b, «фото боком»): ручной поворот загруженных картинок настроек
   (hero/логотип/favicon/обложки журнала) — кнопки ⟲ ⟳ ↕ у превью.
   Крутит ТОЛЬКО файлы, реально лежащие в img/uploads (админ-аплоады):
   посевочные img/editorial/* трекаются в git — поворот откатится при деплое;
   svg GD не читает. rotateStoredImage сносит webp-сиблинг + thumbs/*.webp. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rotate_img') {
    $anchors = ['hero_image' => 's-main', 'logo_image' => 's-common', 'site_favicon' => 's-look',
        'journal_1_image' => 's-5cv', 'journal_2_image' => 's-5cv', 'journal_3_image' => 's-5cv'];
    $key = (string)($_POST['key'] ?? '');
    $file = trim((string)setting($key));
    if (!isset($anchors[$key]) || $file === '' || str_contains($file, '/') || !is_file(IMG_UPLOADS_DIR . '/' . $file)) {
        flash('Файл картинки не найден в загруженных — поворачивать нечего', true);
    } elseif (rotateStoredImage(IMG_UPLOADS_DIR . '/' . $file, (string)($_POST['rot'] ?? ''))) {
        flash('Картинка повёрнута — витрина пересоберёт превью сама');
    } else {
        flash('Не удалось повернуть картинку (формат не поддержан)', true);
    }
    header('Location: /admin/settings.php' . (isset($anchors[$key]) ? '#' . $anchors[$key] : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['vapid_action']) && !isset($_POST['hist_action']) && ($_POST['action'] ?? '') !== 'rotate_img') {
    $keys = ['shop_name','shop_phone','shop_address','site_url','pickup_address','hero_title','hero_subtitle',
        'hero_button_text','hero_button_link','hero_image_alt','steps_title','step_1','step_2','step_3',
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
        'catalog_title','catalog_subtitle','catalog_strip_text','order_title',
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
        'free_delivery_threshold',
        /* S3 (v2026.3): коммерческий слой — инфо-бар, Сплит/Долями, бесплатные
           допы корзины, шаблоны WhatsApp, 1-клик, чипсы-теги, кнопки карточки */
        'infobar_text_1','infobar_text_2','infobar_text_3',
        'split_divider','split_label','split_note',
        'cart_extras_title','cart_extra_postcard_title','cart_extra_postcard_text',
        'cart_extra_chrysal_title','cart_extra_chrysal_text',
        'cart_free_progress_under','cart_free_progress_reached',
        'wa_template_new','wa_template_photo','wa_template_courier',
        'oneclick_title','oneclick_note','oneclick_btn',
        'chips_all_text','chips_tags',
        'badge_fresh_text','badge_sturdy_text',
        'card_btn_cart','card_btn_oneclick','card_delivery_text',
        /* S5 (бутик-тюнинг): живой таймер доставки, сплит-пилюля, quick view */
        'delivery_now_text','delivery_now_tomorrow','delivery_now_short','delivery_now_minutes',
        'card_split_format','split_chip_text',
        'quickview_comp_title','quickview_extras_title','quickview_chrysal_text',
        'quickview_card_label','quickview_card_placeholder','quickview_full_link',
        'gift_self_label','gift_other_label',
        /* S6 (стерильный 5cv): верхняя строка шапки + 4 бейджа доверия */
        'topbar_delivery_text','trust_badge_1','trust_badge_2','trust_badge_3','trust_badge_4',
        /* S7 (коммерческая структура 5cv): заголовки товарных полок + мастерская */
        'shelf_hits_title','shelf_author_title','shelf_premium_title',
        'workshop_hours','workshop_rating','workshop_note',
        /* S9 (выравнивание витрины): подписи и теги плашек 3–4 навигации */
        'budget_tile_mono_label','budget_tile_gifts_label',
        'budget_tile_mono_tag','budget_tile_gifts_tag',
        /* W96 (редизайн 5cv): тексты новых блоков витрины — город, hero-промо, чипы цен,
           секции хитов/премиума/бюджета/допов, поводы, магазины, SEO-текст, журнал */
        'citybar_text','city_label','search_placeholder','catalog_btn_text',
        'hero_promo_badge','hero_promo_title','hero_promo_text','hero_promo_btn_text','hero_promo_link','hero_promo_image',
        'hero_delivery_title','hero_delivery_text',
        /* W104-a2: строки specifics в карточке доставки hero (пусто — строка не печатается) */
        'hero_delivery_point_1','hero_delivery_point_2',
        'chips_price_low','chips_price_high',
        'section_hits_title','section_hits_sub','section_premium_title','section_premium_sub',
        'section_budget_title','section_addons_title','badge_hit_text','badge_premium_text',
        'editorial_text',
        /* W99-fixG2 (H14): вторая/третья editorial-строки — читаются витриной
           (index.php: после 6-й секции и перед FAQ) с дефолтами из кода */
        'editorial_text_2','editorial_text_3',
        /* W103 (F1, редизайн главной): имиджевые блоки — marquee-лента, manifesto,
           премиум-разворот, ghost-CTA hero, тэглайн футера, eyebrow-метки секций */
        'manifesto_kicker','manifesto_text',
        'premium_title','premium_sub','premium_price_label','premium_cta_text',
        'footer_tagline','hero_ghost_text','hero_ghost_link',
        'section_hits_eyebrow','section_budget_eyebrow',
        /* W104 (design-system): eyebrow каталога/поводов, подписи
           editorial-спек-чипов страницы товара. W104-α: ручные нумералы
           section_numeral_* упразднены — нумерация сквозная и автоматическая */
        'catalog_eyebrow','occasions_eyebrow',
        'pdp_spec_captions',
        'occasions_title','stores_title','stores_sub',
        'stores_1_title','stores_1_text','stores_2_title','stores_2_text','stores_3_title','stores_3_text',
        'seo_text_title','seo_text_body',
        'journal_title',
        'journal_1_title','journal_1_text','journal_1_link',
        'journal_2_title','journal_2_text','journal_2_link',
        'journal_3_title','journal_3_text','journal_3_link',
        /* W96-fix3: футер-описание, «доставим сегодня» на товаре, текст звонка на спасибо-странице */
        'footer_about','product_today_text','thanks_call_text',
        /* W98-fixE (E1/E18): интро категорий /category/{slug} — 6 посадочных */
        'category_intro_rozy','category_intro_sbornye-bukety','category_intro_polevye-cvety',
        'category_intro_avtorskie-bukety','category_intro_v-shlyapnoy-korobke','category_intro_sladkie-podarki',
        /* W98-fixE (E8/E18): дата обновления политики ПД (юридический раздел) */
        'policy_updated'];
    $values = [];
    /* КЛАСС-ЗАЩИТА (критик-2): ключ из allowlist, которого нет в отправленной форме,
       НЕ должен затираеться пустотой. Текстовые поля: пишем только если ключ реально пришёл
       (пустое поле всё равно приходит '' — очистка работает). Чекбоксы: снимок галки =
       отсутствие в POST, поэтому сверяем со списком отрендеренных (cb_rendered). */
    $cbRendered = array_map('trim', explode(',', (string)($_POST['cb_rendered'] ?? '')));
    $cbTrackAll = ($cbRendered === [''] || $cbRendered === []); // JS выключен → старое поведение
    foreach ($keys as $k) {
        if (!array_key_exists($k, $_POST)) { continue; }
        $values[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, 5000); /* W88 stress: серверный clamp всех текстовых полей (100k-POST) */
    }
    /* Стресс-критик W87: cookie-тексты — rich-text с whitelist <a>, серверный clamp (не maxlength). */
    foreach (['cookie_banner_text' => 260, 'cookie_accept_text' => 40, 'cookie_reject_text' => 40] as $rk => $rmax) {
        if (array_key_exists($rk, $values)) { $values[$rk] = sanitize_rich_text($values[$rk], $rmax); }
    }
    /* W96 (5cv): SEO-текст витрины — rich-поле, как cookie-тексты (whitelist <a>, серверный clamp) */
    if (array_key_exists('seo_text_body', $values)) { $values['seo_text_body'] = sanitize_rich_text($values['seo_text_body'], 5000); }
    /* W96 (5cv): пороги чипов цен — целые числа строкой (settings — TEXT), только если ключ пришёл */
    if (array_key_exists('chips_price_low', $_POST)) {
        $values['chips_price_low'] = (string)max(0, (int)($_POST['chips_price_low'] ?? 3500));
    }
    /* S3: делитель Сплит — 2..12 платежей; порог бесплатной доставки — целое */
    if (array_key_exists('split_divider', $_POST)) {
        $values['split_divider'] = (string)max(2, min(12, (int)($_POST['split_divider'] ?? 4)));
    }
    if (array_key_exists('free_delivery_threshold', $_POST)) {
        $values['free_delivery_threshold'] = (string)max(0, (int)($_POST['free_delivery_threshold'] ?? 0));
    }
    if (array_key_exists('chips_price_high', $_POST)) {
        $values['chips_price_high'] = (string)max(0, (int)($_POST['chips_price_high'] ?? 7000));
    }
    /* Чекбоксы: 0 если снят (не пришёл), но ТОЛЬКО для реально отрендеренных в форме */
    foreach (['yk_enabled', 'upsell_enabled', 'hero_text_enabled', 'yandex_reviews_enabled',
              'wa_enabled', 'tg_enabled', 'vk_enabled', 'ig_enabled', 'email_enabled', 'max_enabled',
              'notify_enabled', 'logo_enabled',
              /* W100-fixH1 (I14): Вебвизор Метрики — запись сессий, по умолчанию ВЫКЛ */
              'metrika_webvisor',
              /* Витринные фичи (критерий 16): каждая отключаема из админки */
              'feature_delivery_badge', 'feature_faq', 'feature_countdown', 'feature_price_filter',
              'feature_favorites', 'feature_zone_check', 'feature_track_link', 'feature_favicon_badge', 'feature_webpush',
              /* Критик functional: gift-UX и слоты доставки — отключаемы (критерий 14) */
              'feature_gift_fields', 'feature_delivery_slots', 'feature_gallery',
              /* Промокод в корзине (критик functional top#3) */
              'feature_promo',
              /* Awwwards-design D8: блок «С этим берут» на странице товара */
              'feature_related',
              /* W96 (редизайн 5cv): новые блоки витрины — город, hero-промо, чипы, карусели,
                 секции каталога, поводы, магазины, SEO-текст, журнал */
              'feature_citybar', 'hero_promo_enabled', 'hero_delivery_card_enabled',
              /* S3 (v2026.3): коммерческий слой — тумблеры новых блоков */
              'infobar_enabled', 'split_enabled', 'cart_extra_postcard_enabled', 'cart_extra_chrysal_enabled',
              'cart_free_progress_enabled', 'mobilebar_enabled',
              'feature_marquee',
              'feature_chips', 'feature_carousels',
              'feature_section_hits', 'feature_section_premium', 'feature_section_budget', 'feature_section_addons',
              'feature_occasions', 'feature_stores', 'feature_seotext', 'feature_journal',
              /* S5: живой таймер доставки + модалка быстрого просмотра */
              'feature_delivery_now', 'feature_quickview'] as $cb) {
        if (!$cbTrackAll && !in_array($cb, $cbRendered, true)) { continue; } // не в форме — не трогаем
        $values[$cb] = isset($_POST[$cb]) ? '1' : '0';
    }
    /* cart_mode — select с валидацией всех трёх режимов витрины (header.php: drawer|hybrid|page) */
    if (array_key_exists('cart_mode', $_POST)) {
        $values['cart_mode'] = in_array($_POST['cart_mode'] ?? '', ['drawer', 'hybrid', 'page'], true) ? $_POST['cart_mode'] : 'drawer';
    }
    /* W100-fixH2 (J2): секретный ключ ЮKassa — паттерн «пароль не отдавать»:
       значения в HTML больше нет, поэтому пустая отправка = «не трогать», непусто = записать */
    if (array_key_exists('yk_secret_key', $values) && trim((string)$values['yk_secret_key']) === '') {
        unset($values['yk_secret_key']);
    }
    /* W100-fixH2 (J4): ссылочные настройки — whitelist схем ПРИ СОХРАНЕНИИ.
       Прямые href (hero/промо/журнал/MAX/Instagram): пусто, #якорь, /путь, https://, http://, tel:, mailto:.
       Идентификаторы (VK/WhatsApp/Telegram/ID на Яндекс Картах) витрина вклеивает в свой URL —
       для них достаточно «без схем/пробелов/разметки» (javascript:, data:, vbscript:, //host — невозможно).
       Недопустимое значение НЕ сохраняется, владелец получает flash-предупреждение. */
    $linkRejected = [];
    foreach (['hero_button_link','hero_promo_link','hero_ghost_link','journal_1_link','journal_2_link','journal_3_link',
                  'shop_max_link','shop_instagram'] as $lk) {
        if (isset($values[$lk]) && !safe_url_ok((string)$values[$lk])) {
            $linkRejected[] = $lk;
            unset($values[$lk]);
        }
    }
    foreach (['shop_vk','shop_whatsapp','shop_telegram','yandex_reviews_id'] as $lk) {
        if (!isset($values[$lk])) { continue; }
        $lv = trim((string)$values[$lk]);
        $okIdent = safe_url_ok($lv)
            || safe_url_identifier_ok($lv)
            || ($lk === 'yandex_reviews_id' && preg_match('/^\d{1,32}$/', $lv) === 1);
        if (!$okIdent) {
            $linkRejected[] = $lk;
            unset($values[$lk]);
        }
    }
    /* W98-fixD (D5): сотруднику платёжные ключи и каналы уведомлений не сохраняются —
       даже собранным руками POST (в форме этих полей у staff нет). Не затираем, а игнорируем. */
    if (!$isOwner) {
        foreach (['yk_enabled', 'yk_shop_id', 'yk_secret_key', 'vat_rate', 'notify_enabled'] as $ownerOnlyKey) {
            unset($values[$ownerOnlyKey]);
        }
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
    /* W96 (5cv): обложки журнала — новый файл заменяет, чекбокс удаляет (паттерн favicon) */
    foreach ([1, 2, 3] as $jn) {
        $jimg = saveUpload($_FILES["journal_{$jn}_image"] ?? [], IMG_UPLOADS_DIR);
        if ($jimg !== '') {
            deleteImage(setting("journal_{$jn}_image"), IMG_UPLOADS_DIR);
            $values["journal_{$jn}_image"] = $jimg;
        } elseif (isset($_POST["journal_{$jn}_image_remove"])) {
            deleteImage(setting("journal_{$jn}_image"), IMG_UPLOADS_DIR);
            $values["journal_{$jn}_image"] = '';
        }
    }
    saveSettings($values);
    if ($linkRejected !== []) {
        /* W100-fixH2 (J4): отклонённые ссылки — предупреждение, остальное сохранено */
        flash('Настройки сохранены. ' . implode(' ', array_map(
            static fn(string $k): string => 'Ссылка "' . $k . '" отклонена: недопустимый формат (значение не сохранено).',
            $linkRejected)), true);
    } else {
        flash('Настройки сохранены');
    }
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

/* W105 (3-b): показываем кнопки поворота только у файлов, реально лежащих
   в img/uploads с GD-читаемым расширением (svg/посевные пути — нет) */
$rotKeys = ['hero_image', 'logo_image', 'site_favicon', 'journal_1_image', 'journal_2_image', 'journal_3_image'];
$rotatable = static function (string $key) use ($s): bool {
    $f = trim((string)($s[$key] ?? ''));
    if ($f === '' || str_contains($f, '/')) { return false; }
    $p = IMG_UPLOADS_DIR . '/' . $f;
    if (!is_file($p)) { return false; }
    return in_array(strtolower((string)pathinfo($p, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
};
/* W105 (3-b): версия превью по md5 файла — после поворота превью в админке
   не показывается из кэша браузера старыми пикселями */
$imgVer = static function (string $key) use ($s): string {
    $f = trim((string)($s[$key] ?? ''));
    $p = IMG_UPLOADS_DIR . '/' . $f;
    return $f !== '' && is_file($p) ? substr((string)md5_file($p), 0, 8) : '0';
};

adminHeader('Настройки', 'settings');
flash();
?>
<h1>Настройки магазина</h1>
<style>
.card[id]{scroll-margin-top:120px}
@media(max-width:700px){.card[id]{scroll-margin-top:140px}} /* W105 (4-b): якоря секций чистят стек шапка(~64)+чипы(~60) */
</style>

<?php /* W98-fixD (D8): поиск по настройкам — фильтрует строки полей (label/placeholder)
       во всех разделах, чипы разделов и scrollspy не трогаем; пустой запрос = показать всё */ ?>
<div class="card" style="padding:14px 18px;margin-bottom:16px">
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <input class="input" id="settings-search" type="search" placeholder="Поиск по настройкам…" aria-label="Поиск по настройкам" autocomplete="off" style="max-width:420px;margin:0">
    <span id="settings-search-count" style="font-size:.82rem;font-weight:600;color:var(--rose-cta,#AE4A71)" aria-live="polite"></span>
  </div>
  <p style="font-size:.78rem;color:var(--ink-soft);margin:6px 0 0">Ищет по подписям полей и серым подсказкам-примерам во всех разделах сразу. Пустой запрос — показать всё. Скрытые поиском поля всё равно сохраняются.</p>
</div>

<?php /* Оглавление настроек (критик-владелец: «9 секций на одной простыне — листать всё»).
       Якоря-чипы, прыжок в один клик, sticky — всегда под рукой.
       W105 (4-b): обёртка .settings-nav (sticky) + внутренний скролл-ряд #top-nav —
       чип-подсказка «↔» живёт в обёртке: не маскируется и не уезжает вместе с рядом */ ?>
<div class="settings-nav">
<nav id="top-nav" class="dash-ranges" aria-label="Разделы настроек">
  <a href="#s-common">Общие</a>
  <a href="#s-main">Главная</a>
  <a href="#s-5cv">Витрина 5cv</a>
  <a href="#s-commerce">Продажи</a>
  <a href="#s-w103">Имиджевые блоки</a>
  <a href="#s-look">Вид</a>
  <a href="#s-features">Функции</a>
  <a href="#s-steps">Этапы</a>
  <a href="#s-contacts">Контакты</a>
  <a href="#s-cart">Корзина</a>
  <a href="#s-legal">Реквизиты</a>
  <a href="#s-pay">Оплата</a>
  <a href="#s-guarantees">Гарантии</a>
</nav>
</div>
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
    /* W105 (4-b): активный чип приезжает в кадр (3/12 видимых на телефоне —
       без этого spy подсвечивал невидимый чип). block:nearest — страницу не дёргает. */
    var cur = document.querySelector('#top-nav a[aria-current]');
    if (cur && cur.scrollIntoView) { try { cur.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'auto' }); } catch (e) {} }
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
    <?php if ($isOwner): ?>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="notify_enabled" style="width:auto" <?= ($s['notify_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
      Уведомления о новых заказах (email + Telegram)
    </label>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Главный выключатель. Секретный код бота (его «токен») и ваш Telegram-чат настраиваются в разделе «Профиль».</p>
    <?php else: /* W98-fixD (D5): каналы уведомлений — только владельцу */ ?>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 10px;padding:10px 12px;background:var(--bg-alt,#F1EAD9);border:1px dashed var(--ink-soft);border-radius:10px">🔒 Уведомления о новых заказах — <strong>доступно владельцу</strong>. Остальные настройки (тексты, витрина, контакты) вам открыты.</p>
    <?php endif; ?>
    <div class="grid2">
      <div>
        <label class="f" for="s-name">Название магазина *</label>
        <input class="input" id="s-name" name="shop_name" required value="<?= sv('shop_name', $s) ?>">
        <label class="f" for="s-phone">Телефон</label>
        <input class="input" id="s-phone" name="shop_phone" value="<?= sv('shop_phone', $s) ?>">
        <p style="font-size:.75rem;color:var(--ink-soft);margin:2px 0 8px">Основной телефон магазина — показывается в шапке и футере сайта и на страницах заказа. Короткий номер для шапки — в разделе «Контакты».</p>
        <label class="f" for="s-addr">Адрес</label>
        <input class="input" id="s-addr" name="shop_address" value="<?= sv('shop_address', $s) ?>">
        <label class="f" for="s-siteurl">Адрес сайта (для писем и ссылок)</label>
        <input class="input" id="s-siteurl" name="site_url" value="<?= sv('site_url', $s) ?>" placeholder="https://flowers.interfood-catering.ru">
        <p style="font-size:.75rem;color:var(--ink-soft);margin:2px 0 8px">Используется в письмах клиенту и ссылках восстановления пароля. Пусто — берётся домен по умолчанию.</p>
      </div>
      <div>
        <label class="f" for="s-logo" style="color:var(--rose-cta,#AE4A71);font-size:.95rem">🖼 Логотип в шапке сайта</label>
        <input class="input" id="s-logo" name="logo_image" type="file" accept="image/*">
        <?php if (($s['logo_image'] ?? '') !== ''): ?>
          <?php $logoPath = (__DIR__) . '/../img/uploads/' . $s['logo_image']; $logoVer = is_file($logoPath) ? substr(md5_file($logoPath), 0, 8) : '0'; ?>
          <img class="thumb" style="margin-top:8px" src="/img/uploads/<?= e($s['logo_image']) ?>?v=<?= $logoVer ?>" alt="">
          <?php if ($rotatable('logo_image')) { rotateControlsButtons('rot-logo_image'); } ?>
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
        <input class="input" id="h-eyebrow" name="hero_eyebrow" value="<?= sv('hero_eyebrow', $s) !== '' ? sv('hero_eyebrow', $s) : 'Санкт-Петербург · собираем под ваш заказ' ?>" maxlength="60">
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
          <?php /* W105 (3-b): ?v= по md5 — поворот виден сразу, не из кэша */ ?>
          <img class="thumb" style="margin-top:8px;width:120px;height:80px;object-fit:cover" src="/img/uploads/<?= e($s['hero_image']) ?>?v=<?= $imgVer('hero_image') ?>" alt="Текущее фото главной">
          <?php if ($rotatable('hero_image')) { rotateControlsButtons('rot-hero_image'); } ?>
        <?php endif; ?>
        <?php /* W97-fixB3a (B3a-3): alt hero-фото — ключ читается витриной с дефолтом из кода
           (index.php), здесь нужен только для редактирования владельцем. */ ?>
        <label class="f" for="h-img-alt" style="margin-top:8px">Описание фото (alt — для поисковиков и скринридеров)</label>
        <input class="input" id="h-img-alt" name="hero_image_alt" value="<?= sv('hero_image_alt', $s) !== '' ? sv('hero_image_alt', $s) : 'Свежий букет из сезонных цветов — витрина магазина' ?>" maxlength="160">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Пишется в alt большого фото на главной — по нему фото находят в поиске по картинкам и читает скринридер.</p>
      </div>
    </div>
    <?php /* W104-theta (P1 критика C3-G3): дубль полей marquee_1..4 в этой секции удалён —
       копия ниже («Имиджевые блоки W103») шла позже по DOM и при submit молча перебивала
       эти поля: «Настройки сохранены» показывалось, а витрина не менялась.
       Бегущая лента редактируется в одном месте — секция W103, поля «Фраза N — бегущая строка». */ ?>
    <?php /* Заголовки секций главной (критерий 16) */ ?>
    <p style="font-size:.85rem;font-weight:600;margin:14px 0 8px">Заголовки разделов на главной</p>
    <div class="grid2">
      <div>
        <label class="f" for="cat-t">Заголовок каталога</label>
        <input class="input" id="cat-t" name="catalog_title" value="<?= sv('catalog_title', $s) !== '' ? sv('catalog_title', $s) : 'Каталог' ?>" maxlength="40">
        <label class="f" for="cat-s" style="margin-top:8px">Подпись каталога</label>
        <input class="input" id="cat-s" name="catalog_subtitle" value="<?= sv('catalog_subtitle', $s) !== '' ? sv('catalog_subtitle', $s) : 'Выбирайте букет — соберём и привезём сегодня' ?>" maxlength="90">
        <?php /* W104: eyebrow + нумерал каталога (единый компонент шапки секции) */ ?>
        <label class="f" for="cat-eb" style="margin-top:8px">Eyebrow каталога («капс|курсив»)</label>
        <input class="input" id="cat-eb" name="catalog_eyebrow" value="<?= sv('catalog_eyebrow', $s) !== '' ? sv('catalog_eyebrow', $s) : 'Весь|ассортимент' ?>" maxlength="60">
      </div>
      <div>
        <label class="f" for="ord-t">Заголовок формы заказа</label>
        <input class="input" id="ord-t" name="order_title" value="<?= sv('order_title', $s) !== '' ? sv('order_title', $s) : 'Оформление заказа' ?>" maxlength="40">
      </div>
    </div>
  </div>

  <?php /* W96 (редизайн 5cv): тексты и тумблеры новых блоков витрины */ ?>
  <div class="card" id="s-5cv">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Витрина 5cv (новый дизайн)</h2>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 14px">Блоки нового дизайна: бар города, hero-бенто с промо-карточкой, чипы цен, секции «Хиты» / «Премиум» / «До N ₽» / «Дополните букет», поводы, магазины, SEO-текст и журнал. Пустые тексты на сайте не показываются.</p>

    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Шапка и город</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="feature_citybar" style="width:auto" <?= sv('feature_citybar', $s) !== '0' ? 'checked' : '' ?>>
      Полоска «Санкт-Петербург — ваш город?» над шапкой
    </label>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Покупатель сразу видит город доставки — и не звонит уточнять.</p>
    <div class="grid2">
      <div>
        <label class="f" for="cb-bar">Текст полоски города</label>
        <input class="input" id="cb-bar" name="citybar_text" value="<?= sv('citybar_text', $s) !== '' ? sv('citybar_text', $s) : 'Санкт-Петербург — ваш город?' ?>" maxlength="90">
        <label class="f" for="cb-city" style="margin-top:8px">Название города в шапке</label>
        <input class="input" id="cb-city" name="city_label" value="<?= sv('city_label', $s) !== '' ? sv('city_label', $s) : 'Санкт-Петербург' ?>" maxlength="40">
      </div>
      <div>
        <label class="f" for="cb-search">Подсказка в строке поиска</label>
        <input class="input" id="cb-search" name="search_placeholder" value="<?= sv('search_placeholder', $s) !== '' ? sv('search_placeholder', $s) : 'Розы, пионы, букет маме…' ?>" maxlength="60">
        <label class="f" for="cb-cat" style="margin-top:8px">Текст кнопки «Каталог» в шапке</label>
        <input class="input" id="cb-cat" name="catalog_btn_text" value="<?= sv('catalog_btn_text', $s) !== '' ? sv('catalog_btn_text', $s) : 'Каталог' ?>" maxlength="30">
      </div>
    </div>

    <?php /* Hero-бенто: промо-карточка + карточка доставки */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Hero-бенто: промо-карточка и доставка</p>
    <div class="grid2">
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="hero_promo_enabled" style="width:auto" <?= sv('hero_promo_enabled', $s) !== '0' ? 'checked' : '' ?>>
          Тёмная промо-карточка рядом с фото
        </label>
        <label class="f" for="hp-badge" style="margin-top:8px">Мета-подпись на промо-карточке (капс)</label>
        <input class="input" id="hp-badge" name="hero_promo_badge" value="<?= sv('hero_promo_badge', $s) !== '' ? sv('hero_promo_badge', $s) : 'К каждому букету' ?>" maxlength="30">
        <label class="f" for="hp-title" style="margin-top:8px">Заголовок промо-карточки (Playfair-курсив)</label>
        <input class="input" id="hp-title" name="hero_promo_title" value="<?= sv('hero_promo_title', $s) !== '' ? sv('hero_promo_title', $s) : 'Открытка в подарок' ?>" maxlength="80">
        <label class="f" for="hp-text" style="margin-top:8px">Текст промо-карточки</label>
        <textarea class="input" id="hp-text" name="hero_promo_text" rows="2" maxlength="200"><?= sv('hero_promo_text', $s) !== '' ? sv('hero_promo_text', $s) : 'Напишем ваш текст от руки и вложим в букет — это бесплатно' ?></textarea>
        <div style="display:flex;gap:12px;margin-top:8px">
          <div style="flex:1">
            <label class="f" for="hp-btn">Кнопка на карточке</label>
            <input class="input" id="hp-btn" name="hero_promo_btn_text" value="<?= sv('hero_promo_btn_text', $s) !== '' ? sv('hero_promo_btn_text', $s) : 'Выбрать букет' ?>" maxlength="30">
          </div>
          <div style="flex:1">
            <label class="f" for="hp-link">Ссылка кнопки</label>
            <input class="input" id="hp-link" name="hero_promo_link" value="<?= sv('hero_promo_link', $s) !== '' ? sv('hero_promo_link', $s) : '#catalog' ?>" maxlength="200" placeholder="#catalog или /occasion/…">
          </div>
        </div>
      </div>
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="hero_delivery_card_enabled" style="width:auto" <?= sv('hero_delivery_card_enabled', $s) !== '0' ? 'checked' : '' ?>>
          Карточка доставки в hero
        </label>
        <label class="f" for="hd-title" style="margin-top:8px">Заголовок карточки доставки</label>
        <input class="input" id="hd-title" name="hero_delivery_title" value="<?= sv('hero_delivery_title', $s) !== '' ? sv('hero_delivery_title', $s) : 'Доставка в день заказа' ?>" maxlength="60">
        <label class="f" for="hd-text" style="margin-top:8px">Текст карточки доставки</label>
        <textarea class="input" id="hd-text" name="hero_delivery_text" rows="2" maxlength="160"><?= sv('hero_delivery_text', $s) !== '' ? sv('hero_delivery_text', $s) : 'По Санкт-Петербургу — оформите до 20:00, привезём сегодня' ?></textarea>
        <?php /* W104-a2: specifics карточки — 2 строки с amber-маркером (index.php) */ ?>
        <label class="f" for="hd-p1" style="margin-top:8px">Строка-услуга №1 (с маркером)</label>
        <input class="input" id="hd-p1" name="hero_delivery_point_1" value="<?= sv('hero_delivery_point_1', $s) !== '' ? sv('hero_delivery_point_1', $s) : 'Привезём сегодня за 2 часа' ?>" maxlength="60">
        <label class="f" for="hd-p2" style="margin-top:8px">Строка-услуга №2 (с маркером)</label>
        <input class="input" id="hd-p2" name="hero_delivery_point_2" value="<?= sv('hero_delivery_point_2', $s) !== '' ? sv('hero_delivery_point_2', $s) : 'Фото перед отправкой' ?>" maxlength="60">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:8px 0 0">Ссылка кнопки промо-карточки: <code>#order</code> — к форме заказа, <code>#catalog</code> — к каталогу, или полный адрес страницы.</p>
      </div>
    </div>

    <?php /* Чипы цен + карусели */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Чипы цен и карусели</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="feature_chips" style="width:auto" <?= sv('feature_chips', $s) !== '0' ? 'checked' : '' ?>>
      Чипы цен над каталогом (Хиты · До N · N–M · От M · Премиум)
    </label>
    <div class="grid2" style="margin-top:8px">
      <div>
        <label class="f" for="cp-low">«До …» — граница, ₽</label>
        <input class="input" id="cp-low" name="chips_price_low" type="number" min="100" max="200000" step="100" value="<?= sv('chips_price_low', $s) !== '' ? sv('chips_price_low', $s) : '3500' ?>">
      </div>
      <div>
        <label class="f" for="cp-high">«От …» — граница, ₽</label>
        <input class="input" id="cp-high" name="chips_price_high" type="number" min="200" max="200000" step="100" value="<?= sv('chips_price_high', $s) !== '' ? sv('chips_price_high', $s) : '7000' ?>">
      </div>
    </div>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:10px">
      <input type="checkbox" name="feature_carousels" style="width:auto" <?= sv('feature_carousels', $s) !== '0' ? 'checked' : '' ?>>
      Стрелки-карусели у секций каталога
    </label>

    <?php /* Секции каталога */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Секции каталога: Хиты, Премиум, Бюджет, Дополнения</p>
    <div class="grid2">
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_section_hits" style="width:auto" <?= sv('feature_section_hits', $s) !== '0' ? 'checked' : '' ?>>
          Секция «Хиты продаж»
        </label>
        <div style="margin-left:26px">
          <label class="f" for="ht-t">Заголовок</label>
          <input class="input" id="ht-t" name="section_hits_title" value="<?= sv('section_hits_title', $s) !== '' ? sv('section_hits_title', $s) : 'Хиты продаж' ?>" maxlength="60">
          <label class="f" for="ht-s" style="margin-top:8px">Подпись</label>
          <input class="input" id="ht-s" name="section_hits_sub" value="<?= sv('section_hits_sub', $s) !== '' ? sv('section_hits_sub', $s) : 'Выбор, который сложно испортить' ?>" maxlength="120">
        </div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:10px">
          <input type="checkbox" name="feature_section_premium" style="width:auto" <?= sv('feature_section_premium', $s) !== '0' ? 'checked' : '' ?>>
          Секция «Премиум» — тёмный разворот
        </label>
        <?php /* W103 (F1): премиум — теперь разворот, тексты переехали в карточку
               «Имиджевые блоки (W103)» (ключи premium_*) */ ?>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 0 26px">Заголовок, подпись и цену-от разворота редактируйте в карточке «Имиджевые блоки (W103)» ниже.</p>
      </div>
      <div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_section_budget" style="width:auto" <?= sv('feature_section_budget', $s) !== '0' ? 'checked' : '' ?>>
          Секция «Букеты до N ₽»
        </label>
        <div style="margin-left:26px">
          <label class="f" for="bg-t">Заголовок (%s — подставится граница чипа)</label>
          <input class="input" id="bg-t" name="section_budget_title" value="<?= sv('section_budget_title', $s) !== '' ? sv('section_budget_title', $s) : 'Букеты до %s ₽' ?>" maxlength="60">
        </div>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:10px">
          <input type="checkbox" name="feature_section_addons" style="width:auto" <?= sv('feature_section_addons', $s) !== '0' ? 'checked' : '' ?>>
          Секция «Дополните букет»
        </label>
        <div style="margin-left:26px">
          <label class="f" for="ad-t">Заголовок</label>
          <input class="input" id="ad-t" name="section_addons_title" value="<?= sv('section_addons_title', $s) !== '' ? sv('section_addons_title', $s) : 'Дополните букет 🎈' ?>" maxlength="60">
        </div>
        <label class="f" for="bd-hit" style="margin-top:10px">Текст бейджа «Хит» на карточке</label>
        <input class="input" id="bd-hit" name="badge_hit_text" value="<?= sv('badge_hit_text', $s) !== '' ? sv('badge_hit_text', $s) : 'Хит' ?>" maxlength="20">
        <label class="f" for="bd-prem" style="margin-top:8px">Текст бейджа «Премиум» на карточке</label>
        <input class="input" id="bd-prem" name="badge_premium_text" value="<?= sv('badge_premium_text', $s) !== '' ? sv('badge_premium_text', $s) : 'Премиум' ?>" maxlength="20">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:8px 0 0">Товары попадают в секции галочками «Хит продаж» и «Премиум» в разделе «Товары».</p>
        <?php /* W97-fixB3a (B3a-3): editorial-строка — ключ читается витриной с дефолтом из кода
           (index.php), здесь нужен только для редактирования владельцем. */ ?>
        <label class="f" for="ed-text" style="margin-top:10px">Первая editorial-цитата (между «До N ₽» и «Дополните букет»)</label>
        <textarea class="input" id="ed-text" name="editorial_text" rows="2" maxlength="300"><?= sv('editorial_text', $s) !== '' ? sv('editorial_text', $s) : 'Каждое утро начинается с поставки: цветы не ждут склада — они ждут получателя' ?></textarea>
        <?php /* W99-fixG2 (H14): editorial №2 и №3 — ключи читает index.php
               (render_fc_editorial), дефолты те же, что в коде витрины;
               пустое значение скрывает соответствующую врезку. */ ?>
        <label class="f" for="ed-text2" style="margin-top:8px">Вторая editorial-цитата (после каталога)</label>
        <textarea class="input" id="ed-text2" name="editorial_text_2" rows="2" maxlength="300"><?= sv('editorial_text_2', $s) !== '' ? sv('editorial_text_2', $s) : 'Не нашли нужный букет? Соберём авторский — под ваш повод, палитру и бюджет' ?></textarea>
        <label class="f" for="ed-text3" style="margin-top:8px">Третья editorial-цитата (перед разделом FAQ)</label>
        <textarea class="input" id="ed-text3" name="editorial_text_3" rows="2" maxlength="300"><?= sv('editorial_text_3', $s) !== '' ? sv('editorial_text_3', $s) : 'Курьер выезжает после того, как вы одобрили фото готового букета' ?></textarea>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Три pull-цитаты (Playfair-курсив с янтарной линией): после «До N ₽», после каталога и перед FAQ. Очистите поле — цитата исчезнет с сайта.</p>
      </div>
    </div>

    <?php /* W98-fixE (E1/E18): интро посадочных /category/{slug} — ручные тексты
       редактируются здесь; пустое значение = витрина показывает грамматический
       фолбэк «{Название} с доставкой по Санкт-Петербургу…» */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Категории каталога — интро посадочных страниц</p>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:0 0 10px">Текст под заголовком на странице категории (/category/…) и её meta description. Пустое поле — витрина подставит нейтральный шаблон «Название с доставкой по Санкт-Петербургу…».</p>
    <div class="grid2">
      <div>
        <label class="f" for="ci-rozy">Розы — /category/rozy</label>
        <textarea class="input" id="ci-rozy" name="category_intro_rozy" rows="3" maxlength="400"><?= sv('category_intro_rozy', $s) ?></textarea>
        <label class="f" for="ci-sbornye" style="margin-top:8px">Сборные букеты — /category/sbornye-bukety</label>
        <textarea class="input" id="ci-sbornye" name="category_intro_sbornye-bukety" rows="3" maxlength="400"><?= sv('category_intro_sbornye-bukety', $s) ?></textarea>
        <label class="f" for="ci-polevye" style="margin-top:8px">Полевые цветы — /category/polevye-cvety</label>
        <textarea class="input" id="ci-polevye" name="category_intro_polevye-cvety" rows="3" maxlength="400"><?= sv('category_intro_polevye-cvety', $s) ?></textarea>
      </div>
      <div>
        <label class="f" for="ci-avtorskie">Авторские букеты — /category/avtorskie-bukety</label>
        <textarea class="input" id="ci-avtorskie" name="category_intro_avtorskie-bukety" rows="3" maxlength="400"><?= sv('category_intro_avtorskie-bukety', $s) ?></textarea>
        <label class="f" for="ci-korobke" style="margin-top:8px">В шляпной коробке — /category/v-shlyapnoy-korobke</label>
        <textarea class="input" id="ci-korobke" name="category_intro_v-shlyapnoy-korobke" rows="3" maxlength="400"><?= sv('category_intro_v-shlyapnoy-korobke', $s) ?></textarea>
        <label class="f" for="ci-sladkie" style="margin-top:8px">Сладкие подарки — /category/sladkie-podarki</label>
        <textarea class="input" id="ci-sladkie" name="category_intro_sladkie-podarki" rows="3" maxlength="400"><?= sv('category_intro_sladkie-podarki', $s) ?></textarea>
      </div>
    </div>

    <?php /* Поводы, магазины, SEO-текст */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Поводы, магазины, SEO-текст</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="feature_occasions" style="width:auto" <?= sv('feature_occasions', $s) !== '0' ? 'checked' : '' ?>>
      Плитки «Цветы по поводам»
    </label>
    <div style="margin-left:26px">
      <label class="f" for="oc-t">Заголовок блока поводов</label>
      <input class="input" id="oc-t" name="occasions_title" value="<?= sv('occasions_title', $s) !== '' ? sv('occasions_title', $s) : 'Цветы по поводам' ?>" maxlength="60">
      <?php /* W104: eyebrow поводов (тот же формат «капс|курсив») */ ?>
      <label class="f" for="oc-eb" style="margin-top:8px">Eyebrow блока поводов</label>
      <input class="input" id="oc-eb" name="occasions_eyebrow" value="<?= sv('occasions_eyebrow', $s) !== '' ? sv('occasions_eyebrow', $s) : 'Повод|найти просто' ?>" maxlength="60">
      <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Сами плитки-поводы и их лендинги настраиваются в разделе «Поводы».</p>
    </div>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:10px">
      <input type="checkbox" name="feature_stores" style="width:auto" <?= sv('feature_stores', $s) === '1' ? 'checked' : '' ?>>
      Секция «Наши магазины»
    </label>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 8px 26px">Показываются только заполненные магазины — пустые карточки не выводятся.</p>
    <div class="grid2">
      <div>
        <label class="f" for="str-t">Заголовок секции магазинов</label>
        <input class="input" id="str-t" name="stores_title" value="<?= sv('stores_title', $s) !== '' ? sv('stores_title', $s) : 'Наши магазины в Петербурге' ?>" maxlength="60">
        <label class="f" for="st-s" style="margin-top:8px">Подпись</label>
        <input class="input" id="st-s" name="stores_sub" value="<?= sv('stores_sub', $s) !== '' ? sv('stores_sub', $s) : 'Заберите сами или закажите доставку — букет будет готов в течение дня' ?>" maxlength="140">
      </div>
      <div>
        <?php for ($stn = 1; $stn <= 3; $stn++): ?>
        <label class="f" for="st-<?= $stn ?>-t">Магазин <?= $stn ?> — название</label>
        <input class="input" id="st-<?= $stn ?>-t" name="stores_<?= $stn ?>_title" value="<?= sv("stores_{$stn}_title", $s) ?>" maxlength="80" placeholder="напр. Невский проспект">
        <label class="f" for="st-<?= $stn ?>-x" style="margin-top:8px">Магазин <?= $stn ?> — адрес и часы</label>
        <input class="input" id="st-<?= $stn ?>-x" name="stores_<?= $stn ?>_text" value="<?= sv("stores_{$stn}_text", $s) ?>" maxlength="160" placeholder="напр. Невский пр., 100 · ежедневно 9:00–21:00">
        <?php endfor; ?>
      </div>
    </div>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:10px">
      <input type="checkbox" name="feature_seotext" style="width:auto" <?= sv('feature_seotext', $s) !== '0' ? 'checked' : '' ?>>
      SEO-текст под каталогом
    </label>
    <label class="f" for="seo-5cv-t">Заголовок SEO-блока</label>
    <input class="input" id="seo-5cv-t" name="seo_text_title" value="<?= sv('seo_text_title', $s) !== '' ? sv('seo_text_title', $s) : 'Доставка цветов в Санкт-Петербурге' ?>" maxlength="120">
    <label class="f" for="seo-5cv-b" style="margin-top:8px">Текст (абзацы — через пустую строку)</label>
    <textarea class="input" id="seo-5cv-b" name="seo_text_body" rows="8"><?= sv('seo_text_body', $s) ?></textarea>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Честный текст про зоны, сроки, свежесть и оплату помогает поисковикам. Разрешены ссылки вида <code>&lt;a href="/track"&gt;…&lt;/a&gt;</code> — как в тексте cookie-окна.</p>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Футер, товар и спасибо-страница</p>
    <div class="grid2">
      <div>
        <label class="f" for="ft-about">Описание магазина в футере</label>
        <input class="input" id="ft-about" name="footer_about" value="<?= sv('footer_about', $s) !== '' ? sv('footer_about', $s) : 'Свежие букеты с доставкой по всему Санкт-Петербургу' ?>" maxlength="140">
        <label class="f" for="pt-today" style="margin-top:8px">Строка «доставим сегодня» на странице товара</label>
        <input class="input" id="pt-today" name="product_today_text" value="<?= sv('product_today_text', $s) !== '' ? sv('product_today_text', $s) : 'Оформите до 20:00 — доставим сегодня' ?>" maxlength="90">
      </div>
      <div>
        <label class="f" for="th-call">Обещание звонка на спасибо-странице</label>
        <input class="input" id="th-call" name="thanks_call_text" value="<?= sv('thanks_call_text', $s) !== '' ? sv('thanks_call_text', $s) : 'Мы позвоним в течение 15 минут для подтверждения' ?>" maxlength="90">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Пустые строки «доставим сегодня» скрывают строку; футер и звонок показываются всегда (пустая = дефолт).</p>
      </div>
    </div>

    <?php /* Журнал */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Журнал (карточки статей)</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="feature_journal" style="width:auto" <?= sv('feature_journal', $s) === '1' ? 'checked' : '' ?>>
      Блок «Журнал» на главной
    </label>
    <div style="margin-left:26px">
      <label class="f" for="jr-t">Заголовок блока</label>
      <input class="input" id="jr-t" name="journal_title" value="<?= sv('journal_title', $s) !== '' ? sv('journal_title', $s) : 'Журнал Nilov Flowers' ?>" maxlength="80">
    </div>
    <?php for ($jr = 1; $jr <= 3; $jr++): ?>
    <div class="grid2" style="margin-top:12px">
      <div>
        <label class="f" for="jr<?= $jr ?>-t">Статья <?= $jr ?> — заголовок</label>
        <input class="input" id="jr<?= $jr ?>-t" name="journal_<?= $jr ?>_title" value="<?= sv("journal_{$jr}_title", $s) ?>" maxlength="120">
        <label class="f" for="jr<?= $jr ?>-x" style="margin-top:8px">Статья <?= $jr ?> — короткий текст</label>
        <input class="input" id="jr<?= $jr ?>-x" name="journal_<?= $jr ?>_text" value="<?= sv("journal_{$jr}_text", $s) ?>" maxlength="300">
      </div>
      <div>
        <label class="f" for="jr<?= $jr ?>-l">Статья <?= $jr ?> — ссылка</label>
        <input class="input" id="jr<?= $jr ?>-l" name="journal_<?= $jr ?>_link" value="<?= sv("journal_{$jr}_link", $s) ?>" maxlength="200" placeholder="/track или https://…">
        <label class="f" for="jr<?= $jr ?>-img" style="margin-top:8px">Статья <?= $jr ?> — обложка</label>
        <input class="input" id="jr<?= $jr ?>-img" name="journal_<?= $jr ?>_image" type="file" accept="image/*">
        <?php if (($s["journal_{$jr}_image"] ?? '') !== ''): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-top:8px;flex-wrap:wrap">
            <img class="thumb" style="width:64px;height:48px;object-fit:cover" src="/img/uploads/<?= e($s["journal_{$jr}_image"]) ?>?v=<?= $imgVer("journal_{$jr}_image") ?>" alt="Обложка статьи <?= $jr ?>">
            <label class="f" style="display:flex;gap:6px;align-items:center;font-weight:400;font-size:.85rem;margin:0">
              <input type="checkbox" name="journal_<?= $jr ?>_image_remove" value="1" style="width:auto">
              Удалить обложку
            </label>
            <?php if ($rotatable("journal_{$jr}_image")) { rotateControlsButtons('rot-journal_' . $jr . '_image'); } ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endfor; ?>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:8px 0 0">Пустые статьи не показываются — заполните только нужные. Новая обложка заменяет старую.</p>
  </div>

  <?php /* W103 (F1, редизайн главной): имиджевые блоки — marquee, manifesto,
     премиум-разворот, ghost-CTA, тэглайн футера, eyebrow-метки секций */ ?>
  <div class="card" id="s-commerce">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Продажи: инфо-бар, Сплит, допы, WhatsApp, 1-клик</h2>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 14px">S3 (v2026.3). Плашка преимуществ в шапке, калькулятор Яндекс&nbsp;Сплит/Долями, бесплатные допы корзины, шаблоны сообщений WhatsApp, быстрый заказ и чипсы-теги каталога. Всё сохраняется в базу и мгновенно обновляется на витрине.</p>

    <p style="font-size:.9rem;font-weight:700;margin:0 0 6px">Верхняя строка шапки (S6)</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="infobar_enabled" style="width:auto" <?= sv('infobar_enabled', $s) !== '0' ? 'checked' : '' ?>>
      Показывать верхнюю строку
    </label>
    <label class="f" for="s-tbd">Обещание доставки (рядом с городом)</label>
    <input class="input" id="s-tbd" name="topbar_delivery_text" value="<?= sv('topbar_delivery_text', $s) ?>">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 14px">Верхняя строка: «Санкт-Петербург • [это обещание]», справа — телефон и WhatsApp (из раздела «Контакты»).</p>

    <p style="font-size:.9rem;font-weight:700;margin:0 0 6px">Бейджи доверия под шапкой (S6)</p>
    <label class="f" for="s-tb1">Бейдж 1 (⚡ доставка)</label>
    <input class="input" id="s-tb1" name="trust_badge_1" value="<?= sv('trust_badge_1', $s) ?>">
    <label class="f" for="s-tb2">Бейдж 2 (📸 фото)</label>
    <input class="input" id="s-tb2" name="trust_badge_2" value="<?= sv('trust_badge_2', $s) ?>">
    <label class="f" for="s-tb3">Бейдж 3 (⭐ рейтинг)</label>
    <input class="input" id="s-tb3" name="trust_badge_3" value="<?= sv('trust_badge_3', $s) ?>">
    <label class="f" for="s-tb4">Бейдж 4 (💳 Сплит)</label>
    <input class="input" id="s-tb4" name="trust_badge_4" value="<?= sv('trust_badge_4', $s) ?>">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 14px">Горизонтальный ряд из 4 бейджей сразу под шапкой; на мобильном — аккуратный скролл. Пустое поле скрывает бейдж.</p>

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Яндекс Сплит / Долями</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="split_enabled" style="width:auto" <?= sv('split_enabled', $s) !== '0' ? 'checked' : '' ?>>
      Показывать шильдик «Сплит: от N ₽/мес» в карточках и на странице букета
    </label>
    <label class="f" for="s-split">На сколько платежей делить цену</label>
    <input class="input" id="s-split" name="split_divider" type="number" min="2" max="12" value="<?= sv('split_divider', $s) ?: '4' ?>">
    <label class="f" for="s-splabel">Шаблон шильдика ({price} — сумма платежа)</label>
    <input class="input" id="s-splabel" name="split_label" value="<?= sv('split_label', $s) ?>">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Например: «Сплит: от {price} ₽/мес». Первую часть клиент платит сразу, остальные — раз в месяц.</p>

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Бесплатная доставка: порог и прогресс-бар в корзине</p>
    <label class="f" for="s-free">Порог бесплатной доставки, ₽ (0 — выключить)</label>
    <input class="input" id="s-free" name="free_delivery_threshold" type="number" min="0" value="<?= sv('free_delivery_threshold', $s) ?>">
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="cart_free_progress_enabled" style="width:auto" <?= sv('cart_free_progress_enabled', $s) !== '0' ? 'checked' : '' ?>>
      Показывать прогресс-бар до бесплатной доставки в корзине
    </label>
    <label class="f" for="s-fpu">Текст под прогресс-баром ({left} — сколько осталось)</label>
    <input class="input" id="s-fpu" name="cart_free_progress_under" value="<?= sv('cart_free_progress_under', $s) ?>">
    <label class="f" for="s-fpr">Текст при достигнутом пороге</label>
    <input class="input" id="s-fpr" name="cart_free_progress_reached" value="<?= sv('cart_free_progress_reached', $s) ?>">

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Бесплатные допы в корзине (0 ₽)</p>
    <label class="f" for="s-cet">Заголовок блока допов</label>
    <input class="input" id="s-cet" name="cart_extras_title" value="<?= sv('cart_extras_title', $s) ?>">
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="cart_extra_postcard_enabled" style="width:auto" <?= sv('cart_extra_postcard_enabled', $s) !== '0' ? 'checked' : '' ?>>
      Открытка с текстом клиента (0 ₽)
    </label>
    <label class="f" for="s-pct">Название открытки</label>
    <input class="input" id="s-pct" name="cart_extra_postcard_title" value="<?= sv('cart_extra_postcard_title', $s) ?>">
    <label class="f" for="s-pctx">Подпись открытки</label>
    <input class="input" id="s-pctx" name="cart_extra_postcard_text" value="<?= sv('cart_extra_postcard_text', $s) ?>">
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="cart_extra_chrysal_enabled" style="width:auto" <?= sv('cart_extra_chrysal_enabled', $s) !== '0' ? 'checked' : '' ?>>
      Подкормка Chrysal (0 ₽)
    </label>
    <label class="f" for="s-cht">Название подкормки</label>
    <input class="input" id="s-cht" name="cart_extra_chrysal_title" value="<?= sv('cart_extra_chrysal_title', $s) ?>">
    <label class="f" for="s-chtx">Подпись подкормки</label>
    <input class="input" id="s-chtx" name="cart_extra_chrysal_text" value="<?= sv('cart_extra_chrysal_text', $s) ?>">

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Шаблоны WhatsApp (кнопки в заказах и канбане)</p>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:0 0 8px">Подстановки: {id} — номер заказа, {name} — имя клиента, {time} — интервал доставки, {phone} — телефон. Номер получателя — получатель, если указан, иначе заказчик.</p>
    <label class="f" for="s-wan">Шаблон «Новый заказ»</label>
    <textarea class="input" id="s-wan" name="wa_template_new" rows="2" style="padding:10px 12px"><?= sv('wa_template_new', $s) ?></textarea>
    <label class="f" for="s-wap">Шаблон «Согласование фото»</label>
    <textarea class="input" id="s-wap" name="wa_template_photo" rows="2" style="padding:10px 12px"><?= sv('wa_template_photo', $s) ?></textarea>
    <label class="f" for="s-wac">Шаблон «Курьер выехал»</label>
    <textarea class="input" id="s-wac" name="wa_template_courier" rows="2" style="padding:10px 12px"><?= sv('wa_template_courier', $s) ?></textarea>

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Купить в 1 клик</p>
    <label class="f" for="s-oct">Заголовок модалки</label>
    <input class="input" id="s-oct" name="oneclick_title" value="<?= sv('oneclick_title', $s) ?>">
    <label class="f" for="s-ocn">Обещание под заголовком</label>
    <input class="input" id="s-ocn" name="oneclick_note" value="<?= sv('oneclick_note', $s) ?>">
    <label class="f" for="s-ocb">Текст кнопки</label>
    <input class="input" id="s-ocb" name="oneclick_btn" value="<?= sv('oneclick_btn', $s) ?>">

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Чипсы каталога и кнопки карточки</p>
    <label class="f" for="s-chips">Чипсы-теги (через запятую; теги букетов — в «Товарах»)</label>
    <input class="input" id="s-chips" name="chips_tags" value="<?= sv('chips_tags', $s) ?>">
    <label class="f" for="s-chipa">Текст чипа «Все»</label>
    <input class="input" id="s-chipa" name="chips_all_text" value="<?= sv('chips_all_text', $s) ?>">
    <label class="f" for="s-btn1">Кнопка в карточке — добавить в корзину</label>
    <input class="input" id="s-btn1" name="card_btn_cart" value="<?= sv('card_btn_cart', $s) ?>">
    <label class="f" for="s-btn2">Кнопка в карточке — быстрый заказ</label>
    <input class="input" id="s-btn2" name="card_btn_oneclick" value="<?= sv('card_btn_oneclick', $s) ?>">
    <label class="f" for="s-cdeliv">Строка доставки в карточке (пусто — скрыть)</label>
    <input class="input" id="s-cdeliv" name="card_delivery_text" value="<?= sv('card_delivery_text', $s) ?>">
    <label class="f" for="s-splitchip">Чип сплита в карточке</label>
    <input class="input" id="s-splitchip" name="split_chip_text" value="<?= sv('split_chip_text', $s) ?>">

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Товарные полки каталога (S7)</p>
    <label class="f" for="s-shelf1">Заголовок полки 1 — хиты</label>
    <input class="input" id="s-shelf1" name="shelf_hits_title" value="<?= sv('shelf_hits_title', $s) ?>">
    <label class="f" for="s-shelf2">Заголовок полки 2 — авторские букеты и розы</label>
    <input class="input" id="s-shelf2" name="shelf_author_title" value="<?= sv('shelf_author_title', $s) ?>">
    <label class="f" for="s-shelf3">Заголовок полки 3 — премиум и коробки</label>
    <input class="input" id="s-shelf3" name="shelf_premium_title" value="<?= sv('shelf_premium_title', $s) ?>">

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Плашки навигации над каталогом (S9)</p>
    <label class="f" for="s-tilemono">Подпись плашки «Монобукеты»</label>
    <input class="input" id="s-tilemono" name="budget_tile_mono_label" value="<?= sv('budget_tile_mono_label', $s) ?>">
    <label class="f" for="s-tilemonotag">Тег плашки «Монобукеты» (какой тег товаров считать монобукетом)</label>
    <input class="input" id="s-tilemonotag" name="budget_tile_mono_tag" value="<?= sv('budget_tile_mono_tag', $s) ?>">
    <label class="f" for="s-tilegifts">Подпись плашки «Хиты и подарки»</label>
    <input class="input" id="s-tilegifts" name="budget_tile_gifts_label" value="<?= sv('budget_tile_gifts_label', $s) ?>">
    <label class="f" for="s-tilegiftstag">Тег плашки «Хиты и подарки» (хиты + товары с этим тегом)</label>
    <input class="input" id="s-tilegiftstag" name="budget_tile_gifts_tag" value="<?= sv('budget_tile_gifts_tag', $s) ?>">

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Мастерская и отзывы (под каталогом)</p>
    <label class="f" for="s-wshours">Часы работы мастерской</label>
    <input class="input" id="s-wshours" name="workshop_hours" value="<?= sv('workshop_hours', $s) ?>">
    <label class="f" for="s-wsrating">Плашка рейтинга мастерской</label>
    <input class="input" id="s-wsrating" name="workshop_rating" value="<?= sv('workshop_rating', $s) ?>">
    <label class="f" for="s-wsnote">Подпись мастерской (пусто — скрыть)</label>
    <input class="input" id="s-wsnote" name="workshop_note" value="<?= sv('workshop_note', $s) ?>">
    <label class="f" for="s-splitfmt">Формат платежа сплита ({div} — число платежей, {per} — платёж)</label>
    <input class="input" id="s-splitfmt" name="card_split_format" value="<?= sv('card_split_format', $s) ?>">
    <label class="f" for="s-bf">Бейдж «Свежая поставка» (по тегу «свеж…»)</label>
    <input class="input" id="s-bf" name="badge_fresh_text" value="<?= sv('badge_fresh_text', $s) ?>">
    <label class="f" for="s-bs">Бейдж «Стойкие» (по тегу «стой…»)</label>
    <input class="input" id="s-bs" name="badge_sturdy_text" value="<?= sv('badge_sturdy_text', $s) ?>">

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Живой таймер доставки (шапка каталога)</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="feature_delivery_now" style="width:auto" <?= sv('feature_delivery_now', $s) !== '0' ? 'checked' : '' ?>>
      «Ближайшая доставка по СПб: сегодня к 15:30» (живой расчёт времени)
    </label>
    <div class="grid2" style="margin-top:8px">
      <div>
        <label class="f" for="s-dnm">Минут на сборку и доставку</label>
        <input class="input" id="s-dnm" name="delivery_now_minutes" type="number" min="30" max="360" step="15" value="<?= sv('delivery_now_minutes', $s) !== '' ? sv('delivery_now_minutes', $s) : '90' ?>">
      </div>
      <div>
        <label class="f" for="s-dns">Короткий текст (мобайл) — {time} = время</label>
        <input class="input" id="s-dns" name="delivery_now_short" value="<?= sv('delivery_now_short', $s) ?>">
      </div>
    </div>
    <label class="f" for="s-dnt">Полный текст «сегодня» — {time} = время</label>
    <input class="input" id="s-dnt" name="delivery_now_text" value="<?= sv('delivery_now_text', $s) ?>">
    <label class="f" for="s-dntm">Полный текст «завтра» — {time} = время</label>
    <input class="input" id="s-dntm" name="delivery_now_tomorrow" value="<?= sv('delivery_now_tomorrow', $s) ?>">

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Быстрый просмотр (клик по фото букета)</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="feature_quickview" style="width:auto" <?= sv('feature_quickview', $s) !== '0' ? 'checked' : '' ?>>
      Модалка быстрого просмотра: слайдер, состав, открытка, Кризал 0 ₽, сладости
    </label>
    <div class="grid2" style="margin-top:8px">
      <div>
        <label class="f" for="s-qvc">Заголовок состава</label>
        <input class="input" id="s-qvc" name="quickview_comp_title" value="<?= sv('quickview_comp_title', $s) ?>">
      </div>
      <div>
        <label class="f" for="s-qve">Заголовок дополнений</label>
        <input class="input" id="s-qve" name="quickview_extras_title" value="<?= sv('quickview_extras_title', $s) ?>">
      </div>
    </div>
    <div class="grid2" style="margin-top:8px">
      <div>
        <label class="f" for="s-qvh">Строка Кризала</label>
        <input class="input" id="s-qvh" name="quickview_chrysal_text" value="<?= sv('quickview_chrysal_text', $s) ?>">
      </div>
      <div>
        <label class="f" for="s-qvl">Строка открытки</label>
        <input class="input" id="s-qvl" name="quickview_card_label" value="<?= sv('quickview_card_label', $s) ?>">
      </div>
    </div>
    <div class="grid2" style="margin-top:8px">
      <div>
        <label class="f" for="s-qvp">Плейсхолдер текста открытки</label>
        <input class="input" id="s-qvp" name="quickview_card_placeholder" value="<?= sv('quickview_card_placeholder', $s) ?>">
      </div>
      <div>
        <label class="f" for="s-qvf">Ссылка «полное описание»</label>
        <input class="input" id="s-qvf" name="quickview_full_link" value="<?= sv('quickview_full_link', $s) ?>">
      </div>
    </div>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:6px 0 0">Сладости в быстром просмотре — живые товары с тегом «сладости» (раздел «Товары»).</p>

    <p style="font-size:.9rem;font-weight:700;margin:18px 0 6px">Чекаут: «Себе» / «Сюрприз другому» + мобильная панель</p>
    <label class="f" for="s-gs">Пилюля «Себе»</label>
    <input class="input" id="s-gs" name="gift_self_label" value="<?= sv('gift_self_label', $s) ?>">
    <label class="f" for="s-go">Пилюля «Сюрприз другому»</label>
    <input class="input" id="s-go" name="gift_other_label" value="<?= sv('gift_other_label', $s) ?>">
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="mobilebar_enabled" style="width:auto" <?= sv('mobilebar_enabled', $s) !== '0' ? 'checked' : '' ?>>
      Мобильная нижняя панель: Каталог · Поиск · Корзина с суммой · WhatsApp
    </label>
  </div>

  <div class="card" id="s-w103">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Имиджевые блоки (W103)</h2>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0 0 14px">Новые блоки главной страницы: бегущая лента, фотополоса-манифест, тёмный премиум-разворот, тихая ссылка в hero и крупная подпись футера. Пустые тексты = значения по умолчанию из кода.</p>

    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Бегущая лента под hero</p>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
      <input type="checkbox" name="feature_marquee" style="width:auto" <?= sv('feature_marquee', $s) !== '0' ? 'checked' : '' ?>>
      Тёмная лента-«бегущая строка» под первым экраном
    </label>
    <div class="grid2" style="margin-top:8px">
      <div>
        <?php for ($mq = 1; $mq <= 2; $mq++): ?>
        <label class="f" for="mq-<?= $mq ?>"<?= $mq === 2 ? ' style="margin-top:8px"' : '' ?>>Фраза <?= $mq ?> — бегущая строка</label>
        <input class="input" id="mq-<?= $mq ?>" name="marquee_<?= $mq ?>" value="<?= sv("marquee_{$mq}", $s) !== '' ? sv("marquee_{$mq}", $s) : ['Оплата при получении — наличными или картой', 'Срочная сборка — за 1–2 часа'][$mq - 1] ?>" maxlength="90">
        <?php endfor; ?>
      </div>
      <div>
        <?php for ($mq = 3; $mq <= 4; $mq++): ?>
        <label class="f" for="mq-<?= $mq ?>"<?= $mq === 3 ? '' : ' style="margin-top:8px"' ?>>Фраза <?= $mq ?> — бегущая строка</label>
        <input class="input" id="mq-<?= $mq ?>" name="marquee_<?= $mq ?>" value="<?= sv("marquee_{$mq}", $s) !== '' ? sv("marquee_{$mq}", $s) : ['Фото букета перед отправкой', 'Заменяем увядшие в день доставки'][$mq - 3] ?>" maxlength="90">
        <?php endfor; ?>
      </div>
    </div>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Пустая строка пропускается; совсем пусто — лента скроется.</p>

    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Фотополоса-манифест (между каруселями и премиумом)</p>
    <div class="grid2">
      <div>
        <label class="f" for="mf-k">Надпись-«кикер» над цитатой</label>
        <input class="input" id="mf-k" name="manifesto_kicker" value="<?= sv('manifesto_kicker', $s) !== '' ? sv('manifesto_kicker', $s) : 'Наши принципы' ?>" maxlength="40">
      </div>
      <div>
        <label class="f" for="mf-t">Текст манифеста (Playfair-курсив)</label>
        <input class="input" id="mf-t" name="manifesto_text" value="<?= sv('manifesto_text', $s) !== '' ? sv('manifesto_text', $s) : 'Собираем букеты утром — и везём вам сегодня' ?>" maxlength="120">
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Премиум-разворот (тёмная секция)</p>
    <div class="grid2">
      <div>
        <label class="f" for="pm-w103-t">Заголовок (Playfair)</label>
        <input class="input" id="pm-w103-t" name="premium_title" value="<?= sv('premium_title', $s) !== '' ? sv('premium_title', $s) : 'Для особых случаев' ?>" maxlength="60">
        <label class="f" for="pm-w103-s" style="margin-top:8px">Подпись</label>
        <textarea class="input" id="pm-w103-s" name="premium_sub" rows="2" maxlength="200"><?= sv('premium_sub', $s) !== '' ? sv('premium_sub', $s) : 'Крупные композиции из гортензий, пионов и орхидей — когда впечатление важнее бюджета' ?></textarea>
      </div>
      <div>
        <div style="display:flex;gap:12px">
          <div style="flex:1">
            <label class="f" for="pm-w103-pl">Подпись у крупной цены</label>
            <input class="input" id="pm-w103-pl" name="premium_price_label" value="<?= sv('premium_price_label', $s) !== '' ? sv('premium_price_label', $s) : 'Букеты от' ?>" maxlength="30">
          </div>
          <div style="flex:1.4">
            <label class="f" for="pm-w103-cta">Кнопка разворота</label>
            <input class="input" id="pm-w103-cta" name="premium_cta_text" value="<?= sv('premium_cta_text', $s) !== '' ? sv('premium_cta_text', $s) : 'Смотреть премиум' ?>" maxlength="40">
          </div>
        </div>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:8px 0 0">Сама «цена-от» считается автоматически — минимум среди премиум-товаров. Тумблер секции — в карточке «Витрина 5cv» выше.</p>
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Hero, секции и футер</p>
    <div class="grid2">
      <div>
        <div style="display:flex;gap:12px">
          <div style="flex:1.2">
            <label class="f" for="hg-t">Тихая ссылка в hero (текст)</label>
            <input class="input" id="hg-t" name="hero_ghost_text" value="<?= sv('hero_ghost_text', $s) !== '' ? sv('hero_ghost_text', $s) : 'Цветы по поводам' ?>" maxlength="40">
          </div>
          <div style="flex:1">
            <label class="f" for="hg-l">Ссылка</label>
            <input class="input" id="hg-l" name="hero_ghost_link" value="<?= sv('hero_ghost_link', $s) !== '' ? sv('hero_ghost_link', $s) : '#occasions' ?>" maxlength="200" placeholder="#occasions или /occasion/…">
          </div>
        </div>
        <label class="f" for="eb-hits" style="margin-top:8px">Eyebrow секции «Хиты»</label>
        <input class="input" id="eb-hits" name="section_hits_eyebrow" value="<?= sv('section_hits_eyebrow', $s) !== '' ? sv('section_hits_eyebrow', $s) : 'Выбор|покупателей' ?>" maxlength="60">
        <label class="f" for="eb-budget" style="margin-top:8px">Eyebrow секции «До N ₽»</label>
        <input class="input" id="eb-budget" name="section_budget_eyebrow" value="<?= sv('section_budget_eyebrow', $s) !== '' ? sv('section_budget_eyebrow', $s) : 'Красиво —|не значит|дорого' ?>" maxlength="60">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Формат eyebrow: «капс|курсив» — до <code>|</code> заглавными буквами, после — курсивным серифом. Пустое поле скрывает надпись.</p>
        <?php /* W104-α (M): ручные нумералы секций убраны — оглавление главной
               нумеруется автоматически (01 хиты → … → 09 FAQ) в порядке секций;
               ручное редактирование ломало сквозную последовательность */ ?>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:14px 0 0">Нумералы секций (крупные индексы «01»…«09» за заголовками) нумеруются автоматически по порядку секций — настройки не требуют.</p>
      </div>
      <div>
        <label class="f" for="ft-tagline">Крупная подпись футера (Playfair-курсив)</label>
        <input class="input" id="ft-tagline" name="footer_tagline" value="<?= sv('footer_tagline', $s) !== '' ? sv('footer_tagline', $s) : 'Свежие цветы — с утра к вашей двери' ?>" maxlength="90">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Показывается на главной над колонками футера; очистите — блок исчезнет.</p>
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
          <div style="display:flex;align-items:center;gap:10px;margin-top:8px;flex-wrap:wrap">
            <img class="thumb" style="width:32px;height:32px" src="/img/uploads/<?= e($s['site_favicon']) ?>?v=<?= $imgVer('site_favicon') ?>" alt="Текущий favicon">
            <label class="f" style="display:flex;gap:6px;align-items:center;font-weight:400;font-size:.85rem;margin:0">
              <input type="checkbox" name="site_favicon_remove" value="1" style="width:auto">
              Удалить favicon
            </label>
            <?php if ($rotatable('site_favicon')) { rotateControlsButtons('rot-site_favicon'); } ?>
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
          Бейдж «Доставка» на карточках букетов
        </label>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 10px 26px">Зелёная плачка про доставку по Санкт-Петербургу на каждой карточке каталога.</p>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="feature_countdown" style="width:auto" <?= sv('feature_countdown', $s) === '1' ? 'checked' : '' ?>>
          Таймер дедлайна заказа («до 20:00 — доставим сегодня»)
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
        <p style="font-size:.78rem;color:var(--ink-soft);margin:2px 0 6px 26px">Поле «Например: Центральный» — покупатель сразу видит стоимость для своего района.</p>
        <div style="margin-left:26px">
          <label class="f" for="zc-ph">Серый текст-пример внутри поля «Район»</label>
          <input class="input" id="zc-ph" name="zone_check_placeholder" value="<?= sv('zone_check_placeholder', $s) !== '' ? sv('zone_check_placeholder', $s) : 'Например: Центральный' ?>" maxlength="60">
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
          <input class="input" id="bd-sale" name="badge_sale_text" value="<?= sv('badge_sale_text', $s) !== '' ? sv('badge_sale_text', $s) : 'Скидка' ?>" maxlength="40">
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
        <?php /* W104: подписи editorial-спек-чипов страницы товара (значение
               берётся из описания товара, подпись — эти 4 слова) */ ?>
        <label class="f" for="pdp-caps" style="margin-top:8px">Подписи чипов на странице букета («|» — разделитель)</label>
        <input class="input" id="pdp-caps" name="pdp_spec_captions" value="<?= sv('pdp_spec_captions', $s) !== '' ? sv('pdp_spec_captions', $s) : 'Размер|Свежесть|Повод|Состав' ?>" maxlength="60">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Четыре подписи под значениями на карточке букета: размер, свежесть, повод, состав.</p>
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
        <input class="input" id="badge-text" name="delivery_badge_text" value="<?= sv('delivery_badge_text', $s) !== '' ? sv('delivery_badge_text', $s) : 'Доставка по Санкт-Петербургу' ?>" maxlength="60">
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
        <input class="input" id="faq-a<?= $i ?>" name="faq_a<?= $i ?>" value="<?= sv("faq_a{$i}", $s) ?>" maxlength="300" placeholder="<?= $i === 1 ? 'Стоимость зависит от района — посчитаем при подтверждении…' : '' ?>">
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
    <input class="input" id="cd-t" name="countdown_text" value="<?= sv('countdown_text', $s) !== '' ? sv('countdown_text', $s) : 'Заказ до {D} — доставим сегодня' ?>" maxlength="120">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 12px">Вместо <code>{T}</code> подставится время («2 ч 15 мин»), вместо <code>{D}</code> — ваш дедлайн. Ночью (до открытия) и после дедлайна показывается соответствующий текст ниже.</p>
    <label class="f" for="cd-c">Текст после дедлайна</label>
    <input class="input" id="cd-c" name="countdown_closed_text" value="<?= sv('countdown_closed_text', $s) !== '' ? sv('countdown_closed_text', $s) : 'Приём заказов на сегодня закрыт — доставим завтра с 9:00' ?>" maxlength="120">
    <label class="f" for="cd-n" style="margin-top:10px">Текст ночью (с дедлайна до открытия)</label>
    <input class="input" id="cd-n" name="countdown_night_text" value="<?= sv('countdown_night_text', $s) !== '' ? sv('countdown_night_text', $s) : 'Примем заказ сейчас — доставим с 9:00 утра' ?>" maxlength="120">
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
        <input class="input" id="ce-t" name="catalog_empty_title" value="<?= sv('catalog_empty_title', $s) !== '' ? sv('catalog_empty_title', $s) : 'Под эти фильтры ничего не подошло' ?>" maxlength="100">
      </div>
      <div>
        <label class="f" for="ce-h">Подсказка</label>
        <input class="input" id="ce-h" name="catalog_empty_hint" value="<?= sv('catalog_empty_hint', $s) !== '' ? sv('catalog_empty_hint', $s) : 'Сбросьте цену или загляните в соседнюю категорию' ?>" maxlength="160">
      </div>
    </div>
    <?php /* Cookie-баннер (критерий 16): юр-тексты редактируются */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Окно про cookie (появляется при первом визите)</p>
    <label class="f" for="cb-text">Текст</label>
    <input class="input" id="cb-text" name="cookie_banner_text" value="<?= sv('cookie_banner_text', $s) !== '' ? sv('cookie_banner_text', $s) : e('Сайт использует cookie и Яндекс.Метрику для работы и анализа трафика. Подробнее — в <a href="/policy" target="_blank" rel="noopener">Политике обработки персональных данных</a>.') /* W82 (владелец OPEN_OLD): e() для фолбэка — иначе сырые " обрезали value и «Сохранить» без правок персил битый HTML */ ?>" maxlength="260">
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
        <input class="input" id="ct-note" name="cart_total_note" value="<?= sv('cart_total_note', $s) ?>" maxlength="140" placeholder="Пусто = автотекст из зон доставки">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Поясняет, что «Итого» в корзине — только букеты: доставка добавится в форме. Оставьте пустым — сайт сам подставит вилку тарифов по районам («от … до … ₽»); впишите свой текст, чтобы заменить его.</p>
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
        <?php /* W104-theta (P1 критика C3-G3): дубль shop_phone «Телефон для сайта» удалён —
           при submit эта копия перебивала поле «Телефон» из «Общих» (оно шло раньше по DOM).
           Единственное поле телефона — секция «Общие». */ ?>
        <p style="font-size:.75rem;color:var(--ink-soft);margin:0 0 10px">Телефон магазина настраивается в разделе «Общие» вверху страницы. Ниже — только короткий номер для шапки.</p>
        <label class="f" for="c-email">Email магазина</label>
        <input class="input" id="c-email" name="shop_email" value="<?= sv('shop_email', $s) ?>">
        <p style="font-size:.75rem;color:var(--ink-soft);margin:2px 0 8px">Единственное поле email (дубль в списке мессенджеров удалён): письма клиентам отправляются от него, на сайте показывается в футере при включённом флажке «Показывать email на сайте».</p>
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
        <?php /* W104-theta (P1 критика C3-G3): дубль shop_email удалён — эта копия шла
           позже по DOM и перебивала «Email магазина» слева. Единственное поле — слева,
           здесь остаётся только флажок видимости email на витрине. */ ?>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="email_enabled" style="width:auto" <?= ($s['email_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
          Показывать email на сайте (поле «Email магазина» — слева выше)
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
        <input class="input" id="u-title" name="upsell_title" value="<?= sv('upsell_title', $s) !== '' ? sv('upsell_title', $s) : 'Добавьте к букету' ?>">
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
        <?php /* W98-fixE (E8/E18): дата последнего обновления политики ПД —
           статичная строка в футе /policy (была <?= date() ?> — менялась
           при каждом заходе). Ключ в БД не сидируется: пусто = дефолт из кода. */ ?>
        <label class="f" for="l-polupd" style="margin-top:8px">Дата обновления политики ПД</label>
        <input class="input" id="l-polupd" name="policy_updated" value="<?= sv('policy_updated', $s) !== '' ? sv('policy_updated', $s) : '01.09.2026' ?>" maxlength="10" placeholder="дд.мм.гггг">
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Отображается внизу страницы «Политика обработки персональных данных».</p>
      </div>
    </div>
    <?php /* SEO главной + Метрика (критерий 16, P1 аудита) */ ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <p style="font-size:.85rem;font-weight:600;margin:0 0 8px">Продвижение: заголовок и описание для поисковиков</p>
    <label class="f" for="seo-t">Title (виден во вкладке и в Яндексе)</label>
    <input class="input" id="seo-t" name="seo_title" value="<?= sv('seo_title', $s) !== '' ? sv('seo_title', $s) : sv('shop_name', $s) . ' — доставка цветов по Санкт-Петербургу' ?>" maxlength="80">
    <label class="f" for="seo-d" style="margin-top:8px">Description (описание в результатах поиска)</label>
    <input class="input" id="seo-d" name="seo_description" value="<?= sv('seo_description', $s) !== '' ? sv('seo_description', $s) : 'Доставка букетов по Санкт-Петербургу в день заказа. Свежий срез каждое утро, фото перед отправкой. Заказы до 20:00 — доставим сегодня.' ?>" maxlength="200">
    <label class="f" for="mk-id" style="margin-top:8px">Счётчик Яндекс.Метрики (номер)</label>
    <input class="input" id="mk-id" name="metrika_counter_id" value="<?= sv('metrika_counter_id', $s) ?>" placeholder="12345678" inputmode="numeric" maxlength="12">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Метрика грузится только после согласия на cookie. Номер — из личного кабинета Метрики. Пусто = счётчик не ставится.</p>
    <?php /* W100-fixH1 (I14): Вебвизор — отдельный тумблер рядом со счётчиком
           (аналитика); по умолчанию ВЫКЛ — запись сессий чувствительна к приватности */ ?>
    <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:10px">
      <input type="checkbox" name="metrika_webvisor" style="width:auto" <?= sv('metrika_webvisor', $s) === '1' ? 'checked' : '' ?>>
      Вебвизор (запись сессий)
    </label>
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Запись действий посетителей на сайте (движения мыши, прокрутка, клики). Выключен по умолчанию ради приватности — включайте осознанно; работает только при заполненном номере счётчика и согласии на cookie.</p>
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
    <?php if ($isOwner): ?>
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
        <?php /* W100-fixH2 (J2): значение НЕ отдаётся в DOM — пусто = секрет не изменится */ ?>
        <input class="input" id="yk-key" name="yk_secret_key" type="password" autocomplete="new-password" value="" placeholder="оставьте пустым — секрет не изменится"<?= trim((string)($s['yk_secret_key'] ?? '')) !== '' ? ' data-secret-set="1"' : '' ?>>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0"><?= trim((string)($s['yk_secret_key'] ?? '')) !== '' ? 'Секретный ключ задан — поле пустое, чтобы не показывать его в HTML. Введите новый, чтобы заменить.' : 'Из личного кабинета ЮKassa (Интеграция → Ключи API). Введите ключ — он сохранится, но больше не показывается в форме.' ?></p>
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
    <?php else: /* W98-fixD (D5): платёжные ключи — только владельцу */ ?>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:0;padding:10px 12px;background:var(--bg-alt,#F1EAD9);border:1px dashed var(--ink-soft);border-radius:10px">🔒 Раздел «Оплата» (ключи ЮKassa, ставка НДС) — <strong>доступно владельцу</strong>. Сотруднику открыт остальной интерфейс настроек.</p>
    <?php endif; ?>
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

<?php /* W105 (3-b): формы-носители кнопок поворота картинок настроек — вне
       основной формы (вложенность <form> запрещена; кнопки в секциях выше
       привязаны через form=). */ ?>
<?php foreach ($rotKeys as $rk): if ($rotatable($rk)) {
    rotateControlsForm('rot-' . $rk, ['action' => 'rotate_img', 'key' => $rk]);
} endforeach; ?>

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
  <?php foreach ($hist as $hh):
      /* W100-fixH2 (J8): имена изменённых ключей — «12:31 — feature_faq, hero_title (сохранение)» */
      $hhKeys = (array)($hh['changed'] ?? []);
      $hhKeysStr = $hhKeys === [] ? '' : e(implode(', ', array_slice($hhKeys, 0, 8))) . (count($hhKeys) > 8 ? ' …' : ''); ?>
  <li><?= e($hh['ts']) ?> — <?= $hhKeysStr !== '' ? $hhKeysStr . ' (' : '' ?><?= e(['save' => 'сохранение', 'undo' => 'отмена', 'defaults' => 'сброс к „по умолчанию“', 'defaults-save' => 'эталон обновлён текущим', 'defaults-reset' => 'сброс к „по умолчанию“'][$hh['source']] ?? $hh['source']) ?><?= $hhKeysStr !== '' ? ')' : '' ?></li>
  <?php endforeach; ?>
</ol></details>
<?php endif; ?>

<?php
/* --- Web Push (VAPID): отдельная мини-форма, не трогает основную --- */
require_once __DIR__ . '/../includes/vapid.php';
$msgPush = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['vapid_action'])) {
    /* CSRF уже проверён глобальным гейтом в начале файла (~строка 12) */
    if (!$isOwner) { /* W98-fixD (D5): push — канал уведомлений, только владельцу */
        $msgPush = 'Push-уведомления настраивает владелец магазина.';
    } elseif (($_POST['vapid_action'] ?? '') === 'gen') {
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
  <?php if (!$isOwner): /* W98-fixD (D5): канал уведомлений — только владельцу */ ?>
  <p style="font-size:.85rem;color:var(--ink-soft);margin:0;padding:10px 12px;background:var(--bg-alt,#F1EAD9);border:1px dashed var(--ink-soft);border-radius:10px">🔒 Push-уведомления — <strong>доступно владельцу</strong>.</p>
  <?php else: ?>
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
  <?php endif; /* W98-fixD (D5): конец owner-only блока push */ ?>
</div>
<script>
/* W98-fixD (D8): поиск по настройкам — vanilla JS. Фильтрует строки полей по подписи label
   и placeholder; запрос, совпавший с заголовком раздела, раскрывает раздел целиком.
   Прячем через display:none — поля остаются в DOM и попадают в POST (сохранение после
   поиска ничего не затирает). Чипы разделов и scrollspy не трогаем. */
(function () {
  var input = document.getElementById('settings-search');
  var counter = document.getElementById('settings-search-count');
  if (!input || !counter) return;
  var cards = Array.prototype.slice.call(document.querySelectorAll('.card[id^="s-"]'));
  var rows = [];
  cards.forEach(function (card) {
    Array.prototype.forEach.call(card.querySelectorAll('label'), function (lb) {
      var ctrl = null;
      var forId = lb.getAttribute('for');
      if (forId) { ctrl = document.getElementById(forId); }
      if (!ctrl) { ctrl = lb.querySelector('input,select,textarea'); }
      if (!ctrl) { ctrl = lb.nextElementSibling; }
      if (!ctrl || !/^(INPUT|SELECT|TEXTAREA)$/.test(ctrl.tagName)) return;
      var els = [lb];
      if (ctrl !== lb && ctrl.parentNode !== lb) { els.push(ctrl); }
      /* серая подсказка <p> сразу после поля — часть той же строки, прячем вместе */
      var hint = ctrl.nextElementSibling;
      if (hint && hint.tagName === 'P' && !hint.querySelector('input,select,textarea')) { els.push(hint); }
      var hay = ((lb.textContent || '') + ' ' + (ctrl.getAttribute('placeholder') || '') + ' ' + (ctrl.getAttribute('title') || ''))
        .toLowerCase().replace(/\s+/g, ' ').trim();
      rows.push({ hay: hay, card: card, els: els });
    });
  });
  function apply() {
    var q = input.value.trim().toLowerCase();
    var cardMatch = {};
    cards.forEach(function (card) {
      var h = card.querySelector('h2');
      cardMatch[card.id] = q !== '' && !!h && h.textContent.toLowerCase().indexOf(q) !== -1;
    });
    var shown = 0;
    rows.forEach(function (r) {
      var on = q === '' || cardMatch[r.card.id] || r.hay.indexOf(q) !== -1;
      if (on) { shown++; }
      r.els.forEach(function (el) { el.style.display = on ? '' : 'none'; });
    });
    cards.forEach(function (card) {
      var any = cardMatch[card.id] || rows.some(function (r) { return r.card === card && r.els[0].style.display !== 'none'; });
      card.style.display = any ? '' : 'none';
    });
    counter.textContent = q === '' ? '' : (shown > 0 ? 'найдено ' + shown : 'ничего не найдено');
  }
  input.addEventListener('input', apply);
})();
</script>
<?php adminFooter(); ?>
