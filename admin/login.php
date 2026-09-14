<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
ensureAdminUser();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        /* Критик security: брутфорс-лок — не больше 10 попыток/15 мин на IP */
        if (!rl_check('adminlogin', 10, 900)) {
            $err = 'Слишком много попыток входа. Подождите 15 минут и попробуйте снова.';
        } elseif (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
            $err = 'Ошибка безопасности. Обновите страницу и попробуйте ещё раз.';
        } elseif (adminLogin(trim((string)($_POST['login'] ?? '')), (string)($_POST['password'] ?? ''))) {
            header('Location: /admin/index.php');
            exit;
        } else {
            $err = 'Неверный логин или пароль';
        }
    } catch (RuntimeException $e) {
        $err = $e->getMessage();
    }
}
if (isAdmin()) {
    header('Location: /admin/index.php');
    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход — Админ-панель</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Golos+Text:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--rose:#F4A9BE;--rose-deep:#E2799C;--bg:#F6F1E6;--bg-alt:#EFE7D8;--ink:#2B2D2F;--ink-soft:#6E6A61;--line:rgba(43,45,47,.12);--err:#d64545;
--font-display:'Playfair Display',Georgia,serif;--font-ui:'Golos Text',system-ui,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-ui);color:var(--ink);background:var(--bg);min-height:100vh;display:grid;place-items:center;padding:20px}
.login{background:#fff;border-radius:26px;padding:36px;width:100%;max-width:380px;box-shadow:0 20px 50px -30px rgba(43,45,47,.4)}
h1{font-family:var(--font-display);font-weight:600;font-size:1.5rem;margin-bottom:6px}
p.sub{color:var(--ink-soft);font-size:.85rem;margin-bottom:20px}
label{display:block;font-size:.8rem;font-weight:600;margin:12px 0 4px}
input{width:100%;border:1.5px solid var(--line);border-radius:12px;padding:12px 14px;font:400 .95rem var(--font-ui);background:var(--bg)}
input:focus{outline:none;border-color:var(--rose-deep)}
button{width:100%;margin-top:20px;border:none;border-radius:999px;padding:14px;font:600 .95rem var(--font-ui);background:var(--rose-deep);color:#fff;cursor:pointer}
button:hover{background:#d46a90}
.err{margin-top:14px;color:var(--err);font-size:.85rem}
</style>
</head>
<body>
<form class="login" method="post">
  <h1>Вход для администратора</h1>
  <p class="sub">Панель управления магазином цветов</p>
  <label for="login">Логин</label>
  <input id="login" name="login" autocomplete="username" required autofocus>
  <label for="password">Пароль</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>
  <button type="submit">Войти</button>
  <?= csrf_field() ?>
  <?php if ($err !== ''): ?><p class="err"><?= e($err) ?></p><?php endif; ?>
  <a href="/admin/forgot.php" style="display:block;margin-top:14px;text-align:center;font-size:.85rem;color:var(--ink-soft)">Забыли пароль?</a>
</form>
</body>
</html>
