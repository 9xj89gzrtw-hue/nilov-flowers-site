<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

$pdo = db();
$flash_err = false;

// Смена статуса (кроме «Выполнен» — он через /admin/order.php с подтверждением вручения)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if ($id > 0 && array_key_exists($status, statuses())) {
        if ($status === 'done') {
            flash('Для статуса «Выполнен» заполните подтверждение вручения на странице заказа', true);
        } else {
            $pdo->prepare('UPDATE orders SET status = :s WHERE id = :i')
                ->execute([':s' => $status, ':i' => $id]);
            flash('Статус заказа обновлён');
        }
    }
    header('Location: /admin/index.php');
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
$orders = $stmt->fetchAll();

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
            <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="confirmed">
            <button type="submit">Подтвердить</button>
          </form>
          <form method="post" onsubmit="return confirm('Отменить заказ №<?= (int)$o['id'] ?>?')">
            <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="canceled">
            <button type="submit" class="danger">Отменить</button>
          </form>
          <?php elseif ($o['status'] === 'confirmed'): ?>
          <a href="/admin/order.php?id=<?= (int)$o['id'] ?>">Выполнен →</a>
          <?php elseif ($o['status'] === 'canceled'): ?>
          <form method="post">
            <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="new">
            <button type="submit">Вернуть в «Новые»</button>
          </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  </table>
</div>
<?php endforeach; endif; ?>
<?php adminFooter(); ?>
