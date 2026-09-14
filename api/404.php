<?php
/* Критик security: неизвестный /api/* путь отдавал HTML главной. Теперь — честный JSON 404. */
declare(strict_types=1);
/* security-критик re-check: голый api-файл не подключал security.php — не утекает версия PHP */
header_remove("X-Powered-By");
require_once __DIR__ . '/../includes/security.php';
security_headers(); /* security-критик re-check: JSON-эндпоинт тоже с nosniff/CSP-базой */
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['errors' => ['not_found']], JSON_UNESCAPED_UNICODE);
