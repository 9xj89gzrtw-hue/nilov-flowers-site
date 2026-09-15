<?php
/* Смена пароля по одноразовому токену: /admin/reset.php?token=...&id=UID */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();

if (isAdmin()) {
    header('Location: /admin/index.php');
    exit;
}

$token = (string)($_REQUEST['token'] ?? '');
$userId = (int)($_REQUEST['id'] ?? 0);
$valid = ($_SERVER['REQUEST_METHOD'] !== 'POST') ? validatePasswordReset($token, $userId) : null;
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        header('Location: /admin/login.php');
        exit;
    }
    $new = (string)($_POST['new'] ?? '');
    $new2 = (string)($_POST['new2'] ?? '');
    $row = validatePasswordReset((string)($_POST['token'] ?? ''), (int)($_POST['id'] ?? 0));
    if ($row === null) {
        flash('Ссылка недействительна или истекла. Запросите восстановление заново.', true);
        header('Location: /admin/forgot.php');
        exit;
    }
    if (mb_strlen($new) < 8) {
        flash('Новый пароль должен быть не короче 8 символов', true);
    } elseif ($new !== $new2) {
        flash('Новые пароли не совпадают', true);
    } else {
        completePasswordReset((int)$row['id'], (int)$row['user_id'], $new);
        flash('Пароль изменён. Войдите с новым паролем.');
        header('Location: /admin/login.php');
        exit;
    }
}

adminSessionStart();
$err = $_SESSION['flash_err'] ?? false;
$flashMsg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_err']);
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Новый пароль — Админ-панель</title>
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
<?php if ($valid !== null): ?>
<form class="login" method="post">
  <h1>Новый пароль</h1>
  <p class="sub">Придумайте новый пароль (минимум 8 символов)</p>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <input type="hidden" name="id" value="<?= (int)$userId ?>">
  <?= csrf_field() ?>
  <label for="p1">Новый пароль *</label>
  <input id="p1" name="new" type="password" autocomplete="new-password" minlength="8" required autofocus>
  <label for="p2">Новый пароль ещё раз *</label>
  <input id="p2" name="new2" type="password" autocomplete="new-password" minlength="8" required>
  <button type="submit">Сменить пароль</button>
  <?php if ($err !== false && $flashMsg !== null): ?><p class="err"><?= e($flashMsg) ?></p><?php endif; ?>
  <a class="back" href="/admin/login.php">← Вернуться ко входу</a>
</form>
<?php else: ?>
<div class="login">
  <h1>Ссылка недействительна</h1>
  <p class="sub">Ссылка уже использована, истекла или неверна.</p>
  <?php if ($err !== false && $flashMsg !== null): ?><p class="err"><?= e($flashMsg) ?></p><?php endif; ?>
  <a class="back" href="/admin/forgot.php">Запросить новое письмо</a>
  <a class="back" href="/admin/login.php">← Вернуться ко входу</a>
</div>
<?php endif; ?>
</body>
</html>
