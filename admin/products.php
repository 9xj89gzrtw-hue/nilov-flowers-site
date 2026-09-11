<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

$pdo = db();

// Удаление
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT image FROM products WHERE id = :i');
    $stmt->execute([':i' => $id]);
    $img = (string)$stmt->fetchColumn();
    if ($img !== '') {
        deleteImage($img, IMG_PRODUCTS_DIR);
    }
    $pdo->prepare('DELETE FROM products WHERE id = :i')->execute([':i' => $id]);
    flash('Товар удалён');
    header('Location: /admin/products.php');
    exit;
}

// Сохранение (создание/редактирование)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $price = (int)($_POST['price'] ?? 0);
    $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
    $salePrice = $salePriceRaw !== '' ? max(0, (int)$salePriceRaw) : null;
    $description = trim((string)($_POST['description'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $showInUpsell = isset($_POST['show_in_upsell']) ? 1 : 0;
    $sort = (int)($_POST['sort'] ?? 0);

    if ($name === '' || $price <= 0) {
        flash('Укажите название и цену товара', true);
        header('Location: /admin/products.php');
        exit;
    }

    $image = saveUpload($_FILES['image'] ?? [], IMG_PRODUCTS_DIR);

    if ($id > 0) {
        if ($image !== '') {
            $stmt = $pdo->prepare('SELECT image FROM products WHERE id = :i');
            $stmt->execute([':i' => $id]);
            deleteImage((string)$stmt->fetchColumn(), IMG_PRODUCTS_DIR);
            $pdo->prepare('UPDATE products SET image = :img WHERE id = :i')
                ->execute([':img' => $image, ':i' => $id]);
        }
        $pdo->prepare('UPDATE products SET category_id = :c, name = :n, slug = :sl, price = :p,
                sale_price = :sp, description = :d, is_active = :a, show_in_upsell = :u, sort = :s WHERE id = :i')
            ->execute([':c' => $categoryId, ':n' => $name, ':sl' => slugify($name), ':p' => $price,
                ':sp' => $salePrice, ':d' => $description, ':a' => $isActive, ':u' => $showInUpsell, ':s' => $sort, ':i' => $id]);
        flash('Товар обновлён');
    } else {
        $slug = slugify($name);
        $base = $slug;
        $n = 1;
        while ((int)$pdo->query('SELECT COUNT(*) FROM products WHERE slug = ' . $pdo->quote($slug))->fetchColumn() > 0) {
            $slug = $base . '-' . (++$n);
        }
        $pdo->prepare('INSERT INTO products (category_id, name, slug, price, sale_price, description, image, is_active, show_in_upsell, sort)
                VALUES (:c, :n, :sl, :p, :sp, :d, :img, :a, :u, :s)')
            ->execute([':c' => $categoryId, ':n' => $name, ':sl' => $slug, ':p' => $price,
                ':sp' => $salePrice, ':d' => $description, ':img' => $image, ':a' => $isActive, ':u' => $showInUpsell, ':s' => $sort]);
        flash('Товар добавлен');
    }
    header('Location: /admin/products.php');
    exit;
}

// Скрыть/показать
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = :i')->execute([':i' => $id]);
    header('Location: /admin/products.php');
    exit;
}

// Успеть сегодня / снять срочность
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'urgent') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE products SET is_urgent = 1 - is_urgent WHERE id = :i')->execute([':i' => $id]);
    header('Location: /admin/products.php');
    exit;
}

// Снять всё с продажи (массовое скрытие)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hide_all') {
    $pdo->exec('UPDATE products SET is_active = 0');
    flash('Все товары сняты с продажи');
    header('Location: /admin/products.php');
    exit;
}

// Пересортица: swap sort с ближайшим соседом по (sort, id) в направлении; без соседа — ±5
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move') {
    $id = (int)($_POST['id'] ?? 0);
    $dir = ($_POST['dir'] ?? '') === 'down' ? 'down' : 'up';
    $stmt = $pdo->prepare('SELECT id, sort FROM products WHERE id = :i');
    $stmt->execute([':i' => $id]);
    $cur = $stmt->fetch();
    if ($cur !== false) {
        $sort = (int)$cur['sort'];
        if ($dir === 'up') {
            $stmt = $pdo->prepare('SELECT id, sort FROM products WHERE (sort < :s) OR (sort = :s AND id < :i)
                ORDER BY sort DESC, id DESC LIMIT 1');
        } else {
            $stmt = $pdo->prepare('SELECT id, sort FROM products WHERE (sort > :s) OR (sort = :s AND id > :i)
                ORDER BY sort ASC, id ASC LIMIT 1');
        }
        $stmt->execute([':s' => $sort, ':i' => (int)$cur['id']]);
        $neighbour = $stmt->fetch();
        if ($neighbour !== false) {
            $pdo->prepare('UPDATE products SET sort = :s WHERE id = :i')
                ->execute([':s' => (int)$neighbour['sort'], ':i' => (int)$cur['id']]);
            $pdo->prepare('UPDATE products SET sort = :s WHERE id = :i')
                ->execute([':s' => $sort, ':i' => (int)$neighbour['id']]);
        } else {
            $newSort = $dir === 'up' ? $sort - 5 : $sort + 5;
            $pdo->prepare('UPDATE products SET sort = :s WHERE id = :i')->execute([':s' => $newSort, ':i' => $id]);
        }
    }
    header('Location: /admin/products.php');
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :i');
    $stmt->execute([':i' => $editId]);
    $editing = $stmt->fetch();
}
$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.sort, p.id')->fetchAll();
$categories = $pdo->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();

adminHeader('Товары', 'products');
flash();
?>
<h1>Товары</h1>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">
    <?= $editing ? 'Редактирование: ' . e($editing['name']) : 'Новый товар' ?>
  </h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
    <div class="grid2">
      <div>
        <label class="f" for="p-name">Название *</label>
        <input class="input" id="p-name" name="name" required value="<?= $editing ? e($editing['name']) : '' ?>">
        <label class="f" for="p-cat">Категория</label>
        <select id="p-cat" name="category_id">
          <option value="0">— Без категории —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $editing && (int)$editing['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="f" for="p-price">Цена, ₽ *</label>
        <input class="input" id="p-price" name="price" type="number" min="1" required value="<?= $editing ? (int)$editing['price'] : '' ?>">
        <label class="f" for="p-sale">Цена по акции, ₽ (пусто — без скидки)</label>
        <input class="input" id="p-sale" name="sale_price" type="number" min="0" value="<?= $editing && $editing['sale_price'] !== null ? (int)$editing['sale_price'] : '' ?>">
      </div>
      <div>
        <label class="f" for="p-desc">Описание</label>
        <textarea id="p-desc" name="description" rows="4"><?= $editing ? e($editing['description']) : '' ?></textarea>
        <label class="f" for="p-img">Фото <?= $editing && $editing['image'] !== '' ? '(заменить)' : '' ?></label>
        <input class="input" id="p-img" name="image" type="file" accept="image/*">
        <?php if ($editing && $editing['image'] !== ''): ?>
          <img class="thumb" style="margin-top:8px" src="/img/products/<?= e($editing['image']) ?>" alt="">
        <?php endif; ?>
        <label class="f" for="p-sort">Сортировка</label>
        <input class="input" id="p-sort" name="sort" type="number" value="<?= $editing ? (int)$editing['sort'] : 0 ?>">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:16px">
          <input type="checkbox" name="is_active" style="width:auto" <?= !$editing || (int)$editing['is_active'] === 1 ? 'checked' : '' ?>>
          Показывать в каталоге
        </label>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:8px">
          <input type="checkbox" name="show_in_upsell" style="width:auto" <?= $editing && (int)($editing['show_in_upsell'] ?? 0) === 1 ? 'checked' : '' ?>>
          Показывать в апсейле корзины
        </label>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px">
      <button class="btn btn--accent" type="submit"><?= $editing ? 'Сохранить' : 'Добавить товар' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="/admin/products.php">Отмена</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h2 style="font-family:var(--font-display);font-size:1.2rem">Все товары</h2>
    <form method="post" onsubmit="return confirm('Снять ВСЕ товары с продажи? Витрина станет пустой.')">
      <input type="hidden" name="action" value="hide_all">
      <button type="submit" class="danger" style="font-size:.8rem;padding:7px 14px;border-radius:8px;border:1px solid var(--err,#c0392b);color:#c0392b;background:#fff;cursor:pointer;font-family:inherit">Снять всё с продажи</button>
    </form>
  </div>
  <table>
    <tr><th>Фото</th><th>Название</th><th>Категория</th><th>Цена</th><th>Акция</th><th>Сорт.</th><th>Статус</th><th></th><th></th></tr>
    <?php foreach ($products as $p): ?>
    <tr>
      <td><?= $p['image'] !== '' ? '<img class="thumb" src="/img/products/' . e($p['image']) . '" alt="">' : '<div class="thumb"></div>' ?></td>
      <td><strong><?= e($p['name']) ?></strong><br><small style="color:var(--ink-soft)"><?= e($p['slug']) ?></small></td>
      <td><?= e($p['category_name'] ?? '—') ?></td>
      <td><?= formatPrice((int)$p['price']) ?></td>
      <td><?= $p['sale_price'] !== null ? formatPrice((int)$p['sale_price']) : '—' ?></td>
      <td><?= (int)$p['sort'] ?></td>
      <td><?= (int)$p['is_active'] === 1 ? 'Показан' : 'Скрыт' ?><?= (int)($p['show_in_upsell'] ?? 0) === 1 ? ' <span style="display:inline-block;background:var(--rose,#F4A9BE);color:#fff;border-radius:999px;padding:2px 8px;font-size:.68rem;font-weight:700;vertical-align:middle">Апсейл</span>' : '' ?></td>
      <td>
        <div class="row-actions">
          <a href="/admin/products.php?edit=<?= (int)$p['id'] ?>">Изменить</a>
          <form method="post" onsubmit="return confirm('Скрыть/показать товар?')">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit"><?= (int)$p['is_active'] === 1 ? 'Скрыть' : 'Показать' ?></button>
          </form>
          <form method="post">
            <input type="hidden" name="action" value="urgent"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit"><?= (int)($p['is_urgent'] ?? 0) === 1 ? 'Снять срочность' : 'Успеть сегодня' ?></button>
          </form>
          <form method="post" style="display:inline-flex;gap:4px">
            <input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" name="dir" value="up" title="Выше" aria-label="Выше">↑</button>
            <button type="submit" name="dir" value="down" title="Ниже" aria-label="Ниже">↓</button>
          </form>
          <form method="post" onsubmit="return confirm('Удалить товар безвозвратно?')">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="danger">Удалить</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php adminFooter(); ?>
