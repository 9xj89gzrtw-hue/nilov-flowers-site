<?php
/* Смена собственного пароля администратора (текущий пароль обязателен). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $new2 = (string)($_POST['new2'] ?? '');

    $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :i');
    $stmt->execute([':i' => (int)$_SESSION['admin_id']]);
    $hash = (string)$stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        flash('Текущий пароль указан неверно', true);
    } elseif (mb_strlen($new) < 8) {
        flash('Новый пароль должен быть не короче 8 символов', true);
    } elseif ($new !== $new2) {
        flash('Новые пароли не совпадают', true);
    } else {
        $pdo->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :i')
            ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':i' => (int)$_SESSION['admin_id']]);
        flash('Пароль изменён');
        header('Location: /admin/index.php');
        exit;
    }
}

adminHeader('Смена пароля', 'password');
flash();
?>
<h1>Смена пароля</h1>
<div class="card" style="max-width:520px">
  <form method="post">
    <label class="f" for="cur">Текущий пароль *</label>
    <input class="input" id="cur" name="current" type="password" autocomplete="current-password" required>
    <label class="f" for="n1">Новый пароль * <small style="font-weight:400;color:var(--ink-soft)">(минимум 8 символов)</small></label>
    <input class="input" id="n1" name="new" type="password" autocomplete="new-password" minlength="8" required>
    <label class="f" for="n2">Новый пароль ещё раз *</label>
    <input class="input" id="n2" name="new2" type="password" autocomplete="new-password" minlength="8" required>
    <button class="btn btn--accent" type="submit" style="margin-top:16px">Сменить пароль</button>
  </form>
</div>
<?php adminFooter(); ?>
