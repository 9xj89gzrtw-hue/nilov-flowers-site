<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
/* Критик security re-check: logout по CSRF-токену — ссылку «Выйти» нельзя навязать
   жертве через <img src>. Без/с неверным токеном — 400 и выход не выполняется. */
adminSessionStart();
if (!csrf_verify((string)($_GET['t'] ?? ''))) {
    http_response_code(400);
    echo 'Некорректная ссылка выхода.';
    exit;
}
adminLogout();
header('Location: /admin/login.php');
exit;
