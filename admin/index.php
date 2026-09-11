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
$flash_err = false;

// Смена статуса (кроме «Выполнен» — он через /admin/order.php с подтверждением вручения).
// Разрешённые переходы: new→confirmed/canceled, confirmed→canceled/unredeemed,
// canceled/unredeemed→new. done — финальный, через вручение.
$allowedTransitions = [
    'new' => ['confirmed', 'canceled'],
    'confirmed' => ['canceled', 'unredeemed'],
    'canceled' => ['new'],
    'unredeemed' => ['new'],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $current = '';
    if ($id > 0) {
        $st = $pdo->prepare('SELECT status FROM orders WHERE id = :i');
        $st->execute([':i' => $id]);
        $current = (string)($st->fetchColumn() ?: '');
    }
    if ($status === 'done') {
        flash('Для статуса «Выполнен» заполните подтверждение вручения на странице заказа', true);
    } elseif ($current !== '' && in_array($status, $allowedTransitions[$current] ?? [], true)) {
        $pdo->prepare('UPDATE orders SET status = :s WHERE id = :i')
            ->execute([':s' => $status, ':i' => $id]);
        flash('Статус заказа обновлён');
    } else {
        flash('Недопустимый переход статуса', true);
    }
    header('Location: /admin/index.php' . (isset($_POST['page']) && (int)$_POST['page'] > 1 ? '?page=' . (int)$_POST['page'] : ''));
    exit;
}

$statusFilter = (string)($_GET['status'] ?? '');
if ($statusFilter !== '' && !array_key_exists($statusFilter, statuses())) {
    $statusFilter = '';
}
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$sql = 'SELECT o.*, z.name AS zone_name FROM orders o
        LEFT JOIN delivery_zones z ON z.id = o.delivery_zone_id WHERE 1=1';
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND o.status = :s';
    $params[':s'] = $statusFilter;
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $sql .= ' AND o.created_at >= :df';
    $params[':df'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $sql .= ' AND o.created_at <= :dt';
    $params[':dt'] = $dateTo . ' 23:59:59';
}
$sql .= ' ORDER BY o.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allIds = array_column($stmt->fetchAll(), 'id');

// Пагинация: 30 заказов на страницу
$perPage = 30;
$totalOrders = count($allIds);
$pages = max(1, (int)ceil($totalOrders / $perPage));
$page = (int)($_GET['page'] ?? 1);
if ($page < 1 || $page > $pages) {
    $page = 1;
}
$orders = [];
if ($allIds !== []) {
    $pageIds = array_slice($allIds, ($page - 1) * $perPage, $perPage);
    $placeholders = implode(',', array_fill(0, count($pageIds), '?'));
    $orders = $pdo->prepare('SELECT o.*, z.name AS zone_name FROM orders o
        LEFT JOIN delivery_zones z ON z.id = o.delivery_zone_id WHERE o.id IN (' . $placeholders . ')
        ORDER BY o.id DESC');
    $orders->execute($pageIds);
    $orders = $orders->fetchAll();
}
$qs = array_filter($_GET, fn($v, $k) => $v !== '' && $k !== 'page', ARRAY_FILTER_USE_BOTH);

$itemsStmt = $pdo->prepare('SELECT name, price, qty FROM order_items WHERE order_id = :i');

adminHeader('Заказы', 'index');
flash();
?>
<h1>Заказы</h1>

<div class="card">
  <form method="get" class="filters-bar">
    <div>
      <label class="f" for="f-from">С даты</label>
      <input class="input" id="f-from" type="date" name="date_from" value="<?= e($dateFrom) ?>" style="width:auto">
    </div>
    <div>
      <label class="f" for="f-to">По дату</label>
      <input class="input" id="f-to" type="date" name="date_to" value="<?= e($dateTo) ?>" style="width:auto">
    </div>
    <div>
      <label class="f" for="f-status">Статус</label>
      <select id="f-status" name="status" style="width:auto">
        <option value="">Все статусы</option>
        <?php foreach (statuses() as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">Показать</button>
    <?php if ($statusFilter !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
      <a class="btn btn--ghost" href="/admin/index.php">Сбросить</a>
    <?php endif; ?>
  </form>
  <a class="btn btn--accent" href="/admin/export.php?<?= e(http_build_query(array_filter($_GET, fn($v) => $v !== ''))) ?>">Экспорт в CSV</a>
</div>

<?php if (!$orders): ?>
<div class="card">Заказов пока нет.</div>
<?php else: foreach ($orders as $o): ?>
<div class="card">
  <table>
    <tr>
      <td style="width:90px"><strong><a class="order-link" href="/admin/order.php?id=<?= (int)$o['id'] ?>">№ <?= (int)$o['id'] ?></a></strong><br><small style="color:var(--ink-soft)"><?= e($o['created_at']) ?></small></td>
      <td>
        <strong><?= e($o['customer_name']) ?></strong><br>
        <?= $o['phone'] !== '' ? '<a href="tel:' . e(preg_replace('/\D/', '', $o['phone'])) . '">' . e($o['phone']) . '</a>' : '' ?><?= $o['email'] !== '' ? ' · ' . e($o['email']) : '' ?><br>
        <small style="color:var(--ink-soft)">
          <?= $o['zone_name'] !== null ? 'Доставка: ' . e($o['zone_name']) . ' — ' : 'Самовывоз — ' ?>
          <?= $o['delivery_address'] !== '' ? e($o['delivery_address']) : 'самовывоз' ?>
          · Оплата: <?= $o['payment_method'] === 'online' ? 'онлайн' : 'при получении' ?>
        </small>
        <?php if ($o['comment'] !== ''): ?><br><small>Комментарий: <?= e($o['comment']) ?></small><?php endif; ?>
      </td>
      <td style="width:240px">
        <?php
        $itemsStmt->execute([':i' => $o['id']]);
        foreach ($itemsStmt->fetchAll() as $it) {
            echo e($it['name']) . ' × ' . (int)$it['qty'] . ' — ' . formatPrice((int)$it['price']) . '<br>';
        }
        ?>
      </td>
      <td style="width:110px"><strong><?= formatPrice((int)$o['total']) ?></strong></td>
      <td style="width:190px">
        <span class="status-badge <?= e($o['status']) ?>"><?= e(statuses()[$o['status']] ?? $o['status']) ?></span>
        <div class="row-actions" style="margin-top:8px">
          <a href="/admin/order.php?id=<?= (int)$o['id'] ?>">Открыть</a>
          <?php if ($o['status'] === 'new'): ?>
          <form method="post" onsubmit="return confirm('Подтвердить заказ №<?= (int)$o['id'] ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="confirmed"><input type="hidden" name="page" value="<?= $page ?>">
            <button type="submit">Подтвердить</button>
          </form>
          <form method="post" onsubmit="return confirm('Отменить заказ №<?= (int)$o['id'] ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="canceled"><input type="hidden" name="page" value="<?= $page ?>">
            <button type="submit" class="danger">Отменить</button>
          </form>
          <?php elseif ($o['status'] === 'confirmed'): ?>
          <a href="/admin/order.php?id=<?= (int)$o['id'] ?>">Выполнен →</a>
          <form method="post" onsubmit="return confirm('Отметить заказ №<?= (int)$o['id'] ?> как «Не выкуплен»?')">
            <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="unredeemed"><input type="hidden" name="page" value="<?= $page ?>">
            <button type="submit" class="danger">Не выкуплен</button>
          </form>
          <?php elseif ($o['status'] === 'canceled' || $o['status'] === 'unredeemed'): ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="new"><input type="hidden" name="page" value="<?= $page ?>">
            <button type="submit">Вернуть в «Новые»</button>
          </form>
          <?php endif; ?>
          <?php if (($o['receipt_prepay'] ?? '') === 'failed' || ($o['receipt_offset'] ?? '') === 'failed'): ?>
          <a href="/admin/order.php?id=<?= (int)$o['id'] ?>&retry=1">Пробить чек зачёта</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  </table>
</div>
<?php endforeach; endif; ?>

<?php if ($pages > 1): ?>
<div class="card" style="text-align:center">
  <?php for ($p = 1; $p <= $pages; $p++): ?>
    <?php if ($p === $page): ?>
      <strong style="margin:0 6px"><?= $p ?></strong>
    <?php else: ?>
      <a style="margin:0 6px" href="/admin/index.php?<?= e(http_build_query(array_merge($qs, ['page' => $p]))) ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <small style="display:block;color:var(--ink-soft)"><?= $totalOrders ?> заказ(ов), страница <?= $page ?> из <?= $pages ?></small>
</div>
<?php endif; ?>
<?php adminFooter(); ?>
