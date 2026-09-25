<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';

function adminSessionStart(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        /* Критик security re-check: кука админки — только в области /admin */
        session_set_cookie_params(['lifetime' => 0, 'path' => '/admin', 'domain' => '', 'secure' => true,
            'httponly' => true, 'samesite' => 'Lax']);
        session_name('floweradmin');
        session_start();
    }
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    adminSessionStart();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Сравнивает токен из POST с токеном сессии (hash_equals). */
function csrf_verify(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    return hash_equals(csrf_token(), $token);
}

/**
 * Проверка CSRF для админ-POST: без валидного токена — flash-ошибка и отказ.
 * Требует подключённый layout.php (flash()).
 */
function csrf_check(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        if (function_exists('flash')) {
            flash('Ошибка безопасности: неверный CSRF-токен. Обновите страницу и попробуйте снова.', true);
        }
        return false;
    }
    return true;
}

/** hidden-поле для админ-форм. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/* ---------- Аутентификация ---------- */

function adminLogin(string $login, string $password): bool
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $pdo = db();

    /* Rate-limit: >=5 неудачных попыток с IP за 15 минут — блок */
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
        WHERE ip = :ip AND attempted_at >= datetime('now','localtime','-15 minutes')");
    $stmt->execute([':ip' => $ip]);
    if ((int)$stmt->fetchColumn() >= 5) {
        throw new RuntimeException('Слишком много попыток, подождите 15 минут');
    }

    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE login = :l OR email = :l LIMIT 1');
    $stmt->execute([':l' => $login]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        adminSessionStart();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['admin_login'] = $login;
        return true;
    }

    /* неудачная попытка — фиксируем IP */
    $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (:ip)')->execute([':ip' => $ip]);
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
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    /* J2: таймаут неактивности 8 часов — сессия гаснет сама (не «вечно живая») */
    $last = (int)($_SESSION['admin_last_activity'] ?? 0);
    $now = time();
    if ($last > 0 && ($now - $last) > 8 * 3600) {
        $_SESSION = [];
        session_destroy();
        return false;
    }
    $_SESSION['admin_last_activity'] = $now;
    return true;
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Создаёт единственного администратора, если его ещё нет (логин/пароль по умолчанию).
 * Email по умолчанию совпадает с логином — работает вход и по email.
 */
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
        /* Security-критик re-wave: предсказуемых кредов admin123 больше нет.
           Пароль генерируется случайно и кладётся в файл вне web-доступа (db/ закрыт .htaccess=403).
           При env ADMIN_BOOTSTRAP_LOGIN/ADMIN_BOOTSTRAP_PASSWORD используются они. */
        $login = getenv('ADMIN_BOOTSTRAP_LOGIN') ?: 'admin@example.com';
        $pass = getenv('ADMIN_BOOTSTRAP_PASSWORD') ?: bin2hex(random_bytes(8));
        $stmt = $pdo->prepare('INSERT INTO admin_users (login, password_hash, email) VALUES (:l, :h, :e)');
        $stmt->execute([':l' => $login, ':h' => password_hash($pass, PASSWORD_DEFAULT), ':e' => $login]);
        /* W100 (security-критик): concat-баг — BASE_PATH без хвостового слэша писал файл
           в несуществующий «<root>db/…» наружу (запись молча проваливалась, владелец
           нового инсталла не видел пароль). Путь исправлен; db/ закрыт .htaccess=403/410. */
        @file_put_contents(BASE_PATH . '/db/admin-bootstrap-' . date('Ymd-His') . '.txt',
            "login: {$login}\npassword: {$pass}\nСмените пароль в админке и удалите этот файл.\n");
    }
}

/**
 * Текущий админ из admin_users (или null).
 * @return array<string,mixed>|null
 */
function currentAdmin(): ?array
{
    if (!isAdmin()) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :i LIMIT 1');
    $stmt->execute([':i' => (int)$_SESSION['admin_id']]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/* ---------- Восстановление пароля (паттерн codeshack.io 2026) ---------- */

/**
 * Создаёт токен восстановления: в БД — hash('sha256', $token), TTL 30 мин.
 * @return array{token:string,user_id:int}|null (null — email не найден)
 */
function createPasswordReset(string $email): ?array
{
    $stmt = db()->prepare('SELECT id FROM admin_users WHERE email = :e OR login = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $uid = $stmt->fetchColumn();
    if ($uid === false) {
        return null;
    }
    $token = bin2hex(random_bytes(32));
    db()->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, used)
        VALUES (:u, :h, datetime('now','localtime','+30 minutes'), 0)")
        ->execute([':u' => (int)$uid, ':h' => hash('sha256', $token)]);
    return ['token' => $token, 'user_id' => (int)$uid];
}

/** Ссылка для письма: /admin/reset.php?token=...&id=UID */
function passwordResetUrl(array $reset): string
{
    /* W98 (security-критик): base строился из REQUEST_SCHEME+HTTP_HOST — host-header injection
       в письме восстановления (отравленная ссылка). Канонический домен задаётся setting'ом
       site_url (используется и в notify.php), фолбэк — прежнее поведение для CLI/крайних случаев. */
    $base = trim((string)setting('site_url', ''));
    if ($base === '' || !preg_match('#^https?://[a-z0-9.\-]+#i', $base)) {
        $base = (string)(($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }
    return rtrim($base, '/') . '/admin/reset.php?token=' . urlencode($reset['token']) . '&id=' . (int)$reset['user_id'];
}

/**
 * Валидирует токен: не used, не истёк, hash_equals по хешу.
 * @return array<string,mixed>|null строка password_resets
 */
function validatePasswordReset(string $token, int $userId): ?array
{
    if ($token === '' || $userId <= 0) {
        return null;
    }
    $stmt = db()->prepare("SELECT id, user_id, token_hash, expires_at, used FROM password_resets
        WHERE user_id = :u AND used = 0 AND expires_at >= datetime('now','localtime')
        ORDER BY id DESC LIMIT 1");
    $stmt->execute([':u' => $userId]);
    $row = $stmt->fetch();
    if ($row === false || !hash_equals((string)$row['token_hash'], hash('sha256', $token))) {
        return null;
    }
    return $row;
}

/** Одноразовое использование: смена пароля + used=1 + очистка старых токенов пользователя. */
function completePasswordReset(int $resetId, int $userId, string $newPassword): void
{
    $pdo = db();
    $pdo->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :u')
        ->execute([':h' => password_hash($newPassword, PASSWORD_DEFAULT), ':u' => $userId]);
    $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :i')
        ->execute([':i' => $resetId]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = :u AND used = 1')
        ->execute([':u' => $userId]);
}

/**
 * Отправка письма: mail() с fallback в .eml (STATE_OUT_DIR — вне docroot) при false.
 */
function sendAdminMail(string $to, string $subject, string $body): bool
{
    $headers = "From: no-reply@nilov-flowers.local\r\nContent-Type: text/plain; charset=UTF-8";
    $ok = @mail($to, $subject, $body, $headers);
    if (!$ok) {
        $dir = STATE_OUT_DIR . '/mail-out';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $eml = "To: {$to}\r\nSubject: {$subject}\r\n{$headers}\r\nDate: " . date('r') . "\r\n\r\n{$body}";
        @file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml', $eml);
    }
    return $ok;
}
