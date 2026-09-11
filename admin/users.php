<?php
/* Сотрудники: CRUD админ-пользователей (только role=owner). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

$me = currentAdmin();
$isOwner = $me !== null && (string)($me['role'] ?? 'owner') === 'owner';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isOwner) {
        http_response_code(403);
    } elseif (!csrf_check()) {
        flash('Ошибка безопасности: неверный CSRF-токен. Обновите страницу и попробуйте снова.', true);
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create') {
            $email = trim((string)($_POST['email'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? '') === 'owner' ? 'owner' : 'staff';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('Укажите корректный email', true);
            } elseif (mb_strlen($password) < 8) {
                flash('Пароль должен быть не короче 8 символов', true);
            } else {
                $st = $pdo->prepare('SELECT id FROM admin_users WHERE login = :l OR email = :e LIMIT 1');
                $st->execute([':l' => $email, ':e' => $email]);
                if ($st->fetchColumn() !== false) {
                    flash('Сотрудник с таким email уже существует', true);
                } else {
                    $pdo->prepare('INSERT INTO admin_users (login, password_hash, email, name, role, notify_enabled, notify_email)
                        VALUES (:l, :h, :e, :n, :r, 1, :e)')
                        ->execute([':l' => $email, ':h' => password_hash($password, PASSWORD_DEFAULT), ':e' => $email, ':n' => $name, ':r' => $role]);
                    flash('Сотрудник добавлен: ' . $email);
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0 || (int)$me['id'] === $id) {
                flash('Нельзя удалить самого себя', true);
            } else {
                $st = $pdo->prepare('SELECT role FROM admin_users WHERE id = :i');
                $st->execute([':i' => $id]);
                $targetRole = (string)($st->fetchColumn() ?: '');
                if ($targetRole === '') {
                    flash('Сотрудник не найден', true);
                } elseif ((int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() <= 1) {
                    flash('Нельзя удалить последнего сотрудника', true);
                } elseif ($targetRole === 'owner'
                    && (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'owner'")->fetchColumn() <= 1) {
                    flash('Нельзя удалить последнего владельца — сначала назначьте другого owner', true);
                } else {
                    $pdo->prepare('DELETE FROM admin_users WHERE id = :i')->execute([':i' => $id]);
                    flash('Сотрудник удалён');
                }
            }
        } elseif ($action === 'toggle_notify') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare('SELECT notify_enabled FROM admin_users WHERE id = :i');
            $st->execute([':i' => $id]);
            $cur = $st->fetchColumn();
            if ($cur === false) {
                flash('Сотрудник не найден', true);
            } else {
                $pdo->prepare('UPDATE admin_users SET notify_enabled = :v WHERE id = :i')
                    ->execute([':v' => (int)$cur === 1 ? 0 : 1, ':i' => $id]);
                flash('Уведомления ' . ((int)$cur === 1 ? 'выключены' : 'включены'));
            }
        } else {
            flash('Неизвестное действие', true);
        }
    }
    header('Location: /admin/users.php');
    exit;
}

$users = $pdo->query('SELECT id, login, email, name, role, notify_enabled, created_at
    FROM admin_users ORDER BY id ASC')->fetchAll();

adminHeader('Сотрудники', 'users');
flash();
?>
<?php if (!$isOwner): ?>
<h1>Сотрудники</h1>
<div class="flash flash--err">Доступ только для владельца магазина. Обратитесь к владельцу, если нужен доступ.</div>
<?php else: ?>
<h1>Сотрудники</h1>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Добавить сотрудника</h2>
  <form method="post" class="filters-bar">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div>
      <label class="f" for="u-email">Email (он же логин) *</label>
      <input class="input" id="u-email" name="email" type="email" required placeholder="staff@example.com">
    </div>
    <div>
      <label class="f" for="u-name">Имя</label>
      <input class="input" id="u-name" name="name" placeholder="Анна">
    </div>
    <div>
      <label class="f" for="u-pass">Пароль * <small style="font-weight:400">(мин. 8 символов)</small></label>
      <input class="input" id="u-pass" name="password" type="password" minlength="8" required autocomplete="new-password">
    </div>
    <div>
      <label class="f" for="u-role">Роль</label>
      <select id="u-role" name="role">
        <option value="staff">Сотрудник</option>
        <option value="owner">Владелец</option>
      </select>
    </div>
    <button class="btn btn--accent" type="submit">Добавить</button>
  </form>
</div>

<div class="card">
  <div class="table-scroll"><table>
    <tr><th>Email</th><th>Имя</th><th>Роль</th><th>Уведомления</th><th>Добавлен</th><th></th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td>
        <strong><?= e($u['email'] !== '' ? $u['email'] : $u['login']) ?></strong>
        <?= (int)$u['id'] === (int)$me['id'] ? ' <span class="status-badge">это вы</span>' : '' ?>
      </td>
      <td><?= $u['name'] !== '' ? e($u['name']) : '—' ?></td>
      <td><span class="status-badge <?= (string)$u['role'] === 'owner' ? 'new' : '' ?>"><?= (string)$u['role'] === 'owner' ? 'Владелец' : 'Сотрудник' ?></span></td>
      <td>
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle_notify">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="linklike"><?= (int)$u['notify_enabled'] === 1 ? '🔔 Вкл' : '🔕 Выкл' ?></button>
        </form>
      </td>
      <td style="color:var(--ink-soft)"><?= e((string)($u['created_at'] ?? '')) ?></td>
      <td>
        <?php if ((int)$u['id'] !== (int)$me['id']): ?>
        <div class="row-actions">
          <form method="post" onsubmit="return confirm('Удалить сотрудника <?= e($u['email'] !== '' ? $u['email'] : $u['login']) ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button type="submit" class="danger">Удалить</button>
          </form>
        </div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
<?php adminFooter(); ?>
