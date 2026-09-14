<?php
/**
 * POST /api/orders — создание заказа из корзины.
 * Вход (JSON): {name, phone, email, delivery_zone_id|delivery_zone, delivery_address,
 *               payment_method: online|cash, comment, pd_consent, items:[{product_id, qty}]}
 * Выход: {id, paymentToken} либо ошибки {errors: [...], item: {...}}
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/notify.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['errors' => ['method_not_allowed']]);
}

/* Критик security (4/10): rate-limit на создание заказов — не больше 6/час на IP. */
if (!rl_check('orders', 6, 3600)) {
    header('Retry-After: ' . rl_retry_after('orders', 3600));
    respond(429, ['errors' => ['rate_limited']]);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    respond(400, ['errors' => ['bad_json']]);
}

// Совместимость: старый формат продукта data-order-cta одиночной позицией
if (empty($data['items']) && isset($data['product_id'])) {
    $data['items'] = [['product_id' => $data['product_id'], 'qty' => 1]];
}

$errors = [];
$name = mb_substr(trim((string)($data['name'] ?? '')), 0, 120); /* LOW-фикс стресс-теста: 10KB имя не копим */
$phone = trim((string)($data['phone'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$comment = mb_substr(trim((string)($data['comment'] ?? '')), 0, 2000);
/* Критик functional (gift-UX): получатель + открытка. Всё необязательное, лимиты серверные. */
$recipientName = mb_substr(trim((string)($data['recipient_name'] ?? '')), 0, 120);
$recipientPhone = trim((string)($data['recipient_phone'] ?? ''));
$cardText = mb_substr(trim((string)($data['card_text'] ?? '')), 0, 500);
/* Критик functional (слоты доставки): дата ДД.ММ.ГГГГ и интервал из настроек. */
$deliveryDate = trim((string)($data['delivery_date'] ?? ''));
$deliverySlot = mb_substr(trim((string)($data['delivery_slot'] ?? '')), 0, 60);
/* Honeypot (критик security: спам-боты на POST без реферера): заполненное скрытое поле — тихий отказ. */
if (trim((string)($data['company_website'] ?? '')) !== '') {
    respond(201, ['id' => 0, 'paymentToken' => '', 'honeypot' => true]);
}
/* Fail-safe: «online» принимаем только при реально настроенной ЮKassa
   (галочка + оба ключа). Прямые POST с online при пустых ключах → cash,
   заказ не теряется, владелец получит уведомление как обычно. */
$ykLive = setting('yk_enabled', '0') === '1'
    && trim(setting('yk_shop_id', '')) !== ''
    && trim(setting('yk_secret_key', '')) !== '';
$paymentMethod = ($data['payment_method'] ?? 'cash') === 'online' && $ykLive ? 'online' : 'cash';
$zoneId = (int)($data['delivery_zone_id'] ?? $data['delivery_zone'] ?? 0);
$address = trim((string)($data['delivery_address'] ?? ''));
$items = is_array($data['items'] ?? null) ? $data['items'] : [];

$phoneRe = '/^\+?[\d\s\-().]{7,20}$/';
$emailRe = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';

if (mb_strlen($name) < 2) {
    $errors[] = 'name';
}
if ($phone !== '' && !preg_match($phoneRe, $phone)) {
    $errors[] = 'phone';
}
/* Критик functional: те же правила для телефона получателя */
if ($recipientPhone !== '' && !preg_match($phoneRe, $recipientPhone)) {
    $errors[] = 'recipient_phone';
}
/* Дата: только ДД.ММ.ГГГГ и не в прошлом (серверная проверка, клиентская — подсказка) */
if ($deliveryDate !== '') {
    $d = DateTime::createFromFormat('d.m.Y', $deliveryDate);
    $today = new DateTime('today', new DateTimeZone(setting('shop_timezone', 'Europe/Moscow')));
    if (!$d || $d->setTime(0, 0) < $today || (int)$d->format('Ymd') > (int)$today->modify('+60 days')->format('Ymd')) {
        $errors[] = 'delivery_date_invalid';
    }
}
if ($email !== '' && !preg_match($emailRe, $email)) {
    $errors[] = 'email';
}
if ($phone === '' && $email === '') {
    $errors[] = 'contact_required';
}
if ($paymentMethod === 'online' && $email === '') {
    $errors[] = 'email_required_for_online';
}
if (!($data['pd_consent'] ?? false)) {
    respond(422, ['errors' => ['pd_consent_required']]);
}

$pdo = db();

// Зона доставки: 0 = самовывоз
$zone = null;
$zonePrice = 0;
if ($zoneId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM delivery_zones WHERE id = :i');
    $stmt->execute([':i' => $zoneId]);
    $zone = $stmt->fetch();
    if (!$zone) {
        $errors[] = 'delivery_zone_unavailable';
    } else {
        $zonePrice = (int)$zone['price'];
    }
    if ($address === '') {
        $errors[] = 'delivery_address_required';
    }
}

// Позиции заказа: цены берём строго с сервера, клиентские не доверяем.
// Критик security: серверные лимиты — qty строго int 1..99 (не молчаливая клампа),
// позиций не больше 20 (JS-контракт, раньше не исполнялся).
if (count($items) > 20) {
    $errors[] = 'too_many_items';
}
$normalized = [];
foreach ($items as $item) {
    $pid = (int)($item['product_id'] ?? 0);
    $qtyRaw = $item['qty'] ?? 1;
    if (!is_int($qtyRaw) && !(is_string($qtyRaw) && ctype_digit($qtyRaw))) {
        $errors[] = 'item_qty_invalid';
        continue;
    }
    $qty = (int)$qtyRaw;
    if ($qty < 1 || $qty > 99) {
        $errors[] = 'item_qty_invalid';
        continue;
    }
    if ($pid <= 0) {
        continue;
    }
    $stmt = $pdo->prepare('SELECT id, name, price, sale_price, is_active FROM products WHERE id = :i');
    $stmt->execute([':i' => $pid]);
    $p = $stmt->fetch();
    if (!$p || (int)$p['is_active'] !== 1) {
        respond(409, ['item' => ['product_id' => $pid]]);
    }
    $normalized[] = [
        'product_id' => (int)$p['id'],
        'name' => (string)$p['name'],
        'price' => productPrice($p),
        'qty' => $qty,
    ];
}

if ($normalized === []) {
    $errors[] = 'empty_cart';
}
if ($errors !== []) {
    respond(400, ['errors' => $errors]);
}

$subtotal = 0;
foreach ($normalized as $n) {
    $subtotal += $n['price'] * $n['qty'];
}
$total = $subtotal + ($zone !== null ? $zonePrice : 0);
$paymentToken = bin2hex(random_bytes(16));

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO orders (customer_name, phone, email, delivery_zone_id, delivery_address,
        comment, payment_method, total, status, payment_token, consent_log,
        recipient_name, recipient_phone, card_text, delivery_date, delivery_slot)
        VALUES (:n, :ph, :em, :z, :a, :c, :pm, :t, :st, :pt, :cl, :rn, :rp, :ct, :dd, :ds)');
    $stmt->execute([
        ':n' => $name, ':ph' => $phone, ':em' => $email,
        ':z' => $zone !== null ? (int)$zone['id'] : null,
        ':a' => $address, ':c' => $comment, ':pm' => $paymentMethod,
        ':t' => $total, ':st' => 'new', ':pt' => $paymentToken,
        ':cl' => sprintf('consent given %s, ip %s', date('Y-m-d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        ':rn' => $recipientName, ':rp' => $recipientPhone, ':ct' => $cardText,
        ':dd' => $deliveryDate, ':ds' => $deliverySlot,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, price, qty)
        VALUES (:o, :p, :n, :pr, :q)');
    foreach ($normalized as $n) {
        $itemStmt->execute([':o' => $orderId, ':p' => $n['product_id'], ':n' => $n['name'],
            ':pr' => $n['price'], ':q' => $n['qty']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    respond(500, ['errors' => ['internal']]);
}

notifyNewOrder($orderId);

respond(201, ['id' => $orderId, 'paymentToken' => $paymentToken]);
