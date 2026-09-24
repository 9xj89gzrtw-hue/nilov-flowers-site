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
    header('Location: /admin/products.php' . (trim((string)($_POST['back_qs'] ?? '')) !== '' ? '?' . trim((string)$_POST['back_qs']) : ''));
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
    /* W96 (5cv): бейджи карточек — «Хит продаж» и «Премиум» (секции витрины) */
    $isHit = isset($_POST['is_hit']) ? 1 : 0;
    $isPremium = isset($_POST['is_premium']) ? 1 : 0;
    $sort = (int)($_POST['sort'] ?? 0);

    if ($name === '' || $price <= 0) {
        flash('Укажите название и цену товара', true);
        header('Location: /admin/products.php?page=' . max(1, (int)($_POST['back_page'] ?? 1)) . (trim((string)($_POST['back_filters'] ?? '')) !== '' ? '&' . trim((string)$_POST['back_filters']) : ''));
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
                sale_price = :sp, description = :d, is_active = :a, show_in_upsell = :u,
                is_hit = :ih, is_premium = :ip, sort = :s,
                updated_at = datetime(\'now\',\'localtime\') WHERE id = :i')
            ->execute([':c' => $categoryId, ':n' => $name, ':sl' => slugify($name), ':p' => $price,
                ':sp' => $salePrice, ':d' => $description, ':a' => $isActive, ':u' => $showInUpsell,
                ':ih' => $isHit, ':ip' => $isPremium, ':s' => $sort, ':i' => $id]);
        flash('Товар обновлён');
    } else {
        $slug = slugify($name);
        $base = $slug;
        $n = 1;
        while ((int)$pdo->query('SELECT COUNT(*) FROM products WHERE slug = ' . $pdo->quote($slug))->fetchColumn() > 0) {
            $slug = $base . '-' . (++$n);
        }
        $pdo->prepare('INSERT INTO products (category_id, name, slug, price, sale_price, description, image, is_active, show_in_upsell, is_hit, is_premium, sort, updated_at)
                VALUES (:c, :n, :sl, :p, :sp, :d, :img, :a, :u, :ih, :ip, :s, datetime(\'now\',\'localtime\'))')
            ->execute([':c' => $categoryId, ':n' => $name, ':sl' => $slug, ':p' => $price,
                ':sp' => $salePrice, ':d' => $description, ':img' => $image, ':a' => $isActive, ':u' => $showInUpsell,
                ':ih' => $isHit, ':ip' => $isPremium, ':s' => $sort]);
        flash('Товар добавлен');
    }
    /* Операционный критик W38: после сохранения не «терять» товар — возврат на ту же
       страницу списка и якорь на строку (на 2-й странице товар не исчезает из вида). */
    $backPage = max(1, (int)($_POST['back_page'] ?? 1));
    $backId = (int)($_POST['id'] ?? 0);
    $bf = trim((string)($_POST['back_filters'] ?? ''));
    header('Location: /admin/products.php?page=' . $backPage . ($bf !== '' ? '&' . $bf : '') . ($backId ? '#row-' . $backId : ''));
    exit;
}

/* Операционный-критик W32 top#1: пакетное изменение цен — главный ежедневный сценарий
   (сезон, закупка подорожала). Выделенные чекбоксами товары: = N, +N, -N, ±X%. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_vis') {
    /* Массовое скрытие/показ выделенных (W32 missing_ops#1). Показ — с той же валидацией
       цены/фото, что и одиночный toggle. */
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $want = isset($_POST['set']) && (int)$_POST['set'] === 1 ? 1 : 0;
    $done = 0;
    $skipped = 0;
    foreach ($ids as $pid) {
        if ($want === 1) {
            $row = $pdo->prepare('SELECT price, image FROM products WHERE id = :i');
            $row->execute([':i' => $pid]);
            $pr = $row->fetch();
            if (!$pr || (int)$pr['price'] <= 0 || trim((string)$pr['image']) === '') { $skipped++; continue; }
        }
        $done += $pdo->prepare('UPDATE products SET is_active = :a, updated_at = datetime(\'now\',\'localtime\') WHERE id = :i')
            ->execute([':a' => $want, ':i' => $pid]) ? 1 : 0;
    }
    flash(($want ? "Показано: $done" : "Скрыто: $done") . ($skipped ? ", пропущено без цены/фото: $skipped" : ''));
    $__bq = trim((string)($_POST['back_qs'] ?? ''));
    header('Location: /admin/products.php' . ($__bq !== '' ? '?' . $__bq : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_price') {
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $mode = (string)($_POST['mode'] ?? '');
    $val = (int)($_POST['val'] ?? 0);
    if (!$ids || !in_array($mode, ['set', 'add', 'sub', 'pct'], true) || ($mode === 'pct' ? $val === 0 : $val <= 0) || abs($val) > 1000000) {
        flash('Выберите товары, режим и число', true);
    } else {
        $n = 0;
        foreach ($ids as $pid) {
            $row = $pdo->prepare('SELECT price FROM products WHERE id = :i');
            $row->execute([':i' => $pid]);
            $cur = (int)$row->fetchColumn();
            $new = $cur;
            if ($mode === 'set') { $new = $val; }
            if ($mode === 'add') { $new = $cur + $val; }
            if ($mode === 'sub') { $new = max(1, $cur - $val); }
            if ($mode === 'pct') { $new = max(1, (int)round($cur * (100 + $val) / 100)); } /* val может быть отрицательным через режим «уменьшить» */
            if ($new > 0 && $new !== $cur) {
                $pdo->prepare('UPDATE products SET price = :p, updated_at = datetime(\'now\',\'localtime\') WHERE id = :i')
                    ->execute([':p' => $new, ':i' => $pid]);
                $n++;
            }
        }
        flash("Цены обновлены: $n " . pluralRu($n, ["товар","товара","товаров"]));
    }
    /* W45 P2 (владелец): ±% не должен терять поиск/фильтр — как toggle в W44 */
    header('Location: /admin/products.php' . (trim((string)($_POST['back_qs'] ?? '')) !== '' ? '?' . trim((string)$_POST['back_qs']) : ''));
    exit;
}

// Скрыть/показать
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    /* Операционный-критик W32: «Показать» товара с ценой 0 или без фото — гарантия, что
       покупатель никогда не увидит битую карточку «0 ₽» с пустой картинкой. */
    $stmt = $pdo->prepare('SELECT is_active, price, sale_price, image FROM products WHERE id = :i');
    $stmt->execute([':i' => $id]);
    $pr = $stmt->fetch();
    if ($pr && (int)$pr['is_active'] === 0) { /* показываем */
        $hasPrice = (int)$pr['price'] > 0;
        $hasImg = trim((string)$pr['image']) !== '';
        if (!$hasPrice || !$hasImg) {
            flash('Нельзя показать: ' . (!$hasPrice ? 'не заполнена цена. ' : '') . (!$hasImg ? 'нет фото.' : ''), true);
            header('Location: /admin/products.php' . (trim((string)($_POST['back_qs'] ?? '')) !== '' ? '?' . trim((string)$_POST['back_qs']) : ''));
            exit;
        }
    }
    $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = :i')->execute([':i' => $id]);
    /* Владелец-критик W44: подтверждение + возврат с сохранением фильтра/поиска/страницы
       («Скрыл проданный — списокreset'нулся, товар потерялся» — страх потери). */
    flash($pr && (int)$pr['is_active'] === 1 ? 'Товар скрыт из продажи. Он никуда не делся: найдёте его в фильтре статуса «Скрыты» над списком.' : 'Товар показан в каталоге');
    $bq = trim((string)($_POST['back_qs'] ?? ''));
    header('Location: /admin/products.php' . ($bq !== '' ? '?' . $bq : '') . '#row-' . $id);
    exit;
}

// Успеть сегодня / снять срочность
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'urgent') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE products SET is_urgent = 1 - is_urgent WHERE id = :i')->execute([':i' => $id]);
    /* W63 (владелец OPEN#2): «Успеть» не должен терять поиск/фильтр + возвращаем к строке */
    header('Location: /admin/products.php' . (trim((string)($_POST['back_qs'] ?? '')) !== '' ? '?' . trim((string)$_POST['back_qs']) : '') . '#row-' . $id);
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
    /* W63 (владелец OPEN#2): ↑/↓ к строке вне видимой страницы = «товар пропал» */
    header('Location: /admin/products.php' . (trim((string)($_POST['back_qs'] ?? '')) !== '' ? '?' . trim((string)$_POST['back_qs']) : '') . '#row-' . $id);
    exit;
}

// Критик operational (ежедневная рутина владельца): пакетное добавление — несколько фото
// за одну операцию. Товары создаются скрытыми (is_active=0) с ценой 0 — владелец потом
// откроет каждый и допишет название/цену/описание. Имя файла → имя товара.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_import') {
    $files = $_FILES['bulk_images'] ?? [];
    $added = 0;
    /* Операционный-критик W38: молчаливый отказ на сбойном файле — худшее, что может быть
       при загрузке с телефона. Собираем пофайловые причины и показываем человеку. */
    $rejected = [];
    if (isset($files['name']) && is_array($files['name'])) {
        for ($bi = 0; $bi < count($files['name']); $bi++) {
            $one = [
                'name' => $files['name'][$bi], 'type' => $files['type'][$bi],
                'tmp_name' => $files['tmp_name'][$bi], 'error' => $files['error'][$bi],
                'size' => $files['size'][$bi],
            ];
            $imgName = saveUpload($one, IMG_PRODUCTS_DIR);
            if ($imgName === '') {
                $err = (int)$one['error'];
                if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { $why = 'файл слишком большой'; }
                elseif ($err !== UPLOAD_ERR_OK) { $why = 'ошибка загрузки (код ' . $err . ')'; }
                elseif ($one['size'] > 12 * 1024 * 1024) { $why = 'больше 12 МБ — сожмите фото'; }
                else { $why = 'не картинка jpg/png/webp/gif'; }
                $rejected[] = mb_substr((string)$one['name'], 0, 40) . ' — ' . $why;
                continue;
            }
            $base = trim(str_replace(array('_','-'), ' ', pathinfo((string)$files['name'][$bi], PATHINFO_FILENAME)));
            $name = $base !== '' ? mb_substr($base, 0, 80) : 'Новый букет';
            $slug = slugify($name);
            if ($slug === '') { $slug = 'bukets'; }
            $try = $slug; $n = 1;
            while ((int)$pdo->query('SELECT COUNT(*) FROM products WHERE slug = ' . $pdo->quote($try))->fetchColumn() > 0) {
                $try = $slug . '-' . (++$n);
            }
            $pdo->prepare('INSERT INTO products (category_id, name, slug, price, sale_price, description, image, is_active, show_in_upsell, sort, updated_at)
                    VALUES (NULL, :n, :sl, 0, NULL, :d, :img, 0, 0, :s, datetime(\'now\',\'localtime\'))')
                ->execute([':n' => $name, ':sl' => $try, ':d' => '', ':img' => $imgName, ':s' => 999]);
            $added++;
        }
    }
    flash($added > 0 ? "Добавлено товаров: $added (скрытые, с ценой 0 — заполните карточку каждого)" . ($rejected ? '; отклонено: ' . implode('; ', array_slice($rejected, 0, 4)) . (count($rejected) > 4 ? '…' : '') : '')
                      : 'Ни одно фото не принято. ' . ($rejected ? 'Причины: ' . implode('; ', array_slice($rejected, 0, 4)) . (count($rejected) > 4 ? ' и др.' : '') : 'Формат: jpg/png/webp/gif, до 12 МБ'), $added === 0);
    /* W66 (статич. выверка #5): импорт из-под фильтра «Без фото» сбрасывал список — носим back_qs */
    header('Location: /admin/products.php' . (trim((string)($_POST['back_qs'] ?? '')) !== '' ? '?' . trim((string)$_POST['back_qs']) : ''));
    exit;
}


$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :i');
    $stmt->execute([':i' => $editId]);
    $editing = $stmt->fetch();
}
/* Операционный-критик W32: фильтры/поиск — при 200 SKU искать глазами невозможно. */
$fCat = (int)($_GET['f_cat'] ?? 0);
$fSt = (string)($_GET['f_status'] ?? '');
$fQ = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$allProducts = $pdo->query('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.sort, p.id')->fetchAll();
$allProducts = array_values(array_filter($allProducts, function ($p) use ($fCat, $fSt, $fQ) {
    if ($fCat > 0 && (int)$p['category_id'] !== $fCat) { return false; }
    if ($fSt === 'active' && (int)$p['is_active'] !== 1) { return false; }
    if ($fSt === 'hidden' && (int)$p['is_active'] !== 0) { return false; }
    if ($fSt === 'no_price' && (int)$p['price'] > 0) { return false; }
    if ($fSt === 'no_image' && trim((string)$p['image']) !== '') { return false; }
    if ($fSt === 'stale' && $p['updated_at'] !== '' && strtotime((string)$p['updated_at']) > time() - 7 * 86400) { return false; }
    if ($fQ !== '' && mb_strpos(mb_strtolower($p['name'] . ' ' . $p['slug']), $fQ) === false) { return false; }
    return true;
}));

/* Пагинация: 25 товаров на страницу */
$perPage = 25;
$totalProducts = count($allProducts);
$pages = max(1, (int)ceil($totalProducts / $perPage));
$page = (int)($_GET['page'] ?? 1);
if ($page < 1 || $page > $pages) {
    $page = 1;
}
$products = array_slice($allProducts, ($page - 1) * $perPage, $perPage);
/* W45-финал (владелец T3): контекст фильтра для «Изменить»/«Отмена»/возвратов */
$ctxQ = ($__ctx = http_build_query(array_filter(['q' => ($fQ ?: null), 'f_cat' => ($fCat ?: null), 'f_status' => ($fSt ?: null), 'page' => ($page > 1 ? $page : null)]))) !== '' ? '&' . $__ctx : '';
$categories = $pdo->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();

/* Операционный-критик W32: напоминания владельцу — черновики/устаревшие одним взглядом */
$attnDrafts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE price <= 0')->fetchColumn();
$attnNoImg = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE image = ''")->fetchColumn();
$attnStale = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND updated_at != '' AND updated_at < datetime('now','localtime','-14 day')")->fetchColumn();

adminHeader('Товары', 'products');
flash();
?>
<h1>Товары</h1>
<?php if ($attnDrafts + $attnNoImg + $attnStale > 0): ?>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 14px;font-size:.85rem">
  <?php if ($attnDrafts): ?><a href="/admin/products.php?f_status=no_price" style="background:#fff7e0;border:1px solid #e8d48a;border-radius:999px;padding:6px 14px;text-decoration:none;color:var(--ink)">📝 Черновиков без цены: <?= $attnDrafts ?> →</a><?php endif; ?>
  <?php if ($attnNoImg): ?><a href="/admin/products.php?f_status=no_image" style="background:#fdecec;border:1px solid #eab5b5;border-radius:999px;padding:6px 14px;text-decoration:none;color:var(--ink)">🖼 Без фото: <?= $attnNoImg ?> →</a><?php endif; ?>
  <?php if ($attnStale): ?><a href="/admin/products.php?f_status=stale" style="background:#eef3fb;border:1px solid #b9c9e4;border-radius:999px;padding:6px 14px;text-decoration:none;color:var(--ink)">⏳ Не обновлялись 2+ недели: <?= $attnStale ?> →</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">
    <?= $editing ? 'Редактирование: ' . e($editing['name']) : 'Новый товар' ?>
  </h2>
  <form method="post" enctype="multipart/form-data" style="padding-bottom:96px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
    <input type="hidden" name="back_page" value="<?= $page ?>">
    <input type="hidden" name="back_filters" value="<?= e(http_build_query(array_filter(['q' => ($fQ ?: null), 'f_cat' => ($fCat ?: null), 'f_status' => ($fSt ?: null)]))) ?>">
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
          <?php if (!$editing): ?><p style="font-size:.8rem;color:var(--ink-soft);margin:6px 4px 0 4px">Новый товар создастся скрытым — проверьте фото и текст, потом нажмите «Показать» в списке.</p><?php endif; ?>
          Показывать в каталоге
        </label>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:8px">
          <input type="checkbox" name="show_in_upsell" style="width:auto" <?= $editing && (int)($editing['show_in_upsell'] ?? 0) === 1 ? 'checked' : '' ?>>
          Добавлять в блок «Добавьте к букету» в корзине
        </label>
        <?php /* W96 (5cv): бейджи на карточках + секции «Хиты продаж» / «Премиум» на главной */ ?>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:8px">
          <input type="checkbox" name="is_hit" style="width:auto" <?= $editing && (int)($editing['is_hit'] ?? 0) === 1 ? 'checked' : '' ?>>
          Хит продаж (жёлтый бейдж + секция «Хиты продаж» на главной)
        </label>
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:8px">
          <input type="checkbox" name="is_premium" style="width:auto" <?= $editing && (int)($editing['is_premium'] ?? 0) === 1 ? 'checked' : '' ?>>
          Премиум (тёмный бейдж + секция «Премиум» на главной)
        </label>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px;position:sticky;bottom:12px;z-index:25;background:rgba(255,255,255,.97);backdrop-filter:blur(8px);padding:10px 12px;border:1px solid var(--line);border-radius:12px;box-shadow:0 10px 30px -18px rgba(43,45,47,.5)">
      <button class="btn btn--accent" type="submit" style="padding:10px 22px;min-height:44px"><?= $editing ? 'Сохранить' : 'Добавить товар' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="/admin/products.php?edit=0<?= e($ctxQ) ?>">Отмена</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Добавить сразу несколько фото</h2>
  <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:10px">Выберите несколько фотографий букетов (или перетащите сюда). Каждый файл станет отдельным скрытым товаром с названием по имени файла — потом откроете и заполните цену/описание.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_import"><input type="hidden" name="back_qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
    <label class="f" for="p-bulk">Фото (можно несколько)</label>
    <input class="input" id="p-bulk" name="bulk_images[]" type="file" accept="image/*" multiple>
    <div style="margin-top:12px"><button class="btn btn--accent" type="submit">Создать товары из фото</button></div>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h2 style="font-family:var(--font-display);font-size:1.2rem">Все товары <small style="font-weight:400;color:var(--ink-soft)">(<?= count($allProducts) ?>)</small></h2>
    <form method="get" class="filters-bar">
      <input class="input" type="search" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="Поиск по названию…" style="width:170px">
      <select name="f_cat" class="input">
        <option value="0">Все категории</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $fCat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <select name="f_status" class="input">
        <?php $stMap = ['' => 'Все статусы', 'active' => 'Показаны', 'hidden' => 'Скрыты', 'no_price' => 'Без цены', 'no_image' => 'Без фото', 'stale' => 'Не менялись 7+ дней']; ?>
        <?php foreach ($stMap as $k => $lbl): ?><option value="<?= $k ?>" <?= $fSt === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn--ghost">Найти</button>
      <?php if ($fCat || $fSt || $fQ): ?><a class="btn btn--ghost" href="/admin/products.php">Сбросить</a><?php endif; ?>
    </form>
  </div>
  <?php /* Пакетные цены (W32 top#1): выделить чекбоксами → = / + / − / % одним нажатием.
     Отправляет выделенные ids на action=bulk_price (обработчик выше, с CSRF). */ ?>
  <form method="post" id="bulkPriceForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0;padding:10px 12px;background:var(--mint,#D9E9DF);border-radius:10px;font-size:.85rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_price">
    <input type="hidden" name="back_qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
    <span style="font-weight:600">Цены выделенным:</span>
    <select name="mode" class="input" style="width:auto">
      <option value="set">сделать равной</option><option value="add">повысить на</option><option value="sub">понизить на</option><option value="pct">изменить на %</option>
    </select>
    <input class="input" type="number" name="val" min="-90" max="1000000" placeholder="500 или -10" title="Минус со знаком − снизит цены" style="width:150px" required>
    <button type="submit" class="btn btn--accent" onclick="return confirm('Применить к выделенным товарам?')">Применить</button>
    <a href="#" id="bulkSelAll" style="font-size:.8rem">выделить все на странице</a>
    <span style="flex-basis:100%;height:0"></span>
    <button class="btn btn--ghost" style="font-size:.8rem;padding:6px 14px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer" type="button" id="bulkHideBtn">Скрыть выделенные</button>
    <button class="btn btn--ghost" style="font-size:.8rem;padding:6px 14px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer" type="button" id="bulkShowBtn">Показать выделенные</button>
  </form>
  <div class="table-scroll"><table>
    <tr><th></th><th>Фото</th><th>Название</th><th>Категория</th><th>Цена</th><th>Акция</th><th>Сорт.</th><th>Статус</th><th>Обновлён</th><th></th><th></th></tr>
    <?php foreach ($products as $p): ?>
    <tr id="row-<?= (int)$p['id'] ?>">
      <td style="text-align:center"><label class="bulk-hit"><input class="bulk-check" type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" form="bulkPriceForm"></label></td>
      <td><?= $p['image'] !== '' ? '<img class="thumb" src="/img/products/' . e($p['image']) . '" alt="">' : '<div class="thumb"></div>' ?></td>
      <td><strong><?= e($p['name']) ?></strong><?= (int)($p['is_hit'] ?? 0) === 1 ? ' <span style="display:inline-block;background:#f5b301;color:#1c1a1e;border-radius:999px;padding:2px 8px;font-size:.68rem;font-weight:700;vertical-align:middle">Хит</span>' : '' ?><?= (int)($p['is_premium'] ?? 0) === 1 ? ' <span style="display:inline-block;background:#1c1a1e;color:#fff;border-radius:999px;padding:2px 8px;font-size:.68rem;font-weight:700;vertical-align:middle">Премиум</span>' : '' ?><br><small style="color:var(--ink-soft)"><?= e($p['slug']) ?></small></td>
      <td><?= e($p['category_name'] ?? '—') ?></td>
      <td><?= formatPrice((int)$p['price']) ?></td>
      <td><?= $p['sale_price'] !== null ? formatPrice((int)$p['sale_price']) : '—' ?></td>
      <td><?= (int)$p['sort'] ?></td>
      <td><?= (int)$p['is_active'] === 1 ? 'Показан' : 'Скрыт' ?><?= (int)($p['show_in_upsell'] ?? 0) === 1 ? ' <span style="display:inline-block;background:var(--rose-cta,#AE4A71);color:#fff;border-radius:999px;padding:2px 8px;font-size:.68rem;font-weight:700;vertical-align:middle">К корзине</span>' : '' ?></td>
      <?php /* Операционный-критик W32: видна свежесть карточки (обновления/черновики) */ ?>
      <td><small style="color:var(--ink-soft)"><?= $p['updated_at'] !== '' ? e(date('d.m', strtotime((string)$p['updated_at']))) : '—' ?></small></td>
      <td>
        <div class="row-actions">
          <a href="/admin/products.php?edit=<?= (int)$p['id'] ?><?= $ctxQ ?>">Изменить</a>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back_qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
            <button type="submit"><?= (int)$p['is_active'] === 1 ? 'Скрыть' : 'Показать' ?></button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="urgent"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back_qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
            <button type="submit" class="<?= (int)($p['is_urgent'] ?? 0) === 1 ? 'primary-action' : '' ?>"><?= (int)($p['is_urgent'] ?? 0) === 1 ? '★ Срочно' : 'Успеть сегодня' ?></button>
          </form>
          <form method="post" style="display:inline-flex;gap:4px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back_qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
            <button type="submit" name="dir" value="up" title="Выше" aria-label="Выше" style="min-width:44px">↑</button>
            <button type="submit" name="dir" value="down" title="Ниже" aria-label="Ниже" style="min-width:44px">↓</button>
          </form>
          <form method="post" onsubmit="return confirm('Удалить товар безвозвратно?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back_qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
            <button type="submit" class="danger">Удалить</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if ($pages > 1): ?>
  <div style="text-align:center;margin-top:14px">
    <?php for ($p = 1; $p <= $pages; $p++):
   $qs = array_filter(['page' => ($p > 1 ? $p : null), 'f_cat' => ($fCat ?: null), 'f_status' => ($fSt ?: null), 'q' => ($fQ ?: null)]);
   $href = '/admin/products.php' . ($qs ? '?' . http_build_query($qs) : ''); ?>
      <?php if ($p === $page): ?>
        <strong style="margin:0 6px"><?= $p ?></strong>
      <?php else: ?>
        <a style="margin:0 6px" href="<?= e($href) ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <small style="display:block;color:var(--ink-soft)"><?= $totalProducts ?> <?= e(pluralRu($totalProducts, ["товар","товара","товаров"])) ?>, страница <?= $page ?> из <?= $pages ?></small>
  </div>
  <?php endif; ?>
  <details style="margin-top:14px">
    <summary style="cursor:pointer;color:var(--ink-soft);font-size:.85rem">Опасная зона: снять ВСЕ товары с продажи</summary>
    <form method="post" onsubmit="return confirm('Снять ВСЕ товары с продажи? Витрина станет пустой. Товары можно вернуть по одному кнопкой «Показать».')" style="margin-top:8px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="hide_all">
      <button type="submit" class="btn btn--danger" style="font-size:.8rem;padding:7px 14px">Снять всё с продажи</button>
      <small style="display:block;margin-top:6px;color:var(--ink-soft)">Витрина станет пустой. Вернуть можно кнопкой «Показать» у каждого товара.</small>
    </form>
  </details>
</div>
<script>
/* W32: «выделить все на странице» для пакетных цен */
document.getElementById('bulkSelAll')?.addEventListener('click', function (ev) {
  ev.preventDefault();
  var boxes = document.querySelectorAll('input[name="ids[]"]');
  var allOn = Array.prototype.every.call(boxes, function (b) { return b.checked; });
  boxes.forEach(function (b) { b.checked = !allOn; });
});
/* W32 missing_ops: массовое скрытие/показ выделенных — отдельный POST bulk_vis
   (action главной формы — bulk_price, поэтому кнопками-сабмитами нельзя). */
(function () {
  var csrf = '';
  document.querySelectorAll('#bulkPriceForm input[name="csrf_token"]').forEach(function (i) { csrf = i.value; });
  function send(set) {
    var ids = Array.prototype.slice.call(document.querySelectorAll('input[name="ids[]"]:checked')).map(function (b) { return b.value; });
    if (!ids.length) { (window.adminToast||function(m){alert(m);})('Сначала отметьте товары галочками слева.'); return; }
    if (!confirm((set ? 'Показать' : 'Скрыть') + ' выбранные (' + ids.length + ' шт.) на витрине?')) return;
    var f = document.createElement('form');
    f.method = 'post';
    f.action = '/admin/products.php';
    function inp(n, v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); }
    inp('action', 'bulk_vis'); inp('set', String(set)); inp('csrf_token', csrf); inp('back_qs', new URLSearchParams(window.location.search).toString());
    ids.forEach(function (id) { inp('ids[]', id); });
    document.body.appendChild(f);
    f.submit();
  }
  document.getElementById('bulkHideBtn')?.addEventListener('click', function () { send(0); });
  document.getElementById('bulkShowBtn')?.addEventListener('click', function () { send(1); });
})();
</script>
<?php adminFooter(); ?>
