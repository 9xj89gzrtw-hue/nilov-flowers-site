<?php
/**
 * POST /api/payment/create — {orderId, token} → {redirectUrl}
 * Заглушка платёжного провайдера: подтверждает заказ и возвращает
 * страницу подтверждения. Реальный эквайринг подключается здесь.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

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

$stmt = db()->prepare('SELECT id, payment_token, payment_method FROM orders WHERE id = :i');
$stmt->execute([':i' => $orderId]);
$order = $stmt->fetch();

if (!$order || !hash_equals($order['payment_token'], $token)) {
    respond(404, ['error' => 'Заказ не найден']);
}

respond(200, ['redirectUrl' => '/order-thanks?id=' . $orderId]);
