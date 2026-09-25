<?php
/** Уведомление владельцу о новом заказе: mail() с fallback в STATE_OUT_DIR/mail-out/*.eml (вне docroot),
 *  тихие часы (quiet_from/quiet_to в зоне shop_timezone) — письмо не отправляется. */
declare(strict_types=1);

/** Отправка сообщения в Telegram (Bot API sendMessage). Таймаут 5 с — не блокировать заказ.
 *  $dirLog: путь для file-based fallback, если curl/network недоступен или Telegram вернул ошибку. */
function tg_send(string $token, string $chatId, string $html, ?string $logDir = null): bool
{
    if ($token === '' || $chatId === '') {
        return false;
    }
    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $ok = $res !== false && ((json_decode((string)$res, true)['ok'] ?? false) === true);
    if (!$ok && $logDir !== null) {
        // file-based fallback: curl не выполнился (false) либо Telegram вернул ok=false
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $detail = $res !== false ? (string)$res : ('curl_error: ' . $err);
        @file_put_contents(
            $logDir . '/tg-fallback.log',
            date('Y-m-d H:i:s') . " tg_send failed\n" . $detail . "\n---\n" . $html . "\n\n",
            FILE_APPEND
        );
    }
    return $ok;
}

function isQuietHours(?string $from, ?string $to, string $tz): bool
{
    $from = trim((string)$from);
    $to = trim((string)$to);
    if ($from === '' || $to === '' || !preg_match('/^\d{1,2}:\d{2}$/', $from) || !preg_match('/^\d{1,2}:\d{2}$/', $to)) {
        return false;
    }
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone($tz !== '' ? $tz : 'Europe/Moscow'));
    } catch (Throwable) {
        return false;
    }
    [$fh, $fm] = array_map('intval', explode(':', $from));
    [$th, $tm] = array_map('intval', explode(':', $to));
    $minutes = (int)$now->format('H') * 60 + (int)$now->format('i');
    $fromM = $fh * 60 + $fm;
    $toM = $th * 60 + $tm;
    // Окно через полночь (например 22:00–08:00)
    return $fromM <= $toM ? ($minutes >= $fromM && $minutes < $toM) : ($minutes >= $fromM || $minutes < $toM);
}

function notifyNewOrder(int $orderId): void
{
    if (setting('notify_enabled', '1') !== '1') {
        return;
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :i');
    $stmt->execute([':i' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return;
    }
    $items = $pdo->prepare('SELECT name, price, qty FROM order_items WHERE order_id = :i');
    $items->execute([':i' => $orderId]);

    $lines = '';
    /* W98-fixF (F3): состав для письма-подтверждения КЛИЕНТУ — только позиции
       и доставка (без менеджерских деталей: получатель/открытка/слоты). */
    $clientLines = '';
    $sum = 0;
    foreach ($items->fetchAll() as $it) {
        $lines .= '- ' . $it['name'] . ' × ' . (int)$it['qty'] . ' — ' . formatPrice((int)$it['price']) . "\n";
        $clientLines .= '- ' . $it['name'] . ' × ' . (int)$it['qty'] . ' — ' . formatPrice((int)$it['price']) . "\n";
        $sum += (int)$it['price'] * (int)$it['qty'];
    }
    if ((int)$order['delivery_zone_id'] > 0) {
        $deliveryLine = '- Доставка — ' . formatPrice(max(0, (int)$order['total'] - $sum)) . "\n";
        $lines .= $deliveryLine;
        $clientLines .= $deliveryLine;
    }
    /* Критик functional: подарок и желаемое время — сразу в уведомлении менеджеру */
    $gift = [];
    if (($order['recipient_name'] ?? '') !== '') { $gift[] = 'получатель: ' . $order['recipient_name']; }
    if (($order['recipient_phone'] ?? '') !== '') { $gift[] = 'тел. получателя: ' . $order['recipient_phone']; }
    if (($order['card_text'] ?? '') !== '') { $gift[] = 'открытка: «' . $order['card_text'] . '»'; }
    if ($gift !== []) { $lines .= '- 🎁 ' . implode(' · ', $gift) . "\n"; }
    if (($order['delivery_date'] ?? '') !== '' || ($order['delivery_slot'] ?? '') !== '') {
        $lines .= '- Хочет: ' . trim(($order['delivery_date'] ?? '') . ' ' . ($order['delivery_slot'] ?? '')) . "\n";
    }

    // Тихие часы — единый шлюз для email и Telegram
    $quiet = isQuietHours(setting('quiet_from'), setting('quiet_to'), setting('shop_timezone', 'Europe/Moscow'));

    // Telegram: шлём, если заданы токен и chat_id (тихие часы уважаем, при сбое — file-based fallback)
    $tgToken = trim(setting('telegram_bot_token', ''));
    $tgChat = trim(setting('tg_chat_id', ''));
    if ($tgToken !== '' && $tgChat !== '') {
        if ($quiet) {
            $dir = STATE_OUT_DIR . '/mail-out';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($dir . '/skipped-quiet.log', date('Y-m-d H:i:s') . " заказ № {$orderId} — tg пропущен (тихие часы)\n", FILE_APPEND);
        } else {
            $tgMsg = "🌸 <b>Новый заказ № {$orderId}</b>\n"
                . "Имя: " . htmlspecialchars((string)$order['customer_name'], ENT_QUOTES, 'UTF-8') . "\n"
                . "Телефон: " . htmlspecialchars((string)$order['phone'], ENT_QUOTES, 'UTF-8') . "\n"
                . "Состав:\n" . htmlspecialchars($lines, ENT_QUOTES, 'UTF-8')
                . "Сумма: " . formatPrice((int)$order['total']);
            tg_send($tgToken, $tgChat, $tgMsg, STATE_OUT_DIR . '/tg-out');
        }
    }

    // Web Push (фича по умолчанию выкл; ключи/подписки — из админки). Не блокируем заказ: 4с таймаут.
    if (setting('feature_webpush', '0') === '1') {
        require_once __DIR__ . '/vapid.php';
        @webpushSendAll(
            'Новый заказ № ' . $orderId,
            $order['customer_name'] . ' · ' . formatPrice((int)$order['total']) . ' · ' . date('H:i'),
            '/admin/'
        );
    }

    /* ============ W98-fixF (F3): письмо-подтверждение КЛИЕНТУ (CRO-воронка) ============
     * Критик: покупатель уходил без фидбэка — «принят ли мой заказ?». Письмо
     * транзакционное (клиент сам только что оформил заказ) — тихие часы НЕ
     * уважаем (они для owner-канала); транспорт тот же, что у owner-письма:
     * mail() с фолбэком в STATE_OUT_DIR/mail-out/*.eml. Домен трекинга —
     * setting('site_url') с дефолтом (в админку ключ добавит следующая волна). */
    $clientEmail = trim((string)($order['email'] ?? ''));
    if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL) !== false) {
        $from = trim(setting('shop_email', ''));
        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            /* W100-fixH1 (I13): домен .local не резолвится у почтовых провайдеров —
               фолбэк на домен сайта; shop_email из настроек остаётся приоритетом */
            $from = 'noreply@flowers.interfood-catering.ru';
        }
        $payLine = ($order['payment_method'] ?? 'cash') === 'online'
            ? 'К оплате онлайн'
            : 'К оплате при получении';
        $trackUrl = rtrim(setting('site_url', 'https://flowers.interfood-catering.ru'), '/') . '/track';
        $clientSubject = 'Заказ № ' . $orderId . ' принят — ' . setting('shop_name', 'Nilov Flowers');
        $clientBody = 'Здравствуйте, ' . $order['customer_name'] . "!\n\n"
            . "Ваш заказ № " . $orderId . " принят.\n\n"
            . "Состав заказа:\n" . $clientLines . "\n"
            . $payLine . ': ' . formatPrice((int)$order['total']) . "\n\n"
            . setting('thanks_call_text', 'Мы позвоним в течение 15 минут для подтверждения') . "\n\n"
            . 'Отследить заказ: ' . $trackUrl . "\n\n"
            . '— ' . setting('shop_name', 'Nilov Flowers') . "\n";
        $clientHeaders = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
        $clientSent = @mail(
            $clientEmail,
            '=?UTF-8?B?' . base64_encode($clientSubject) . '?=',
            $clientBody,
            $clientHeaders
        );
        if (!$clientSent) {
            $dir = STATE_OUT_DIR . '/mail-out';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $eml = "Date: " . date('r') . "\nTo: {$clientEmail}\nSubject: =?UTF-8?B?" . base64_encode($clientSubject) . "?=\n"
                . "MIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\n\n" . $clientBody;
            @file_put_contents($dir . '/order-' . $orderId . '-client.eml', $eml);
        }
    }

    // Адрес получателя: notify_email → email владельца (role='owner', notify_enabled=1)
    $to = trim(setting('notify_email', ''));
    if ($to === '') {
        $owner = $pdo->query("SELECT notify_email, email, login FROM admin_users WHERE role = 'owner' AND notify_enabled = 1 LIMIT 1")->fetch();
        if ($owner) {
            $to = trim((string)($owner['notify_email'] ?: $owner['email'] ?: $owner['login']));
        }
    }
    if ($to === '' || !str_contains($to, '@')) {
        return; // некуда слать
    }

    $subject = 'Новый заказ № ' . $orderId . ' — Nilov Flowers';
    $body = "Новый заказ № {$orderId} от " . $order['created_at'] . "\n\n"
        . "Состав:\n" . $lines
        . "\nСумма: " . formatPrice((int)$order['total']) . "\n"
        . "Получатель: " . $order['customer_name'] . "\n"
        . "Телефон: " . $order['phone'] . "\n"
        . ($order['email'] !== '' ? "Email: " . $order['email'] . "\n" : '')
        . "Оплата: " . ($order['payment_method'] === 'online' ? 'онлайн' : 'при получении') . "\n";

    if (isQuietHours(setting('quiet_from'), setting('quiet_to'), setting('shop_timezone', 'Europe/Moscow'))) {
        $dir = STATE_OUT_DIR . '/mail-out';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/skipped-quiet.log', date('Y-m-d H:i:s') . " заказ № {$orderId} — тихие часы\n", FILE_APPEND);
        return;
    }

    $headers = 'From: noreply@flowers.interfood-catering.ru' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
    $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    if (!$sent) {
        $dir = STATE_OUT_DIR . '/mail-out';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $eml = "Date: " . date('r') . "\nTo: {$to}\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\n"
            . "MIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\n\n" . $body;
        @file_put_contents($dir . '/order-' . $orderId . '.eml', $eml);
    }
}
