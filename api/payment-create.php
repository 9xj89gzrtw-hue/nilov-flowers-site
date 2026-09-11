<?php
/**
 * POST /api/payment/create — {orderId, token} → {redirectUrl}
 * ЮKassa REST API v3: POST https://api.yookassa.ru/v3/payments
 *   Basic auth (shopId:secretKey) из settings (yk_shop_id / yk_secret_key),
 *   Idempotence-Key, confirmation: redirect.
 * Пустые ключи → демо-режим: заказ подтверждается сразу (оплата при получении).
 * Чек 54-ФЗ: receipt (prepayment) отправляется вместе с платежом, если заданы ключи;
 *   статус чека пишется в orders.receipt_prepay (ok|failed|demo).
 * Canonical: https://yookassa.ru/developers/payment-acceptance/receipts/54fz/other-services/payments (2026-09-11)
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Метод не поддерживается']);
}

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) {
    respond(400, ['error' => 'Некорректный запрос']);
}

$orderId = (int)($data['orderId'] ?? 0);
$token = (string)($data['token'] ?? '');

$stmt = db()->prepare('SELECT id, payment_token, payment_method, total, customer_name, email, phone FROM orders WHERE id = :i');
$stmt->execute([':i' => $orderId]);
$order = $stmt->fetch();

if (!$order || !hash_equals((string)$order['payment_token'], $token)) {
    respond(404, ['error' => 'Заказ не найден']);
}

$shopId = setting('yk_shop_id', '');
$secret = setting('yk_secret_key', '');
$baseUrl = 'https://flowers.interfood-catering.ru';

/* Демо-режим: ключи не заданы ИЛИ ЮKassa выключена в админке — оплата при получении */
if ($shopId === '' || $secret === '' || setting('yk_enabled', '0') !== '1') {
    respond(200, ['redirectUrl' => '/order-thanks?id=' . $orderId, 'demo' => true]);
}

/* Состав чека: позиции заказа + НДС из настроек */
$itemsStmt = db()->prepare('SELECT name, price, qty FROM order_items WHERE order_id = :i');
$itemsStmt->execute([':i' => $orderId]);
$positions = [];
foreach ($itemsStmt->fetchAll() as $it) {
    $positions[] = [
        'description' => mb_substr((string)$it['name'], 0, 128),
        'quantity' => number_format((float)$it['qty'], 2, '.', ''),
        'amount' => ['value' => number_format((float)$it['price'], 2, '.', ''), 'currency' => 'RUB'],
        'vat_code' => vatCode(setting('vat_rate', 'none')),
        'payment_mode' => 'full_prepayment',
        'payment_subject' => 'commodity',
    ];
}

$payload = [
    'amount' => ['value' => number_format((float)$order['total'], 2, '.', ''), 'currency' => 'RUB'],
    'confirmation' => ['type' => 'redirect', 'return_url' => $baseUrl . '/order-thanks?id=' . $orderId],
    'capture' => true,
    'description' => 'Заказ №' . $orderId . ' — ' . setting('shop_name', 'Nilov Flowers'),
    'metadata' => ['order_id' => (string)$orderId],
    'receipt' => [
        'customer' => [
            'email' => $order['email'] !== '' ? (string)$order['email'] : null,
            'phone' => $order['phone'] !== '' ? preg_replace('/\D/', '', (string)$order['phone']) : null,
        ],
        'items' => $positions,
    ],
];
$payload['receipt']['customer'] = array_filter($payload['receipt']['customer'], fn($v) => $v !== null);
if ($payload['receipt']['customer'] === []) {
    unset($payload['receipt']);
}

$idem = bin2hex(random_bytes(16));
$ch = curl_init('https://api.yookassa.ru/v3/payments');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_USERPWD => $shopId . ':' . $secret,
    CURLOPT_HTTPHEADER => [
        'Idempotence-Key: ' . $idem,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$raw = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($raw === false || $httpCode >= 400) {
    /* Фискальный сбой/сетевой сбой — не ломаем заказ, чек помечаем failed (кнопка retry в админке) */
    db()->prepare("UPDATE orders SET receipt_prepay = 'failed' WHERE id = :i")->execute([':i' => $orderId]);
    respond(200, ['redirectUrl' => '/order-thanks?id=' . $orderId, 'paymentFallback' => true,
        'note' => 'Платёж временно недоступен, оплата при получении']);
}

$resp = json_decode((string)$raw, true);
$confirmationUrl = $resp['confirmation']['confirmation_url'] ?? '';
if ($confirmationUrl === '') {
    db()->prepare("UPDATE orders SET receipt_prepay = 'failed' WHERE id = :i")->execute([':i' => $orderId]);
    respond(200, ['redirectUrl' => '/order-thanks?id=' . $orderId, 'paymentFallback' => true]);
}

db()->prepare("UPDATE orders SET receipt_prepay = 'ok', payment_token = :t WHERE id = :i")
    ->execute([':t' => (string)($resp['id'] ?? $token), ':i' => $orderId]);

respond(200, ['redirectUrl' => $confirmationUrl]);

/* Код ставки НДС ЮKassa; default: none (УСН) */
function vatCode(string $rate): int
{
    return match ($rate) {
        '0' => 2,        // НДС 0%
        '10' => 4,       // НДС 10% (1105 = 5)
        '20' => 3,       // НДС 20% (1104 = 6)
        default => 1,    // без НДС (УСН) — canonical default для большинства магазинов-ИП
    };
}
