<?php
/* Страница благодарности: ?id=N — показывается после оформления заказа.
   Самовывоз/доставка — из настроек. Без id — тихий редирект на главную. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: /');
    exit;
}

$siteName = setting('shop_name', 'Nilov Flowers');
$phone = setting('shop_phone', '');
$phoneDigits = preg_replace('/\D/', '', $phone) ?: '';
$pickupAddr = trim(setting('shop_address', ''));

$canonicalUrl = 'https://flowers.interfood-catering.ru/order-thanks';
?><!DOCTYPE html>
<html lang="ru">
<head>
<title>Заказ №<?= $orderId ?> принят — <?= e($siteName) ?></title>
<meta name="robots" content="noindex">
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main class="section">
  <div class="wrap" style="max-width:640px;text-align:center">
    <svg class="nv-bloom" viewBox="0 0 200 120" fill="none" aria-hidden="true" style="margin:0 auto 18px">
      <g class="nv-bloom-petals">
        <ellipse cx="100" cy="58" rx="22" ry="44" fill="currentColor" opacity=".32" transform="rotate(-38 100 88)"/>
        <ellipse cx="100" cy="58" rx="22" ry="44" fill="currentColor" opacity=".32" transform="rotate(-14 100 88)"/>
        <ellipse cx="100" cy="58" rx="22" ry="44" fill="currentColor" opacity=".32" transform="rotate(14 100 88)"/>
        <ellipse cx="100" cy="58" rx="22" ry="44" fill="currentColor" opacity=".32" transform="rotate(38 100 88)"/>
      </g>
      <circle cx="100" cy="74" r="15" fill="currentColor" opacity=".55"/>
    </svg>
    <h1 class="section-title" style="margin-bottom:.3em">Спасибо! Заказ №<?= $orderId ?> принят</h1>
    <p style="font-size:1.08rem;margin-bottom:1.2rem">Мы уже увидели ваш заказ и <strong>позвоним для подтверждения</strong> в ближайшее время.</p>
    <p style="color:var(--ink-soft);margin-bottom:1.6rem">
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
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
