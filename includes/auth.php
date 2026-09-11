<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function adminSessionStart(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('floweradmin');
        session_start();
    }
}

function adminLogin(string $login, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash FROM admin_users WHERE login = :l LIMIT 1');
    $stmt->execute([':l' => $login]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        adminSessionStart();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['admin_login'] = $login;
        return true;
    }
    return false;
}

function adminLogout(): void
{
    adminSessionStart();
    $_SESSION = [];
    session_destroy();
}

function isAdmin(): bool
{
    adminSessionStart();
    return isset($_SESSION['admin_id']);
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/** Создаёт единственного администратора, если его ещё нет (логин/пароль по умолчанию). */
function ensureAdminUser(): void
{
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL
    )');
    $count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO admin_users (login, password_hash) VALUES (:l, :h)');
        $stmt->execute([':l' => 'admin', ':h' => password_hash('admin123', PASSWORD_DEFAULT)]);
    }
}
