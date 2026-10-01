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
    /* Стресс-критик W88: отказ = HTTP 400, а не 302-редирект (семантика ошибки запроса) */
    http_response_code(400);
    exit('Неверный CSRF-токен. Обновите страницу.');
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
        /* W70 (владелец NEW-2): валидация по тому же словарю, что и UI.
           W98-fixD (D9): «Подтверждён» → «Новые» — локальное исключение (кнопка «Вернуть
           в новые» на карточке): в глобальный словарь includes/util.php не лезем.
           W105-8fix1 (админ-критик 8-c P2): «Новый» принимается и из «В работе» —
           ошибочный статус восстанавливаем из любого живого (селект ниже
           печатает тот же набор переходов). */
        $allowedLocal = orderTransitions()[$curSt] ?? [];
        if (in_array($curSt, ['photo', 'florist', 'courier'], true) && !in_array('new', $allowedLocal, true)) {
            $allowedLocal[] = 'new';
        }
        if (array_key_exists($status, statuses()) && $status !== 'done' && in_array($status, $allowedLocal, true)) {
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

/* W105-8fix1 (админ-критик 8-c P2): заказ просмотрен — фиксируем текущее
   число новых как «прочитанное» (сессия): poll.php отдаёт unread = new − seen,
   заголовок «🔔 (N) НОВЫЙ ЗАКАЗ!» затихает после визита, новый заказ будит
   снова. Аналогичная фиксация — в ленте /admin/index.php. */
$_SESSION['admin_new_seen'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn();

/* S3 (v2026.3): WhatsApp-сообщение по шаблону статуса — подстановка данных
   заказа ({id}, {name}, {time}, {phone}). Шаблоны редактируются в админке
   (wa_template_new/photo/courier). Номер получателя: получатель → заказчик. */
$waPhone = trim((string)($order['recipient_phone'] ?? '')) ?: trim((string)$order['phone']);
$waDigits = preg_replace('/\D/', '', $waPhone) ?: '';
$waTemplateKey = 'wa_template_' . ($order['status'] === 'new' ? 'new'
    : ($order['status'] === 'photo' ? 'photo'
    : ($order['status'] === 'courier' ? 'courier' : 'new')));
$waText = str_replace(
    ['{id}', '{name}', '{time}', '{phone}'],
    [(string)$order['id'], (string)$order['customer_name'], trim((string)($order['delivery_slot'] ?? '') ?: 'в течение дня'), $waPhone],
    setting($waTemplateKey, 'Здравствуйте! Ваш букет №{id} собран — отправляем фото на согласование 📸')
);
$waHref = $waDigits !== '' ? 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($waText) : '';

/* S3: допы заказа (extras JSON) — открытка/Кризал из корзины */
$orderExtras = json_decode((string)($order['extras'] ?? ''), true);
$orderExtras = is_array($orderExtras) ? $orderExtras : [];

adminHeader('Заказ №' . $id, 'index');
flash();
?>
<p><a class="back-link" href="/admin/index.php" style="color:var(--ink-soft);font-size:.85rem">← Все заказы</a></p>
<h1>Заказ № <?= (int)$order['id'] ?> <span class="status-badge <?= e($order['status']) ?>"><?= e(statuses()[$order['status']] ?? $order['status']) ?></span></h1>

<?php /* S3 (v2026.3): панель быстрых действий — записка флористу (печать А5)
       и WhatsApp с шаблоном статуса. */ ?>
<div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px 16px">
  <button type="button" class="btn btn--accent" onclick="window.print()">🖨 Печать записки к букету</button>
  <?php if ($waHref !== ''): ?>
  <a class="btn btn--wa" href="<?= e($waHref) ?>" target="_blank" rel="noopener" title="Откроется WhatsApp с готовым текстом">💬 Написать в WhatsApp</a>
  <?php endif; ?>
  <a class="btn btn--ghost" href="tel:<?= e(preg_replace('/\D/', '', (string)$order['phone'])) ?>">📞 Позвонить</a>
  <span style="font-size:.8rem;color:var(--ink-soft)">Записка — А5 для флориста: состав, адрес, открытка. WhatsApp — с шаблоном текущего этапа.</span>
</div>

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
      <?php /* S3 (v2026.3): бесплатные допы корзины */ ?>
      <?php if (!empty($orderExtras['postcard']) || !empty($orderExtras['chrysal'])): ?>
      <p style="margin-top:6px"><strong>Допы к букету:</strong>
        <?= !empty($orderExtras['postcard']) ? 'открытка' : '' ?><?= !empty($orderExtras['postcard']) && !empty($orderExtras['chrysal']) ? ' + ' : '' ?><?= !empty($orderExtras['chrysal']) ? 'подкормка Chrysal' : '' ?>
      </p>
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
      <?php /* W100-fixH2 (J7): таблице состава — заголовки колонок (scope=col),
         фактические колонки: позиция (имя × количество) и сумма справа */ ?>
      <table>
        <thead>
          <tr><th scope="col">Позиция</th><th scope="col" style="text-align:right">Сумма</th></tr>
        </thead>
        <tbody>
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
        </tbody>
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
<?php elseif (in_array($order['status'], ['canceled', 'unredeemed'], true)): /* W72 (владелец NEW-5): деталка canceled/unredeemed — выход из тупика */ ?>
<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:6px">Вернуть заказ в работу</h2>
  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="status">
    <button class="btn btn--accent" type="submit" name="status" value="new">↩ Вернуть в «Новые»</button>
    <span style="font-size:.85rem;color:var(--ink-soft)">Заказ вернётся в ленту новых — можно подтвердить заново.</span>
  </form>
</div>
<?php elseif (in_array($order['status'], ['new', 'photo', 'florist', 'courier'], true)): /* W70 (владелец NEW-1): «Флорист собирает» больше не тупик — вручение доступно */ ?>
<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:6px">Изменить статус</h2>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <select name="status" style="width:auto">
        <?php /* W70 (владелец NEW-2): только легальные переходы — тот же словарь, что в ленте.
               W105-8fix1 (админ-критик 8-c P2): «Новый» вернулся в селект и для
               «Флорист собирает» (раньше — только кнопкой): ошибочный
               перевод этапа восстанавливаем. Набор = тому, что принимает POST выше. */
               $allowed = orderTransitions()[$order['status']] ?? [];
               if (in_array($order['status'], ['photo', 'florist', 'courier'], true)
                   && !in_array('new', $allowed, true)) {
                   $allowed[] = 'new';
               }
               $labels = statuses();
               foreach ($allowed as $key): ?>
          <option value="<?= e($key) ?>"><?= e($labels[$key] ?? $key) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Применить</button>
    </form>
    <?php /* W98-fixD (D9): из «Согласование фото» — назад в «Новые» одним шагом */
    if ($order['status'] === 'photo'): ?>
    <form method="post" onsubmit="return confirm('Вернуть заказ №<?= (int)$id ?> в «Новые»? Он снова появится в ленте новых заказов.')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="status" value="new">
      <button class="btn btn--ghost" type="submit">↩ Вернуть в «Новые»</button>
    </form>
    <?php endif; ?>
  </div>

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

<?php /* S3 (v2026.3): ЗАПИСКА ФЛОРИСТУ ДЛЯ ПЕЧАТИ (А5). В @media print остаётся
       только .print-note: шапка-номер, состав (по позициям), адрес/интервал,
       телефон, КРУПНЫЙ текст открытки с пожеланием, СЮРПРИЗ-плашка при
       получателе≠заказчику. Кнопка «Печать записки» сверху страницы вызывает
       window.print() — эта разметка и есть то, что уйдёт на принтер. */ ?>
<div class="print-note" aria-label="Печатная записка к букету">
  <div class="print-note__page">
    <header class="print-note__head">
      <span class="print-note__brand"><?= e(setting('shop_name', 'Nilov Flowers')) ?></span>
      <span class="print-note__num">Заказ № <?= (int)$order['id'] ?></span>
    </header>

    <section class="print-note__block">
      <h3 class="print-note__cap">Состав букета</h3>
      <ul class="print-note__items">
        <?php foreach ($items as $it): ?>
        <li><?= e($it['name']) ?> × <?= (int)$it['qty'] ?></li>
        <?php endforeach; ?>
        <?php if (!empty($orderExtras['chrysal'])): ?><li>Подкормка Chrysal (пакетик в коробку)</li><?php endif; ?>
        <?php if (!empty($orderExtras['postcard']) || ($order['card_text'] ?? '') !== ''): ?><li>Открытка — текст ниже, написать от руки</li><?php endif; ?>
      </ul>
    </section>

    <section class="print-note__block">
      <h3 class="print-note__cap">Доставка по Санкт-Петербургу</h3>
      <p class="print-note__row">
        <?= $order['delivery_zone_id'] !== null
            ? e((string)$order['zone_name']) . ($order['delivery_address'] !== '' ? ' · ' . e((string)$order['delivery_address']) : '')
            : 'Самовывоз: ' . e(trim(setting('pickup_address', '')) ?: 'адрес уточнить у менеджера') ?>
      </p>
      <p class="print-note__row">
        <?= ($order['delivery_date'] ?? '') !== '' ? e((string)$order['delivery_date']) . ' · ' : '' ?><?= e((string)($order['delivery_slot'] ?? '') ?: 'время согласовано по телефону') ?>
      </p>
      <?php if (($order['recipient_name'] ?? '') !== '' || ($order['recipient_phone'] ?? '') !== ''): ?>
      <p class="print-note__row"><strong><?= e((string)($order['recipient_name'] ?? '')) ?></strong><?= ($order['recipient_phone'] ?? '') !== '' ? ' · ' . e((string)$order['recipient_phone']) : '' ?></p>
      <?php endif; ?>
      <p class="print-note__row">Заказчик: <?= e((string)$order['customer_name']) ?><?= $order['phone'] !== '' ? ' · ' . e((string)$order['phone']) : '' ?></p>
    </section>

    <?php if (($order['recipient_name'] ?? '') !== ''): ?>
    <p class="print-note__surprise">Сюрприз — имя отправителя получателю не называть</p>
    <?php endif; ?>

    <?php if (($order['card_text'] ?? '') !== ''): ?>
    <section class="print-note__card">
      <h3 class="print-note__cap">Текст открытки — написать крупно и разборчиво</h3>
      <p class="print-note__card-text">«<?= e((string)$order['card_text']) ?>»</p>
    </section>
    <?php endif; ?>

    <footer class="print-note__foot">
      <span><?= e(date('d.m.Y H:i', strtotime((string)$order['created_at']))) ?></span>
      <span><?= $order['payment_method'] === 'online' ? 'оплачен онлайн' : 'оплата при получении · ' . formatPrice((int)$order['total']) ?></span>
    </footer>
  </div>
</div>

<style>
/* S3: печатная записка — экран: спрятана; печать: одна страница А5 (или А6
   при масштабе 2 на лист). Кнопки/навигация админки в печать не идут. */
.print-note{display:none}
.btn--wa{background:#1FAF54;color:#fff}
.btn--wa:hover{background:#178f45}

@media print{
  @page{size:A5 portrait;margin:8mm}
  body *{visibility:hidden!important}
  .print-note,.print-note *{visibility:visible!important}
  .print-note{display:block!important;position:absolute;inset:0;width:100%}
  .admin-top,main.wrap>*:not(.print-note){display:none!important}
  .print-note__page{font-family:'Golos Text',system-ui,sans-serif;color:#18181B;background:#fff;padding:2mm}
  .print-note__head{display:flex;justify-content:space-between;align-items:baseline;border-bottom:2px solid #143C2B;padding-bottom:3mm;margin-bottom:4mm}
  .print-note__brand{font-weight:800;font-size:13pt;letter-spacing:.02em}
  .print-note__num{font-weight:700;font-size:12pt;color:#143C2B}
  .print-note__cap{font-size:8.5pt;text-transform:uppercase;letter-spacing:.1em;color:#6E6A72;margin:0 0 2mm;font-weight:700}
  .print-note__block{margin-bottom:4mm}
  .print-note__items{margin:0;padding-left:5mm;font-size:13pt;line-height:1.55;list-style:disc}
  .print-note__row{margin:0 0 1.5mm;font-size:11.5pt;line-height:1.4}
  .print-note__surprise{margin:4mm 0;padding:3mm 4mm;background:#F4DEE3;color:#8E3B54;font-weight:800;font-size:10.5pt;text-transform:uppercase;letter-spacing:.06em;text-align:center;border-radius:2mm}
  .print-note__card{border:1.5px dashed #143C2B;border-radius:3mm;padding:4mm;margin-top:2mm}
  .print-note__card-text{font-family:'Playfair Display',Georgia,serif;font-size:16pt;line-height:1.5;margin:0;min-height:30mm;word-break:break-word}
  .print-note__foot{display:flex;justify-content:space-between;margin-top:4mm;padding-top:2mm;border-top:1px solid #E8E6E1;font-size:9pt;color:#6E6A72}
}
</style>
<?php adminFooter(); ?>
