<?php
/** GET /admin/poll.php → {"count": N} — новых заказов (status='new'). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

adminSessionStart();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$count = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn();
echo json_encode(['count' => $count]);
