<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

/* CSRF: админ-POST без валидного токена — отказ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    /* Стресс-критик W88: отказ = HTTP 400, а не 302-редирект (семантика ошибки запроса) */
    http_response_code(400);
    exit('Неверный CSRF-токен. Обновите страницу.');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $price = max(0, (int)($_POST['price'] ?? 0));
        $sort = (int)($_POST['sort'] ?? 0);
        if ($name !== '') {
            if ($id > 0) {
                $pdo->prepare('UPDATE delivery_zones SET name = :n, price = :p, sort = :s WHERE id = :i')
                    ->execute([':n' => $name, ':p' => $price, ':s' => $sort, ':i' => $id]);
                flash('Зона обновлена');
            } else {
                $pdo->prepare('INSERT INTO delivery_zones (name, price, sort) VALUES (:n, :p, :s)')
                    ->execute([':n' => $name, ':p' => $price, ':s' => $sort]);
                flash('Зона добавлена');
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM delivery_zones WHERE id = :i')->execute([':i' => (int)($_POST['id'] ?? 0)]);
        flash('Зона удалена');
    }
    header('Location: /admin/zones.php');
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM delivery_zones WHERE id = :i');
    $stmt->execute([':i' => $editId]);
    $editing = $stmt->fetch();
}
$zones = $pdo->query('SELECT * FROM delivery_zones ORDER BY sort, id')->fetchAll();

adminHeader('Зоны доставки', 'zones');
flash();
?>
<h1>Зоны доставки</h1>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px"><?= $editing ? 'Редактирование зоны' : 'Новая зона' ?></h2>
  <form method="post" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
    <div style="flex:1;min-width:200px">
      <label class="f" for="z-name">Название (район) *</label>
      <input class="input" id="z-name" name="name" required value="<?= $editing ? e($editing['name']) : '' ?>">
    </div>
    <div style="width:140px">
      <label class="f" for="z-price">Стоимость, ₽</label>
      <input class="input" id="z-price" name="price" type="number" min="0" value="<?= $editing ? (int)$editing['price'] : 0 ?>">
    </div>
    <div style="width:110px">
      <label class="f" for="z-sort">Сортировка</label>
      <input class="input" id="z-sort" name="sort" type="number" value="<?= $editing ? (int)$editing['sort'] : 0 ?>">
    </div>
    <button class="btn btn--accent" type="submit"><?= $editing ? 'Сохранить' : 'Добавить' ?></button>
    <?php if ($editing): ?><a class="btn btn--ghost" href="/admin/zones.php">Отмена</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-scroll"><table>
    <tr><th>Район</th><th>Стоимость</th><th>Сортировка</th><th></th></tr>
    <?php foreach ($zones as $z): ?>
    <tr>
      <td><strong><?= e($z['name']) ?></strong></td>
      <td><?= formatPrice((int)$z['price']) ?></td>
      <td><?= (int)$z['sort'] ?></td>
      <td>
        <div class="row-actions">
          <a href="/admin/zones.php?edit=<?= (int)$z['id'] ?>">Изменить</a>
          <form method="post" onsubmit="return confirm('Удалить зону доставки?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
            <button type="submit" class="danger">Удалить</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php adminFooter(); ?>
