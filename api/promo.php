<?php
/**
 * POST /api/promo {code, subtotal} → {ok:true, discount, label} | {ok:false, error}
 * Промокод валидируется СТРОГО на сервере (критик functional: клиентским цифрам не доверяем).
 * kind: percent (value 1..90) | fixed (value в ₽). min_order — порог субтотала. max_uses=0 — безлимит.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}
if (!rl_check('promo', 10, 300)) {
    respond(429, ['ok' => false, 'error' => 'rate_limited']);
}
$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) {
    respond(400, ['ok' => false, 'error' => 'bad_json']);
}
$code = mb_strtoupper(trim((string)($data['code'] ?? '')), 'UTF-8');
$subtotal = max(0, (int)($data['subtotal'] ?? 0));
if ($code === '' || mb_strlen($code) > 32) {
    respond(200, ['ok' => false, 'error' => 'empty']);
}
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE code = :c AND active = 1');
    $stmt->execute([':c' => $code]);
    $promo = $stmt->fetch();
    if (!$promo) {
        respond(200, ['ok' => false, 'error' => 'invalid']);
    }
    if ((int)$promo['max_uses'] > 0 && (int)$promo['used'] >= (int)$promo['max_uses']) {
        respond(200, ['ok' => false, 'error' => 'exhausted']);
    }
    if ($subtotal < (int)$promo['min_order']) {
        respond(200, ['ok' => false, 'error' => 'min_order', 'min' => (int)$promo['min_order']]);
    }
    $discount = $promo['kind'] === 'fixed'
        ? min((int)$promo['value'], $subtotal)
        : (int)floor($subtotal * max(0, min(90, (int)$promo['value'])) / 100);
    respond(200, [
        'ok' => true,
        'code' => $code,
        'discount' => $discount,
        'label' => $promo['kind'] === 'fixed'
            ? '−' . number_format($discount, 0, '', ' ') . ' ₽ по промокоду ' . $code
            : '-' . (int)$promo['value'] . '% по промокоду ' . $code,
    ]);
} catch (Throwable $e) {
    error_log('promo: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'server']);
}
