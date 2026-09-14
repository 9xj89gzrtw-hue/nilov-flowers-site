<?php
/* Критик security: неизвестный /api/* путь отдавал HTML главной. Теперь — честный JSON 404. */
declare(strict_types=1);
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['errors' => ['not_found']], JSON_UNESCAPED_UNICODE);
