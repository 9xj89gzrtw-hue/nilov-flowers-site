<?php
/* История настроек: снимок всех settings перед каждым изменением из админки
   + отмена последнего изменения (критерий 16) + возврат к заводским значениям
   (критерий 17). Идемпотентно, только stdlib. */
declare(strict_types=1);

/** Сохранить текущее состояние settings как точку отката ПЕРЕД записью newValues.
 *  source: save | undo | defaults. Вызывается из admin/settings.php. */
function settingsSnapshot(string $source): void
{
    $snap = allSettings();
    unset($snap['vapid_private']); // приватный ключ не храним в истории
    db()->prepare('INSERT INTO settings_history (ts, source, snapshot) VALUES (:t, :s, :j)')
        ->execute([':t' => date('Y-m-d H:i:s'), ':s' => $source, ':j' => json_encode($snap, JSON_UNESCAPED_UNICODE)]);
    /* хвост режем: 60 снимков ≈ все отмены последних ~часов */
    $st = db()->query('SELECT id FROM settings_history ORDER BY id DESC LIMIT 100 OFFSET 60')->fetchAll(PDO::FETCH_COLUMN);
    if ($st) {
        db()->exec('DELETE FROM settings_history WHERE id IN (' . implode(',', array_map('intval', $st)) . ')');
    }
}

/** Есть ли что отменять. */
function settingsCanUndo(): bool
{
    return (bool)db()->query('SELECT 1 FROM settings_history WHERE source = \'save\' LIMIT 1')->fetchColumn();
}

/** Откатить последнее изменение настроек → вернуть предыдущий снимок. true = успех. */
function settingsUndoLast(): bool
{
    $row = db()->query("SELECT snapshot FROM settings_history WHERE source = 'save' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$row) { return false; }
    $snap = json_decode((string)$row['snapshot'], true);
    if (!is_array($snap) || $snap === []) { return false; }
    /* сама отмена — тоже изменение: снимаем текущее состояние, чтобы отменить отмену */
    settingsSnapshot('undo');
    saveSettings($snap);
    return true;
}

/** Список последних изменений (для UI): дата + тип. */
function settingsHistoryList(int $limit = 8): array
{
    $stmt = db()->prepare('SELECT id, ts, source FROM settings_history ORDER BY id DESC LIMIT :l');
    $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Заводские значения витринных текстов/тумблеров (из seedDatabase/migrations).
 *  ВНИМАНИЕ: только человекочитаемые настройки. Технические/секретные ключи
 *  (токены, VAPID, верификации, id counters, реквизиты) сюда НЕ входят —
 *  сброс к дефолтам не должен убить уведомления, платежи или легальную информацию. */
function settingsDefaults(): array
{
    return [
        'shop_name' => 'Nilov Flowers',
        'hero_title' => 'Свежие цветы с утренней поставки',
        'hero_subtitle' => 'Соберём и доставим букет в течение дня — к празднику или просто так',
        'hero_button_text' => 'Выбрать букет',
        'hero_eyebrow' => 'Санкт-Петербург · доставка в день заказа',
        'hero_text_enabled' => '1',
        'steps_title' => 'Как это работает',
        'step_1' => 'Выбираете букет в каталоге и добавляете в корзину',
        'step_2' => 'Оформляете заказ — мы связываемся с вами для подтверждения',
        'step_3' => 'Собираем букет и доставляем в выбранный район',
        'guarantees_title' => 'Почему у нас заказывают',
        'guarantee_1' => 'Фото букета перед отправкой',
        'guarantee_2' => 'Свежие цветы с утренней поставки',
        'guarantee_3' => 'Заменяем увядшие в день доставки',
        'catalog_title' => 'Каталог',
        'catalog_subtitle' => 'Соберём и доставим букет в день заказа',
        'order_title' => 'Оформление заказа',
        'marquee_1' => 'Доставка по Санкт-Петербургу в день заказа',
        'marquee_2' => 'Свежие цветы с утренней поставки',
        'marquee_3' => 'Фото букета перед отправкой',
        'marquee_4' => 'Заменяем увядшие в день доставки',
        'badge_sale_text' => 'Скидка до конца недели',
        'badge_urgent_text' => 'Успеть сегодня',
        'delivery_badge_text' => 'Доставка 0₽ · Приморский',
        'faq_title' => 'Частые вопросы',
        'faq_q1' => 'Сколько стоит доставка?',
        'faq_q2' => 'Успею ли заказать сегодня?',
        'faq_q3' => 'Как понять, что пришёл именно мой букет?',
        'faq_q4' => 'Как оплатить?',
        'pickup_option_text' => 'Самовывоз — бесплатно',
        'delivery_hint_text' => 'Доставим в течение дня, время согласуем по телефону',
        'cart_title' => 'Корзина',
        'cart_checkout_text' => 'Оформить заказ',
        'cart_continue_text' => 'Продолжить покупки',
        'upsell_title' => 'Возможно, пригодится',
        'cart_mode' => 'drawer',
        'feature_delivery_badge' => '1',
        'feature_faq' => '1',
        'feature_countdown' => '1',
        'feature_price_filter' => '1',
        'feature_favorites' => '1',
        'feature_zone_check' => '1',
        'feature_track_link' => '1',
        'feature_favicon_badge' => '1',
        'wa_enabled' => '1',
        'tg_enabled' => '1',
        'vk_enabled' => '1',
    ];
}

/** Сброс витринных текстов/фич к заводским. Секретные и технические ключи НЕ трогаются.
 *  Перед записью — снимок, чтобы кнопка «Отменить» работала и после сброса. */
function settingsResetToDefaults(): bool
{
    settingsSnapshot('defaults');
    saveSettings(settingsDefaults());
    return true;
}
