<?php
/* CSP report endpoint (критик security): принимает отчёты, всегда 204.
   Логирует в /tmp только при наличии setrl — без БД-мусора. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header_remove("X-Powered-By");
http_response_code(204);
exit;
