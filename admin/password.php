<?php
/* Устаревшая страница: смена пароля перенесена в /admin/profile.php.
   Редирект-заглушка на переходный период (1 месяц), старая ссылка ?ok=1 сохранена. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

if (isset($_GET['ok'])) {
    /* Совместимость со старыми редиректами password.php?ok=1 */
    flash('Пароль изменён');
}
header('Location: /admin/profile.php');
exit;
