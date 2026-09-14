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

// Критик operational (ежедневная рутина владельца): пакетное добавление — несколько фото
// за одну операцию. Товары создаются скрытыми (is_active=0) с ценой 0 — владелец потом
// откроет каждый и допишет название/цену/описание. Имя файла → имя товара.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_import') {
    $files = $_FILES['bulk_images'] ?? [];
    $added = 0;
    if (isset($files['name']) && is_array($files['name'])) {
        for ($bi = 0; $bi < count($files['name']); $bi++) {
            $one = [
                'name' => $files['name'][$bi], 'type' => $files['type'][$bi],
                'tmp_name' => $files['tmp_name'][$bi], 'error' => $files['error'][$bi],
                'size' => $files['size'][$bi],
            ];
            $imgName = saveUpload($one, IMG_PRODUCTS_DIR);
            if ($imgName === '') { continue; }
            $base = trim(pathinfo((string)$files['name'][$bi], PATHINFO_FILENAME));
            $name = $base !== '' ? mb_substr($base, 0, 80) : 'Новый букет';
            $slug = slugify($name);
            if ($slug === '') { $slug = 'bukets'; }
            $try = $slug; $n = 1;
            while ((int)$pdo->query('SELECT COUNT(*) FROM products WHERE slug = ' . $pdo->quote($try))->fetchColumn() > 0) {
                $try = $slug . '-' . (++$n);
            }
            $pdo->prepare('INSERT INTO products (category_id, name, slug, price, sale_price, description, image, is_active, show_in_upsell, sort)
                    VALUES (NULL, :n, :sl, 0, NULL, :d, :img, 0, 0, :s)')
                ->execute([':n' => $name, ':sl' => $try, ':d' => '', ':img' => $imgName, ':s' => 999]);
            $added++;
        }
    }
    flash($added > 0 ? "Добавлено товаров: $added (скрытые, с ценой 0 — заполните карточку каждого)"
                      : 'Ни одно фото не принято (проверьте формат: jpg/png/webp, до 5 МБ)', $added === 0);
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
$allProducts = $pdo->query('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.sort, p.id')->fetchAll();

/* Пагинация: 25 товаров на страницу */
$perPage = 25;
$totalProducts = count($allProducts);
$pages = max(1, (int)ceil($totalProducts / $perPage));
$page = (int)($_GET['page'] ?? 1);
if ($page < 1 || $page > $pages) {
    $page = 1;
}
$products = array_slice($allProducts, ($page - 1) * $perPage, $perPage);
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
    <?= csrf_field() ?>
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
        <label class="f" for="p-sort">Позиция в каталоге (1 — первым)</label>
        <input class="input" id="p-sort" name="sort" type="number" value="<?= $editing ? (int)$editing['sort'] : 0 ?>">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:16px">
          <input type="checkbox" name="is_active" style="width:auto" <?= $editing ? ((int)$editing['is_active'] === 1 ? 'checked' : '') : '' ?>>
          <?php /* Новый товар = черновик (не показан): владелец проверит фото/текст и сам нажмёт «Показать».
                 Критик-владелец: «товар сразу продаётся до проверки — страшно». */ ?>
          <?php if (!$editing): ?><p style="font-size:.8rem;color:var(--ink-soft);margin-top:4px">Новый товар создастся скрытым — проверьте фото и текст, потом нажмите «Показать» в списке.</p><?php endif; ?>
          Показывать в каталоге
        </label>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:8px">
          <input type="checkbox" name="show_in_upsell" style="width:auto" <?= $editing && (int)($editing['show_in_upsell'] ?? 0) === 1 ? 'checked' : '' ?>>
          Добавлять в блок «Добавьте к букету» в корзине
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
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Добавить сразу несколько фото</h2>
  <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:10px">Выберите несколько фотографий букетов (или перетащите сюда). Каждый файл станет отдельным скрытым товаром с названием по имени файла — потом откроете и заполните цену/описание.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_import">
    <label class="f" for="p-bulk">Фото (можно несколько)</label>
    <input class="input" id="p-bulk" name="bulk_images[]" type="file" accept="image/*" multiple>
    <div style="margin-top:12px"><button class="btn btn--accent" type="submit">Создать товары из фото</button></div>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h2 style="font-family:var(--font-display);font-size:1.2rem">Все товары</h2>
  </div>
  <div class="table-scroll"><table>
    <tr><th>Фото</th><th>Название</th><th>Категория</th><th>Цена</th><th>Акция</th><th>Сорт.</th><th>Статус</th><th></th><th></th></tr>
    <?php foreach ($products as $p): ?>
    <tr>
      <td><?= $p['image'] !== '' ? '<img class="thumb" src="/img/products/' . e($p['image']) . '" alt="">' : '<div class="thumb"></div>' ?></td>
      <td><strong><?= e($p['name']) ?></strong><br><small style="color:var(--ink-soft)"><?= e($p['slug']) ?></small></td>
      <td><?= e($p['category_name'] ?? '—') ?></td>
      <td><?= formatPrice((int)$p['price']) ?></td>
      <td><?= $p['sale_price'] !== null ? formatPrice((int)$p['sale_price']) : '—' ?></td>
      <td><?= (int)$p['sort'] ?></td>
      <td><?= (int)$p['is_active'] === 1 ? 'Показан' : 'Скрыт' ?><?= (int)($p['show_in_upsell'] ?? 0) === 1 ? ' <span style="display:inline-block;background:var(--rose,#F4A9BE);color:#fff;border-radius:999px;padding:2px 8px;font-size:.68rem;font-weight:700;vertical-align:middle">К корзине</span>' : '' ?></td>
      <td>
        <div class="row-actions">
          <a href="/admin/products.php?edit=<?= (int)$p['id'] ?>">Изменить</a>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit"><?= (int)$p['is_active'] === 1 ? 'Скрыть' : 'Показать' ?></button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="urgent"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit"<?= (int)($p['is_urgent'] ?? 0) === 1 ? ' style="background:var(--rose-deep,#E2799C);color:#fff;border-color:var(--rose-deep,#E2799C);font-weight:700"' : '' ?>><?= (int)($p['is_urgent'] ?? 0) === 1 ? '★ Успеть сегодня — включено' : 'Успеть сегодня' ?></button>
          </form>
          <form method="post" style="display:inline-flex;gap:4px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" name="dir" value="up" title="Выше" aria-label="Выше">↑</button>
            <button type="submit" name="dir" value="down" title="Ниже" aria-label="Ниже">↓</button>
          </form>
          <form method="post" onsubmit="return confirm('Удалить товар безвозвратно?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="danger">Удалить</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if ($pages > 1): ?>
  <div style="text-align:center;margin-top:14px">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <?php if ($p === $page): ?>
        <strong style="margin:0 6px"><?= $p ?></strong>
      <?php else: ?>
        <a style="margin:0 6px" href="/admin/products.php<?= $p > 1 ? '?page=' . $p : '' ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <small style="display:block;color:var(--ink-soft)"><?= $totalProducts ?> товар(ов), страница <?= $page ?> из <?= $pages ?></small>
  </div>
  <?php endif; ?>
  <details style="margin-top:14px">
    <summary style="cursor:pointer;color:var(--ink-soft);font-size:.85rem">Опасная зона: снять ВСЕ товары с продажи</summary>
    <form method="post" onsubmit="return confirm('Снять ВСЕ товары с продажи? Витрина станет пустой. Товары можно вернуть по одному кнопкой «Показать».')" style="margin-top:8px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="hide_all">
      <button type="submit" class="danger" style="font-size:.8rem;padding:7px 14px;border-radius:8px;border:1px solid var(--err,#c0392b);color:#c0392b;background:#fff;cursor:pointer;font-family:inherit">Снять всё с продажи</button>
      <small style="display:block;margin-top:6px;color:var(--ink-soft)">Витрина станет пустой. Вернуть можно кнопкой «Показать» у каждого товара.</small>
    </form>
  </details>
</div>
<?php adminFooter(); ?>
