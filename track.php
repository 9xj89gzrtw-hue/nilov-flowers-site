<?php
/**
 * Страница трекинга заказа «Где мой заказ?» (критерий 13, паттерн branded tracking 2026).
 * Покупатель вводит телефон последнего заказа → видит статус + состав + сумму.
 * Без пароля: маскируем чувствительное (адрес → только район), максимум 5 записей.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$phone = trim($_GET['phone'] ?? '');
$orders = [];
$rlLimited = false;
$normalized = preg_replace('/\D+/', '', $phone);
/* Legal/security-критик W40: поиск по 10-значному «хвосту» LIKE '%tail' был оракулом —
   перебором хвостов можно было смотреть чужие заказы. Теперь: полная длина (10-11 цифр)
   и точное совпадение нормализованного номера (8↔7 эквивалентны), плюс rate-limit. */
if (!rl_check('track', 10, 600)) {
    $rlLimited = true;
    http_response_code(429);
    header('Retry-After: ' . max(60, rl_retry_after('track', 600)));
} elseif (strlen($normalized) === 10 || strlen($normalized) === 11) {
    $d11 = strlen($normalized) === 10 ? '7' . $normalized : preg_replace('/^8/', '7', $normalized);
    $rows = db()->prepare(
        "SELECT o.id, o.created_at, o.status, o.total, o.delivery_zone_id,
                z.name AS zone
         FROM orders o LEFT JOIN delivery_zones z ON z.id = o.delivery_zone_id
         WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(o.phone, ' ', ''), '+', ''), '(', ''), ')', ''), '-', '') = :n
            OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(o.phone, ' ', ''), '+', ''), '(', ''), ')', ''), '-', '') = :n8
         ORDER BY o.id DESC LIMIT 5"
    );
    $rows->execute([':n' => $d11, ':n8' => preg_replace('/^7/', '8', $d11)]);
    $orders = $rows->fetchAll();
}

$pageTitle = 'Где мой заказ? — Nilov Flowers';
require __DIR__ . '/partials/head.php';
?>
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex, follow">
<meta name="description" content="Проверьте статус заказа букета по номеру телефона — Nilov Flowers, доставка цветов в Санкт-Петербурге.">
<?php
require __DIR__ . '/partials/header.php';

$statusEmoji = [
    'new' => '🌸 Новый',
    'confirmed' => '✅ Подтверждён',
    'done' => '💐 Выполнен',
    'canceled' => '✖️ Отменён',
    'unredeemed' => '📦 Не выкуплен',
];
function trackStep(string $status): int {
    return match ($status) {
        'new' => 1, 'confirmed' => 2, 'done' => 3, default => 0,
    };
}
$shopPhone = setting('shop_phone', '');
?>
<style>
.track{max-width:680px;margin:0 auto;padding:48px 20px 80px}
.track h1{font-family:var(--font-display);font-weight:600;font-size:2rem;margin-bottom:8px}
.track__lead{color:var(--ink-soft);margin-bottom:24px}
.track-form{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px}
.track-form input{flex:1 1 240px;padding:12px 16px;border-radius:12px;border:1px solid var(--line);font-size:1rem;background:#fff}
.track-form button{padding:12px 22px;border-radius:12px;border:0;background:var(--rose);color:#fff;font-weight:600;font-size:.95rem;cursor:pointer}
.track-card{border:1px solid var(--line);border-radius:16px;padding:18px 20px;margin-bottom:12px;background:#fff}
.track-card__top{display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap}
.track-card__id{font-weight:600}
.track-card__status{padding:3px 12px;border-radius:999px;font-size:.8rem;font-weight:600;white-space:nowrap}
.track-card__status.new{background:#fdeef2;color:#c2497a}
.track-card__status.confirmed{background:#e8f0fb;color:#3a6db3}
.track-card__status.done{background:#e7f5ec;color:#2e7d4f}
.track-card__status.canceled,.track-card__status.unredeemed{background:#f5f5f5;color:#777}
.track-card__meta{color:var(--ink-soft);font-size:.88rem;margin-top:8px;line-height:1.55}
.track-steps{display:flex;gap:4px;margin-top:14px}
.track-steps span{flex:1;height:6px;border-radius:3px;background:var(--line)}
.track-steps span.on{background:var(--rose)}
.track-empty{padding:36px 20px;text-align:center;color:var(--ink-soft);border:1px dashed var(--line);border-radius:16px}
@media (max-width:640px){.track{padding:32px 16px 64px}.track h1{font-size:1.6rem}}
</style>
<main id="main" class="track">
  <h1>Где мой заказ?</h1>
  <p class="track__lead">Введите телефон, который указали при оформлении — покажем статус ваших последних заказов.</p>

  <form class="track-form" method="get" action="/track">
    <input type="tel" name="phone" value="<?= e($phone) ?>" placeholder="+7 (900) 123-45-67" required>
    <button type="submit">Проверить статус</button>
  </form>

  <?php if ($rlLimited): ?>
    <div class="track-empty">Слишком много проверок подряд — подождите минуту и попробуйте снова. Если срочно — позвоните: <a href="tel:<?= e($shopPhone) ?>"><?= e($shopPhone) ?></a></div>
  <?php elseif ($normalized !== ''): ?>
    <?php if ($orders === []): ?>
      <div class="track-empty">По этому телефону заказов не найдено.<br>Проверьте номер или позвоните нам: <a href="tel:<?= e($shopPhone) ?>"><?= e($shopPhone) ?></a></div>
    <?php else: ?>
      <?php foreach ($orders as $o):
          $step = trackStep($o['status']);
          $addr = $o['zone'] ? 'Доставка: ' . $o['zone'] : 'Самовывоз';
      ?>
      <div class="track-card">
        <div class="track-card__top">
          <span class="track-card__id">Заказ №<?= (int)$o['id'] ?> · <?= e($o['created_at']) ?></span>
          <span class="track-card__status <?= e($o['status']) ?>"><?= $statusEmoji[$o['status']] ?? e($o['status']) ?></span>
        </div>
        <p class="track-card__meta">
          <?= e($addr) ?> · Сумма: <strong><?= formatPrice((int)$o['total']) ?></strong>
          <?php if ($o['status'] === 'new'): ?><br>Мы свяжемся с вами для подтверждения в течение 30 минут.
          <?php elseif ($o['status'] === 'confirmed'): ?><br>Букет собираем — фото пришлём перед отправкой.
          <?php elseif ($o['status'] === 'done'): ?><br>Доставлено. Спасибо, что выбираете нас! 💐
          <?php endif; ?>
        </p>
        <?php if ($step > 0): ?>
        <div class="track-steps" aria-label="Прогресс заказа">
          <span class="<?= $step >= 1 ? 'on' : '' ?>"></span>
          <span class="<?= $step >= 2 ? 'on' : '' ?>"></span>
          <span class="<?= $step >= 3 ? 'on' : '' ?>"></span>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

  <p style="margin-top:24px;color:var(--ink-soft);font-size:.85rem"><a href="/">← Вернуться в каталог</a></p>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
