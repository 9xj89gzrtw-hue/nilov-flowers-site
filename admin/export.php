<?php
/* Экспорт заказов в CSV (Excel-совместимый, ; разделитель, BOM для кириллицы).
   Фильтры: status, date_from, date_to (даты создания) + delivery_from/delivery_to/dchip
   (дата доставки — как в списке заказов, W98-fixD). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/db.php';
ensureAdminUser();
requireAdmin();

$pdo = db();
$status = (string)($_GET['status'] ?? '');
if ($status !== '' && !array_key_exists($status, statuses())) {
    $status = '';
}
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
/* W98-fixD (D1): экспорт учитывает и фильтры планирования доставки из списка */
$deliveryFrom = trim((string)($_GET['delivery_from'] ?? ''));
$deliveryTo = trim((string)($_GET['delivery_to'] ?? ''));
$deliveryChip = (string)($_GET['dchip'] ?? '');
if (!in_array($deliveryChip, ['today', 'tomorrow', 'week', 'nodate'], true)) {
    $deliveryChip = '';
}

$sql = 'SELECT o.*, z.name AS zone_name FROM orders o
        LEFT JOIN delivery_zones z ON z.id = o.delivery_zone_id WHERE 1=1';
$params = [];
if ($status !== '') {
    $sql .= ' AND o.status = :s';
    $params[':s'] = $status;
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $sql .= ' AND o.created_at >= :df';
    $params[':df'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $sql .= ' AND o.created_at <= :dt';
    $params[':dt'] = $dateTo . ' 23:59:59';
}
if ($deliveryFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryFrom)) {
    $sql .= ' AND o.delivery_date >= :ddf';
    $params[':ddf'] = $deliveryFrom;
}
if ($deliveryTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryTo)) {
    $sql .= ' AND o.delivery_date <= :ddt';
    $params[':ddt'] = $deliveryTo;
}
if ($deliveryChip === 'today') {
    $sql .= ' AND o.delivery_date = :dchip_date';
    $params[':dchip_date'] = date('Y-m-d');
} elseif ($deliveryChip === 'tomorrow') {
    $sql .= ' AND o.delivery_date = :dchip_date';
    $params[':dchip_date'] = date('Y-m-d', strtotime('+1 day'));
} elseif ($deliveryChip === 'week') {
    $sql .= ' AND o.delivery_date >= :dchip_from AND o.delivery_date <= :dchip_to';
    $params[':dchip_from'] = date('Y-m-d');
    $params[':dchip_to'] = date('Y-m-d', strtotime('+6 days'));
} elseif ($deliveryChip === 'nodate') {
    $sql .= " AND (o.delivery_date IS NULL OR o.delivery_date = '')";
}
$sql .= ' ORDER BY o.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$out = fopen('php://temp', 'r+');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM — кириллица в Excel
/* Security-критик re-wave CSV formula injection: поле, начинающееся с = + - @, Excel
   исполнит как формулу. Данные заказа приходят от посторонних (имя/телефон/комментарий). */
$csvCell = static function ($v) {
    $v = (string)$v;
    return ($v !== '' && strpos($v[0], '=') === 0 || ($v !== '' && in_array($v[0], ['+', '-', '@'], true)))
        ? "'" . $v : $v;
};
fputcsv($out, ['ID', 'Дата', 'Имя', 'Телефон', 'Email', 'Доставка', 'Адрес', 'Оплата',
    'Состав', 'Сумма', 'Статус', 'Комментарий'], ';', '"', ''); /* W72 (владелец W71-1): PHP 8.5 deprecated $escape — явный '' */
$itemsStmt = $pdo->prepare('SELECT name, qty FROM order_items WHERE order_id = :i');
foreach ($stmt->fetchAll() as $o) {
    $itemsStmt->execute([':i' => $o['id']]);
    $composition = implode('; ', array_map(
        fn($it) => $it['name'] . ' x' . $it['qty'],
        $itemsStmt->fetchAll()
    ));
    fputcsv($out, [
        $o['id'], $o['created_at'], $csvCell($o['customer_name']), $csvCell($o['phone']), $csvCell($o['email']),
        $csvCell($o['zone_name'] ?? 'Самовывоз'), $csvCell($o['delivery_address']),
        $o['payment_method'] === 'online' ? 'онлайн' : 'при получении',
        $csvCell($composition), $o['total'], statuses()[$o['status']] ?? $o['status'], $csvCell($o['comment']),
    ], ';', '"', '');
}
rewind($out);
$csv = stream_get_contents($out);
fclose($out);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
header('Content-Length: ' . strlen($csv));
echo $csv;
exit;
