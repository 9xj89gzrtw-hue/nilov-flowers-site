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

/* ---------- Статистика (дашборд) ---------- */
$range = (string)($_GET['range'] ?? '30');
if (!in_array($range, ['1', '7', '30', '90', 'all'], true)) {
    $range = '30';
}
$revenueStatuses = "('confirmed','done','unredeemed')";
/* Операционный критик W38: «как сегодня?» — главный вопрос флориста; range=1 = с полуночи. */
if ($range === '1') {
    $rangeCond = " AND date(o.created_at) = date('now','localtime')";
} else {
    $rangeCond = $range === 'all' ? '' : " AND o.created_at >= datetime('now','localtime','-" . (int)$range . " days')";
}

$revRow = $pdo->query("SELECT COALESCE(SUM(o.total),0), COUNT(*) FROM orders o
    WHERE o.status IN $revenueStatuses$rangeCond")->fetch(PDO::FETCH_NUM);
$revenue = (int)$revRow[0];
$paidCount = (int)$revRow[1];
$avgCheck = $paidCount > 0 ? (int)round($revenue / $paidCount) : 0;
$newCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o WHERE o.status = 'new'$rangeCond")->fetchColumn();
$allCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o WHERE 1=1$rangeCond")->fetchColumn();
/* W38: в режиме «Сегодня» — вчерашний результат для сравнения («больше или меньше обычного?») */
$yRev = 0; $yCount = 0;
if ($range === '1') {
    $yr = $pdo->query("SELECT COALESCE(SUM(o.total),0), COUNT(*) FROM orders o WHERE o.status IN $revenueStatuses AND date(o.created_at) = date('now','localtime','-1 day')")->fetch(PDO::FETCH_NUM);
    $yRev = (int)$yr[0]; $yCount = (int)$yr[1];
}

$statusBreak = $pdo->query("SELECT status, COUNT(*) AS c FROM orders o WHERE 1=1$rangeCond GROUP BY status")->fetchAll();
$statusCounts = [];
foreach ($statusBreak as $sb) {
    $statusCounts[$sb['status']] = (int)$sb['c'];
}

/* Sparkline: выручка по дням за период (для all — последние 90 дней, чтобы график не разрастался) */
$sparkDays = $range === 'all' ? 90 : max(1, (int)$range);
$sparkRows = $pdo->query("SELECT date(o.created_at) AS d, SUM(o.total) AS rev FROM orders o
    WHERE o.status IN $revenueStatuses
      AND o.created_at >= datetime('now','localtime','-" . $sparkDays . " days')
    GROUP BY date(o.created_at)")->fetchAll();
$revByDay = [];
foreach ($sparkRows as $sr) {
    $revByDay[$sr['d']] = (int)$sr['rev'];
}
$sparkData = [];
for ($i = $sparkDays - 1; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $sparkData[] = ['d' => $day, 'rev' => $revByDay[$day] ?? 0];
}
$sparkMax = max($sparkData) === [] ? 0 : max(array_column($sparkData, 'rev'));

$topProducts = $pdo->query("SELECT oi.product_id, oi.name, SUM(oi.qty) AS sold,
        p.image, p.slug
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.status IN $revenueStatuses$rangeCond
    GROUP BY oi.product_id, oi.name
    ORDER BY sold DESC, oi.name LIMIT 5")->fetchAll();

$itemsStmt = $pdo->prepare('SELECT name, price, qty FROM order_items WHERE order_id = :i');

adminHeader('Заказы', 'index');
flash();
?>
<h1>Заказы</h1>

<?php /* Дружелюбное приветствие (критерий 15): конкретная подсказка, что делать сейчас */
$hello = $newCount > 0
    ? ['🌸', 'У вас ' . $newCount . ' нов' . ($newCount === 1 ? 'ый заказ' : ($newCount < 5 ? 'ых заказа' : 'ых заказов')) . '!', 'Откройте первый заказ и позвоните покупателю для подтверждения — это самый важный шаг.']
    : ['🌷', 'Новых заказов нет', 'Хорошее время добавить свежий букет в каталог или проверить, как выглядит витрина.'];
?>
<div class="admin-hello">
  <span class="admin-hello__emoji" aria-hidden="true"><?= $hello[0] ?></span>
  <div>
    <b><?= e($hello[1]) ?></b>
    <p><?= e($hello[2]) ?></p>
  </div>
  <a class="btn btn--accent" style="margin-left:auto" href="/" target="_blank">Посмотреть сайт</a>
</div>

<?php
$dashqs = fn(string $r) => '/admin/index.php?' . e(http_build_query(array_merge(array_filter($_GET, fn($v, $k) => $v !== '' && $k !== 'range', ARRAY_FILTER_USE_BOTH), $r === '30' ? [] : ['range' => $r])));
?>
<div class="dash-section">
<div class="card dash-card">
  <div class="dash-head">
    <h2 style="font-family:var(--font-display);font-size:1.25rem">Статистика</h2>
    <nav class="dash-ranges">
      <?php foreach ([['1', 'Сегодня'], ['7', '7 дней'], ['30', '30 дней'], ['90', '90 дней'], ['all', 'Всё время']] as [$rk, $rl]): ?>
        <a href="<?= $dashqs($rk) ?>" class="<?= $range === $rk ? 'active' : '' ?>"><?= e($rl) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="dash-metrics">
    <div class="dash-metric dash-metric--rose">
      <span class="dash-metric__label">Выручка<?= $range === '1' && ($yCount || $yRev) ? ' (вчера ' . formatPrice($yRev) . ')' : '' ?></span>
      <strong class="dash-metric__value"><?= $allCount > 0 ? formatPrice($revenue) : '—' ?></strong>
    </div>
    <div class="dash-metric dash-metric--blue">
      <span class="dash-metric__label">Заказов<?= $range === '1' && $yCount ? ' (вчера ' . $yCount . ')' : '' ?></span>
      <strong class="dash-metric__value"><?= $allCount ?></strong>
    </div>
    <div class="dash-metric dash-metric--mint">
      <span class="dash-metric__label">Средний чек</span>
      <strong class="dash-metric__value"><?= $revenue > 0 ? formatPrice($avgCheck) : '—' ?></strong>
    </div>
    <div class="dash-metric dash-metric--rose-deep">
      <span class="dash-metric__label">Новые</span>
      <strong class="dash-metric__value"><?= $newCount ?></strong>
    </div>
  </div>
  <p style="font-size:.78rem;color:var(--ink-soft);margin:-8px 0 14px">Выручка и средний чек считаются только по подтверждённым, выполненным и забранным заказам — новые и отменённые не учитываются.</p>

  <div class="dash-spark">
    <span class="dash-metric__label">Выручка по дням (<?= $sparkDays ?> дн.)</span>
    <?php if ($sparkMax <= 0): /* Visual W54: пустой график выглядел «сломанным» — говорим прямо */ ?>
      <p style="margin:6px 0 0;font-size:.85rem;color:var(--ink-soft)">За <?= $sparkDays ?> дн. подтверждённых заказов с выручкой ещё нет — столбики появятся, когда пойдут оплаты.</p>
    <?php else: ?>
    <svg class="sparkline" viewBox="0 0 <?= max(1, $sparkDays) * 8 ?> 60" preserveAspectRatio="none" role="img" aria-label="Выручка по дням">
      <?php foreach ($sparkData as $i => $sd):
        $h = $sparkMax > 0 ? max(2, (int)round($sd['rev'] / $sparkMax * 56)) : 2; ?>
        <rect x="<?= $i * 8 ?>" y="<?= 60 - $h ?>" width="6" height="<?= $h ?>" rx="1.5"
          fill="<?= $sd['rev'] > 0 ? 'var(--rose-cta,#AE4A71)' : 'var(--bg-alt)' ?>">
          <title><?= e($sd['d']) ?>: <?= formatPrice($sd['rev']) ?></title>
        </rect>
      <?php endforeach; ?>
    </svg>
    <?php endif; ?>
  </div>

  <div class="dash-row">
    <div class="dash-statuses">
      <span class="dash-metric__label">По статусам</span>
      <div class="dash-chips">
        <?php foreach (statuses() as $key => $label): ?>
          <span class="status-badge <?= e($key) ?>"><?= e($label) ?>: <?= $statusCounts[$key] ?? 0 ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="dash-top">
      <span class="dash-metric__label">Топ-5 товаров</span>
      <?php if ($topProducts === []): ?>
        <small style="color:var(--ink-soft)">Продаж пока нет</small>
      <?php else: ?>
      <ol class="dash-top-list">
        <?php foreach ($topProducts as $tp): ?>
        <li>
          <?= $tp['image'] !== '' ? '<img class="thumb dash-thumb" src="/img/products/' . e($tp['image']) . '" alt="">' : '<div class="thumb dash-thumb"></div>' ?>
          <span class="dash-top-name"><?= e($tp['name']) ?></span>
          <strong><?= (int)$tp['sold'] ?> шт.</strong>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

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
    <div style="display:flex;gap:10px;align-items:center;margin-top:19px">
      <button class="btn" type="submit">Показать</button>
      <?php if ($statusFilter !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
        <a class="btn btn--ghost" href="/admin/index.php">Сбросить</a>
      <?php endif; ?>
      <a class="btn btn--ghost" href="/admin/export.php?<?= e(http_build_query(array_filter($_GET, fn($v) => $v !== ''))) ?>">Экспорт в CSV</a>
    </div>
  </form>
</div>

<?php if (!$orders): ?>
<div class="card"><div class="empty-state"><strong>Заказов пока нет</strong>Появятся после первого заказа с сайта.</div></div>
<?php else: foreach ($orders as $o): ?>
<div class="card">
  <div class="table-scroll"><table>
    <tr>
      <td style="width:90px"><strong><a class="order-link" href="/admin/order.php?id=<?= (int)$o['id'] ?>">№ <?= (int)$o['id'] ?></a></strong><br><small style="color:var(--ink-soft)"><?= e(date('d.m.Y H:i', strtotime((string)$o['created_at']))) ?></small></td>
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
          <a href="/admin/order.php?id=<?= (int)$o['id'] ?>">Открыть заказ</a>
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
            <?= csrf_field() ?>
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
  </table></div>
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
  <small style="display:block;color:var(--ink-soft)"><?= $totalOrders ?> <?= e(pluralRu($totalOrders, ["заказ","заказа","заказов"])) ?>, страница <?= $page ?> из <?= $pages ?></small>
</div>
<?php endif; ?>
<?php adminFooter(); ?>
