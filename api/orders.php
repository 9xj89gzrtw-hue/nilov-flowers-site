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
$name = trim((string)($data['name'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$comment = mb_substr(trim((string)($data['comment'] ?? '')), 0, 2000);
$paymentMethod = ($data['payment_method'] ?? 'cash') === 'online' ? 'online' : 'cash';
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
    $errors[] = 'pd_consent';
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

// Позиции заказа: цены берём строго с сервера, клиентские не доверяем
$normalized = [];
foreach ($items as $item) {
    $pid = (int)($item['product_id'] ?? 0);
    $qty = max(1, min(99, (int)($item['qty'] ?? 1)));
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
        comment, payment_method, total, status, payment_token)
        VALUES (:n, :ph, :em, :z, :a, :c, :pm, :t, :st, :pt)');
    $stmt->execute([
        ':n' => $name, ':ph' => $phone, ':em' => $email,
        ':z' => $zone !== null ? (int)$zone['id'] : null,
        ':a' => $address, ':c' => $comment, ':pm' => $paymentMethod,
        ':t' => $total, ':st' => 'new', ':pt' => $paymentToken,
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
