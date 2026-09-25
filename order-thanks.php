<?php
/* Страница благодарности: ?id=N — показывается после оформления заказа.
   Самовывоз/доставка — из настроек. Без id — тихий редирект на главную.
   Редизайн 5cv (W96/T2-d): центрированная карточка успеха с галочкой. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: /');
    exit;
}
/* W100 (security-критик, BOLA): ранее детали заказа (email, состав, слоты, сумма) были
   доступны анонимно по последовательным id — перебор всей базы. Теперь страница требует
   одноразовый токен из ответа API (paymentToken того же заказа, hash_equals), который
   знает только клиент, оформивший заказ. Без токена — тихий редирект. */
$__thanksTok = (string)($_GET['t'] ?? '');
if ($__thanksTok === '') {
    header('Location: /');
    exit;
}
$siteName = setting('shop_name', 'Nilov Flowers');
$phone = setting('shop_phone', '');
$phoneDigits = preg_replace('/\D/', '', $phone) ?: '';
$pickupAddr = trim(setting('shop_address', ''));

/* Security-критик W40 MEDIUM: без проверки страница рисовала «Заказ №N принят» для ЛЮБОГО N
   (подтверждение существования/несуществования заказов). Теперь — только реальный заказ.
   W99-fixG (G15): одним запросом тянем и сам заказ (email/дата/интервал/оплата/сумма),
   и зону доставки (LEFT JOIN, как было). */
$__orderStmt = db()->prepare('SELECT o.id, o.email, o.delivery_date, o.delivery_slot,
    o.delivery_zone_id, o.payment_method, o.total, o.payment_token, z.name AS zone
    FROM orders o LEFT JOIN delivery_zones z ON z.id = o.delivery_zone_id WHERE o.id = :i LIMIT 1');
$__orderStmt->execute([':i' => $orderId]);
$__order = $__orderStmt->fetch();
if (!$__order || !hash_equals((string)($__order['payment_token'] ?? ''), $__thanksTok)) {
    header('Location: /');
    exit;
}
$__zoneName = (string)($__order['zone'] ?? '');

/* W99-fixG (G15): состав заказа — позиции × количество (для сводки) */
$__items = [];
try {
    $__itemsStmt = db()->prepare('SELECT name, price, qty FROM order_items WHERE order_id = :i ORDER BY id');
    $__itemsStmt->execute([':i' => $orderId]);
    $__items = $__itemsStmt->fetchAll();
} catch (Throwable $e) {
    $__items = [];
}

/* Доставка/самовывоз — честная строка: зона заказа ИЛИ адрес самовывоза */
$deliveryLine = '';
if ($__zoneName !== '') {
    $deliveryLine = 'Доставка: район «' . $__zoneName . '» — время и адрес подтвердим по телефону.';
} elseif ($pickupAddr !== '') {
    $deliveryLine = 'Самовывоз: ' . $pickupAddr . ' — предупредим, когда букет будет готов.';
}

/* W99-fixG (G15): строка про письмо — только если у заказа есть email
   (транзакционное письмо клиенту отправляет notifyNewOrder, W98-fixF F3) */
$__orderEmail = trim((string)($__order['email'] ?? ''));

$canonicalUrl = 'https://flowers.interfood-catering.ru/order-thanks';
$pageTitle = 'Заказ № ' . $orderId . ' принят — ' . $siteName;
?><!DOCTYPE html>
<html lang="ru">
<head>
<title>Заказ № <?= $orderId ?> принят — <?= e($siteName) ?></title>
<meta name="robots" content="noindex">
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap" style="max-width:560px;text-align:center">
      <?php /* Галочка в круге (5cv): тёплая подложка + розовый штрих */ ?>
      <span aria-hidden="true" style="display:inline-grid;place-items:center;width:72px;height:72px;border-radius:50%;background:var(--surface-warm);margin-bottom:18px">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--pink,#ff4ea2)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
      </span>
      <h1 class="page-hero__title">Спасибо! Заказ № <?= $orderId ?> принят</h1>
      <?php /* W96-fix3b (D8): конкретное обещание звонка — настройка thanks_call_text
         (пустое значение в БД скрывает строку, отсутствующий ключ — дефолт) */ ?>
      <?php $thanksCall = trim(setting('thanks_call_text', 'Мы позвоним в течение 15 минут для подтверждения')); ?>
      <?php if ($thanksCall !== ''): ?>
      <p style="font-size:1.05rem;margin:10px 0 0"><strong><?= e($thanksCall) ?></strong></p>
      <?php endif; ?>
      <?php /* W99-fixG (G15а): письмо с деталями — если email в заказе есть */ ?>
      <?php if ($__orderEmail !== ''): ?>
      <p style="margin:8px 0 0;color:var(--ink-soft,#6e6a72);font-size:.92rem">Мы отправили письмо с деталями на <?= e($__orderEmail) ?></p>
      <?php endif; ?>
      <p class="section-sub" style="margin:12px auto 24px">
        Букет соберём из цветов утренней поставки, а фото пришлём вам перед отправкой.
        <?php if ($deliveryLine !== ''): ?><br><?= e($deliveryLine) ?><?php endif; ?>
      </p>
      <?php /* W99-fixG (G15б): сводка заказа — состав × количество, дата+интервал
         доставки, район и сумма к оплате. Карточка по образцу существующих блоков
         (border/16px/#fff, как .track-card), компактно. */ ?>
      <?php if ($__items !== []): ?>
      <div style="border:1.5px solid var(--line,#e7e5ea);border-radius:16px;padding:16px 18px;background:#fff;text-align:left;margin:0 auto 20px;max-width:440px">
        <p style="font-weight:700;margin:0 0 8px;font-size:.95rem">Ваш заказ</p>
        <ul style="margin:0 0 10px;padding-left:18px;font-size:.9rem;line-height:1.6">
          <?php foreach ($__items as $__it): ?>
          <li><?= e($__it['name']) ?> — <?= (int)$__it['qty'] ?> × <?= formatPrice((int)$__it['price']) ?></li>
          <?php endforeach; ?>
        </ul>
        <p style="margin:0 0 4px;font-size:.88rem;color:var(--ink-soft,#6e6a72)">
          <?php if ($__zoneName !== ''): ?>Доставка: <?= e($__zoneName) ?><?php else: ?>Самовывоз<?php endif; ?><?= trim((string)$__order['delivery_date']) !== '' ? ' · ' . e($__order['delivery_date']) : '' ?><?= trim((string)$__order['delivery_slot']) !== '' ? ' · ' . e($__order['delivery_slot']) : '' ?>
        </p>
        <p style="margin:0;font-size:.95rem;font-weight:700">
          <?= ($__order['payment_method'] ?? 'cash') === 'cash' ? 'К оплате при получении: ' : 'Итого: ' ?><?= formatPrice((int)$__order['total']) ?>
        </p>
      </div>
      <?php endif; ?>
      <?php /* W96-fix3b (D8): «Что дальше» — 3 шага в стилистике карточки заказа */ ?>
      <div class="fc-thanks__steps">
        <p class="fc-thanks__title">Что дальше</p>
        <ol class="fc-thanks__list">
          <li>
            <span class="fc-thanks__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
            <span><strong>Подтвердим по телефону</strong> — уточним букет, адрес и время</span>
          </li>
          <li>
            <span class="fc-thanks__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>
            <span><strong>Соберём и сфотографируем</strong> — пришлём фото перед отправкой</span>
          </li>
          <li>
            <span class="fc-thanks__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 6h11v11H2z"/><path d="M13 9h4l3 3v5h-3"/><circle cx="5.5" cy="17.5" r="2"/><circle cx="16.5" cy="17.5" r="2"/></svg></span>
            <span><strong>Привезём</strong> — вручим лично или оставим у двери, как скажете</span>
          </li>
        </ol>
      </div>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px">
        <a class="btn btn--accent" href="/#catalog">Выбрать ещё букеты</a>
        <?php if (setting('feature_track_link', '1') === '1'): ?>
        <a class="btn btn--outline" href="/track">Отследить заказ</a>
        <?php endif; ?>
        <?php if ($phone !== ''): ?>
        <a class="btn btn--outline" href="tel:+<?= e($phoneDigits) ?>">Позвонить нам: <?= e($phone) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
