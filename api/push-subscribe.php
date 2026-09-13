<?php
/* Сохранение Web Push-подписки покупателя/владельца. POST {sub, keys?} JSON.
   Идемпотентно (endpoint UNIQUE). Только при включённой фиче feature_webpush. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';

header('Content-Type: application/json; charset=utf-8');

function respondp(int $code, array $payload): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { respondp(405, ['error' => 'method']); }
if (setting('feature_webpush', '0') !== '1') { respondp(403, ['error' => 'disabled']); }

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) { respondp(400, ['error' => 'json']); }

$endpoint = trim((string)($data['endpoint'] ?? ''));
$p256 = trim((string)(($data['keys']['p256dh'] ?? '')));
$auth = trim((string)(($data['keys']['auth'] ?? '')));
if ($endpoint === '' || strlen($endpoint) > 2048 || !preg_match('#^https://#', $endpoint)) {
    respondp(400, ['error' => 'endpoint']);
}
if ($p256 === '' || $auth === '') { respondp(400, ['error' => 'keys']); }
if (strlen($p256) > 256 || strlen($auth) > 128) { respondp(400, ['error' => 'keys_len']); }

try {
    $pdo = db();
    $pdo->prepare('INSERT OR REPLACE INTO push_subscriptions (endpoint, sub_json) VALUES (:e, :j)')
        ->execute([':e' => $endpoint, ':j' => json_encode($data, JSON_UNESCAPED_SLASHES)]);
} catch (Throwable $e) {
    respondp(500, ['error' => 'db']);
}
respondp(201, ['ok' => true]);
