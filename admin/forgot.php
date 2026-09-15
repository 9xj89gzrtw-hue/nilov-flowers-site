<?php
/* «Забыли пароль?»: email → одноразовый токен (хеш в БД, TTL 30 мин) → письмо-ссылка. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();

if (isAdmin()) {
    header('Location: /admin/index.php');
    exit;
}

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Security-критик re-wave: без лимита можно завалить ящик письмами-сбросами
       (6 часовых токенов живут параллельно) — 4 запроса с IP за 10 минут. */
    if (!rl_check('forgot', 4, 600)) {
        http_response_code(429);
        $sent = false; // показываем форму без отправки
    } elseif (!csrf_check()) {
        header('Location: /admin/forgot.php');
        exit;
    }
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reset = createPasswordReset($email);
        if ($reset !== null) {
            $url = passwordResetUrl($reset);
            $body = "Здравствуйте!\r\n\r\nЗапрос на восстановление пароля в панели управления Nilov Flowers.\r\n"
                . "Ссылка для смены пароля (действует 30 минут, одноразовая):\r\n{$url}\r\n\r\n"
                . "Если вы не запрашивали восстановление — просто проигнорируйте это письмо.";
            sendAdminMail($email, 'Восстановление пароля — Nilov Flowers', $body);
        }
        /* одинаковый ответ независимо от существования email (не раскрываем наличие аккаунта) */
        $sent = true;
    } else {
        flash('Введите корректный email', true);
    }
}

adminSessionStart();
$err = $_SESSION['flash_err'] ?? false;
$flashMsg = $sent ? 'Если такой email зарегистрирован, письмо со ссылкой для восстановления отправлено. Ссылка действует 30 минут.' : null;
unset($_SESSION['flash'], $_SESSION['flash_err']);
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Восстановление пароля — Админ-панель</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Golos+Text:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--rose:#F4A9BE;--rose-deep:#E2799C;--bg:#F6F1E6;--ink:#2B2D2F;--ink-soft:#6E6A61;--line:rgba(43,45,47,.12);--err:#C43A3A;
--font-display:'Playfair Display',Georgia,serif;--font-ui:'Golos Text',system-ui,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-ui);color:var(--ink);background:var(--bg);min-height:100vh;display:grid;place-items:center;padding:20px}
.login{background:#fff;border-radius:26px;padding:36px;width:100%;max-width:380px;box-shadow:0 20px 50px -30px rgba(43,45,47,.4)}
h1{font-family:var(--font-display);font-weight:600;font-size:1.5rem;margin-bottom:6px}
p.sub{color:var(--ink-soft);font-size:.85rem;margin-bottom:20px}
label{display:block;font-size:.8rem;font-weight:600;margin:12px 0 4px}
input{width:100%;border:1.5px solid var(--line);border-radius:12px;padding:12px 14px;font:400 .95rem var(--font-ui);background:var(--bg)}
input:focus{outline:none;border-color:var(--rose-deep)}
input:focus-visible{outline:3px solid rgba(174,74,113,.55);outline-offset:2px}
button{width:100%;margin-top:20px;border:none;border-radius:999px;padding:14px;font:600 .95rem var(--font-ui);background:#AE4A71;color:#fff;cursor:pointer}
button:hover{background:#9E4062}
.err{margin-top:14px;color:var(--err);font-size:.85rem}
.ok{margin-top:14px;color:#3e8e5a;font-size:.85rem}
a.back{display:block;margin-top:16px;text-align:center;font-size:.85rem;color:var(--ink-soft)}
@media(hover:none),(pointer:coarse){a.back{min-height:44px;display:inline-flex;align-items:center;justify-content:center}} /* W76 */
</style>
</head>
<body>
<form class="login" method="post">
  <h1>Восстановление пароля</h1>
  <p class="sub">Введите email администратора — пришлём ссылку для смены пароля</p>
  <label for="email">Email</label>
  <input id="email" name="email" type="email" autocomplete="username" required autofocus>
  <button type="submit">Отправить ссылку</button>
  <?= csrf_field() ?>
  <?php if ($flashMsg !== null): ?><p class="ok"><?= e($flashMsg) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="err">Введите корректный email</p><?php endif; ?>
  <a class="back" href="/admin/login.php">← Вернуться ко входу</a>
</form>
</body>
</html>
