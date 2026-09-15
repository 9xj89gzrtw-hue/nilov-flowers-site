<?php
/* Страница заказа в админке: детали + смена статуса (для «done» требуется
   подтверждение вручения: фото и/или причина отсутствия фото). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/db.php';
ensureAdminUser();
requireAdmin();

/* CSRF: админ-POST без валидного токена — отказ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    $back = $_SERVER['HTTP_REFERER'] ?? '/admin/index.php';
    header('Location: ' . $back);
    exit;
}

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /admin/index.php');
    exit;
}

/* Обработка действий */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'status') {
        $status = (string)($_POST['status'] ?? '');
        $curSt = '';
        $stQ = $pdo->prepare('SELECT status FROM orders WHERE id = :i');
        $stQ->execute([':i' => $id]);
        $curSt = (string)($stQ->fetchColumn() ?: '');
        /* W70 (владелец NEW-2): валидация по тому же словарю, что и UI */
        if (array_key_exists($status, statuses()) && $status !== 'done' && in_array($status, orderTransitions()[$curSt] ?? [], true)) {
            $pdo->prepare('UPDATE orders SET status = :s WHERE id = :i')
                ->execute([':s' => $status, ':i' => $id]);
            flash('Статус заказа обновлён');
        } elseif ($status === 'done') {
            /* «Выполнен» только через форму вручения */
            flash('Для статуса «Выполнен» заполните подтверждение вручения', true);
        } else {
            flash('Недопустимый переход статуса', true);
        }
        header('Location: /admin/order.php?id=' . $id);
        exit;
    }

    if ($action === 'handover') {
        $givenTo = mb_substr(trim((string)($_POST['given_to'] ?? '')), 0, 200);
        $noPhotoReason = mb_substr(trim((string)($_POST['no_photo_reason'] ?? '')), 0, 500);
        $photoFile = saveUpload($_FILES['handover_photo'] ?? [], IMG_UPLOADS_DIR);

        if ($photoFile === '' && $noPhotoReason === '') {
            flash('Нужно фото вручения или причина его отсутствия', true);
            header('Location: /admin/order.php?id=' . $id);
            exit;
        }

        $pdo->prepare('UPDATE orders SET status = :s, given_to = :g, no_photo_reason = :r,
                handover_photo = COALESCE(NULLIF(:p, \'\'), handover_photo)
                WHERE id = :i')
            ->execute([':s' => 'done', ':g' => $givenTo, ':r' => $noPhotoReason, ':p' => $photoFile, ':i' => $id]);
        flash('Заказ отмечен выполненным');
        header('Location: /admin/order.php?id=' . $id);
        exit;
    }

    /* Удаление заказа (по желанию владельца): позиции + сам заказ, необратимо.
       Отдельная страница «заказ удалён» не нужна — возврат в список с плашкой. */
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM order_items WHERE order_id = :i')->execute([':i' => $id]);
        $pdo->prepare('DELETE FROM orders WHERE id = :i')->execute([':i' => $id]);
        flash('Заказ №' . $id . ' удалён');
        header('Location: /admin/index.php');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT o.*, z.name AS zone_name, z.price AS zone_price FROM orders o
    LEFT JOIN delivery_zones z ON z.id = o.delivery_zone_id WHERE o.id = :i');
$stmt->execute([':i' => $id]);
$order = $stmt->fetch();
if (!$order) {
    flash('Заказ не найден', true);
    header('Location: /admin/index.php');
    exit;
}

$items = $pdo->prepare('SELECT name, price, qty FROM order_items WHERE order_id = :i');
$items->execute([':i' => $id]);
$items = $items->fetchAll();

adminHeader('Заказ №' . $id, 'index');
flash();
?>
<p><a class="back-link" href="/admin/index.php" style="color:var(--ink-soft);font-size:.85rem">← Все заказы</a></p>
<h1>Заказ № <?= (int)$order['id'] ?> <span class="status-badge <?= e($order['status']) ?>"><?= e(statuses()[$order['status']] ?? $order['status']) ?></span></h1>

<div class="card">
  <div class="grid2">
    <div>
      <h2 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:6px">Покупатель</h2>
      <p><strong><?= e($order['customer_name']) ?></strong></p>
      <p><?= $order['phone'] !== '' ? '<a href="tel:' . e(preg_replace('/\D/', '', $order['phone'])) . '">' . e($order['phone']) . '</a>' : '' ?>
         <?= $order['email'] !== '' ? ' · ' . e($order['email']) : '' ?></p>
      <p style="margin-top:8px;color:var(--ink-soft);font-size:.88rem">
        <?= $order['delivery_zone_id'] !== null
            ? 'Доставка: ' . e((string)$order['zone_name']) . ($order['delivery_address'] !== '' ? ' — ' . e($order['delivery_address']) : '')
            : 'Самовывоз' ?><br>
        Оплата: <?= $order['payment_method'] === 'online' ? 'онлайн (карта/СБП)' : 'при получении' ?><br>
        Создан: <?= e(date('d.m.Y H:i', strtotime((string)$order['created_at']))) ?>
      </p>
      <?php if ($order['comment'] !== ''): ?>
        <p style="margin-top:8px"><strong>Комментарий:</strong> <?= e($order['comment']) ?></p>
      <?php endif; ?>
      <?php /* Критик functional (gift-UX/слоты): новые поля заказа */ ?>
      <?php if (($order['recipient_name'] ?? '') !== '' || ($order['recipient_phone'] ?? '') !== ''): ?>
        <p style="margin-top:8px;padding:10px 12px;background:var(--mint);border-radius:12px">
          <strong>Получатель:</strong> <?= e((string)($order['recipient_name'] ?? '')) ?>
          <?= ($order['recipient_phone'] ?? '') !== '' ? ' · <a href="tel:' . e(preg_replace('/\D/', '', (string)$order['recipient_phone'])) . '">' . e((string)$order['recipient_phone']) . '</a>' : '' ?>
        </p>
      <?php endif; ?>
      <?php if (($order['card_text'] ?? '') !== ''): ?>
        <p style="margin-top:6px"><strong>Открытка:</strong> «<?= e((string)$order['card_text']) ?>»</p>
      <?php endif; ?>
      <?php if (($order['delivery_date'] ?? '') !== '' || ($order['delivery_slot'] ?? '') !== ''): ?>
        <p style="margin-top:6px"><strong>Хочет доставку:</strong> <?= e(trim((string)(($order['delivery_date'] ?? '') . ' ' . ($order['delivery_slot'] ?? '')))) ?></p>
      <?php endif; ?>
      <?php if (($order['promo_code'] ?? '') !== ''): ?>
        <p style="margin-top:6px"><strong>Промокод:</strong> <?= e((string)$order['promo_code']) ?></p>
      <?php endif; ?>
    </div>
    <div>
      <h2 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:6px">Состав</h2>
      <table>
        <?php if (!$items): /* W68 (obvious NEW-2): заказ без строк — не пустая таблица «Итого», а объяснение */ ?>
        <tr><td style="color:var(--ink-soft)">Позиции не записаны (заказ без состава)</td><td></td></tr>
        <?php endif; ?>
        <?php foreach ($items as $it): ?>
        <tr><td><?= e($it['name']) ?> × <?= (int)$it['qty'] ?></td><td style="text-align:right"><?= formatPrice((int)$it['price'] * (int)$it['qty']) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($order['delivery_zone_id'] !== null && (int)$order['zone_price'] > 0): ?>
        <tr><td>Доставка</td><td style="text-align:right"><?= formatPrice((int)$order['zone_price']) ?></td></tr>
        <?php endif; ?>
        <tr><td><strong>Итого</strong></td><td style="text-align:right"><strong><?= formatPrice((int)$order['total']) ?></strong></td></tr>
      </table>
    </div>
  </div>
</div>

<?php if ($order['status'] === 'done'): ?>
<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:6px">Подтверждение вручения</h2>
  <?php if ($order['handover_photo'] !== ''): ?>
    <p><img src="/img/uploads/<?= e($order['handover_photo']) ?>" alt="Фото вручения" style="max-width:280px;border-radius:12px;margin:8px 0"></p>
  <?php endif; ?>
  <?php if ($order['given_to'] !== ''): ?><p>Кому вручено: <strong><?= e($order['given_to']) ?></strong></p><?php endif; ?>
  <?php if ($order['no_photo_reason'] !== ''): ?><p style="color:var(--ink-soft)">Причина отсутствия фото: <?= e($order['no_photo_reason']) ?></p><?php endif; ?>
  <p style="color:var(--ink-soft);font-size:.85rem;margin-top:6px">Заказ выполнен — статус больше не меняется.</p>
</div>
<?php elseif (in_array($order['status'], ['new', 'confirmed', 'in_progress'], true)): /* W70 (владелец NEW-1): «В работе» больше не тупик — вручение доступно */ ?>
<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:6px">Изменить статус</h2>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="status">
    <select name="status" style="width:auto">
      <?php /* W70 (владелец NEW-2): только легальные переходы — тот же словарь, что в ленте */
             $allowed = orderTransitions()[$order['status']] ?? [];
             $labels = statuses();
             foreach ($allowed as $key): ?>
        <option value="<?= e($key) ?>"><?= e($labels[$key] ?? $key) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Применить</button>
  </form>

  <h2 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:2px">Выполнен (подтверждение вручения)</h2>
  <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:8px">Чтобы отметить заказ «Выполненным», приложите фото вручения (букет и ориентир: дом, подъезд) — или укажите причину, если фото нет.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="handover">
    <label class="f" for="hp">Фото вручения</label>
    <input class="input" id="hp" name="handover_photo" type="file" accept="image/*">
    <label class="f" for="gt">Кому вручено (необязательно)</label>
    <input class="input" id="gt" name="given_to" placeholder="Получатель / курьер передал лично">
    <label class="f" for="npr">Причина отсутствия фото</label>
    <input class="input" id="npr" name="no_photo_reason" placeholder="Если фото сделать не удалось">
    <button class="btn btn--accent" type="submit" style="margin-top:14px">Отметить выполненным</button>
  </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:20px">
  <details>
    <summary class="del-summary">Удалить заказ №<?= (int)$order['id'] ?></summary>
    <p style="font-size:.85rem;color:var(--ink-soft);margin:10px 0">Заказ исчезнет из списка вместе с составом. Действие необратимо — если заказ просто не нужен, лучше «Отменить» (данные сохранятся в статистике).</p>
    <form method="post" onsubmit="return confirm('Удалить заказ №<?= (int)$order['id'] ?> безвозвратно? Восстановить будет нельзя.')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="btn btn--danger">Удалить безвозвратно</button>
    </form>
  </details>
</div>
<?php adminFooter(); ?>
