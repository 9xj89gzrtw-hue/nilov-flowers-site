<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

/* CSRF: админ-POST без валидного токена — отказ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    $back = $_SERVER['HTTP_REFERER'] ?? '/admin/index.php';
    header('Location: ' . $back);
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $sort = (int)($_POST['sort'] ?? 0);
        if ($name !== '') {
            if ($id > 0) {
                $pdo->prepare('UPDATE categories SET name = :n, sort = :s WHERE id = :i')
                    ->execute([':n' => $name, ':s' => $sort, ':i' => $id]);
                flash('Категория обновлена');
            } else {
                $pdo->prepare('INSERT INTO categories (name, sort) VALUES (:n, :s)')
                    ->execute([':n' => $name, ':s' => $sort]);
                flash('Категория добавлена');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE products SET category_id = NULL WHERE category_id = :i')->execute([':i' => $id]);
        $pdo->prepare('DELETE FROM categories WHERE id = :i')->execute([':i' => $id]);
        flash('Категория удалена');
    }
    header('Location: /admin/categories.php');
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :i');
    $stmt->execute([':i' => $editId]);
    $editing = $stmt->fetch();
}
$categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS cnt
    FROM categories c ORDER BY c.sort, c.id')->fetchAll();

adminHeader('Категории', 'categories');
flash();
?>
<h1>Категории</h1>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px"><?= $editing ? 'Редактирование категории' : 'Новая категория' ?></h2>
  <form method="post" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
    <div style="flex:1;min-width:200px">
      <label class="f" for="c-name">Название *</label>
      <input class="input" id="c-name" name="name" required value="<?= $editing ? e($editing['name']) : '' ?>">
    </div>
    <div style="width:110px">
      <label class="f" for="c-sort">Сортировка</label>
      <input class="input" id="c-sort" name="sort" type="number" value="<?= $editing ? (int)$editing['sort'] : 0 ?>">
    </div>
    <button class="btn btn--accent" type="submit"><?= $editing ? 'Сохранить' : 'Добавить' ?></button>
    <?php if ($editing): ?><a class="btn btn--ghost" href="/admin/categories.php">Отмена</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <table>
    <tr><th>Название</th><th>Товаров</th><th>Сортировка</th><th></th></tr>
    <?php foreach ($categories as $c): ?>
    <tr>
      <td><strong><?= e($c['name']) ?></strong></td>
      <td><?= (int)$c['cnt'] ?></td>
      <td><?= (int)$c['sort'] ?></td>
      <td>
        <div class="row-actions">
          <a href="/admin/categories.php?edit=<?= (int)$c['id'] ?>">Изменить</a>
          <form method="post" onsubmit="return confirm('Удалить категорию? Товары останутся без категории.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="danger">Удалить</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php adminFooter(); ?>
