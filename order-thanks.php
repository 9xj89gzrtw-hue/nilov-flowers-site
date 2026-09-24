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
/* Security-критик W40 MEDIUM: без проверки страница рисовала «Заказ №N принят» для ЛЮБОГО N
   (подтверждение существования/несуществования заказов). Теперь — только реальный заказ. */
$__exists = db()->prepare('SELECT 1 FROM orders WHERE id = :i LIMIT 1');
$__exists->execute([':i' => $orderId]);
if ($__exists->fetchColumn() === false) {
    header('Location: /');
    exit;
}

$siteName = setting('shop_name', 'Nilov Flowers');
$phone = setting('shop_phone', '');
$phoneDigits = preg_replace('/\D/', '', $phone) ?: '';
$pickupAddr = trim(setting('shop_address', ''));

$canonicalUrl = 'https://flowers.interfood-catering.ru/order-thanks';
$pageTitle = 'Заказ №' . $orderId . ' принят — ' . $siteName;
?><!DOCTYPE html>
<html lang="ru">
<head>
<title>Заказ №<?= $orderId ?> принят — <?= e($siteName) ?></title>
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
      <h1 class="page-hero__title">Спасибо! Заказ №<?= $orderId ?> принят</h1>
      <p style="font-size:1.05rem;margin:10px 0 0">Мы уже увидели ваш заказ и <strong>позвоним для подтверждения</strong> в ближайшее время.</p>
      <p class="section-sub" style="margin:12px auto 24px">
        Букет соберём из цветов утренней поставки, а фото пришлём вам перед отправкой.
        <?php if ($pickupAddr !== ''): ?><br>Самовывоз: <?= e($pickupAddr) ?> — предупредим, когда букет будет готов.<?php endif; ?>
      </p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a class="btn btn--accent" href="/#catalog">Выбрать ещё букеты</a>
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
