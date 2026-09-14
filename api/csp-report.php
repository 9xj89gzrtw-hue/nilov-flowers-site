<?php
/* CSP report endpoint (критик security): принимает отчёты, всегда 204. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/security.php'; /* security-критик: JSON-эндпоинт с полными заголовками */
header('Content-Type: application/json; charset=utf-8');
header_remove("X-Powered-By");
/* Security-критик W46 LOW-остаток: report-uri был не лимитирован — спам-POST мог
   долбить эндпоинт. 60 репортов/минуту/IP достаточно честному браузеру. */
if (!rl_check('csp-report', 60, 60)) {
    http_response_code(204); /* тихо, без различимого oracle-ответа */
    exit;
}
http_response_code(204);
exit;
