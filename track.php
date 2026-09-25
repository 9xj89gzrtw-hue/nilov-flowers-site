<?php
/**
 * Страница трекинга заказа «Где мой заказ?» (критерий 13, паттерн branded tracking 2026).
 * Покупатель вводит телефон последнего заказа → видит статус + состав + сумму.
 * Без пароля: маскируем чувствительное (адрес → только район), максимум 5 записей.
 * Редизайн 5cv (W96/T2-d): fc-section + форма в карточке .order-form (поля уже перекрашены).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

/* W98-fixE (E5): телефон читаем только из POST — ПД не попадают в URL и логи
   доступа (GET /track?phone=… уходит в историю). Просто перерисовка — введённый
   номер остаётся в value, клавиша Enter отправляет форму (submit-кнопка). */
$phone = trim($_POST['phone'] ?? '');
$orders = [];
$rlLimited = false;
$rlWaitMin = 1;
$normalized = preg_replace('/\D+/', '', $phone);
/* Legal/security-критик W40: поиск по 10-значному «хвосту» LIKE '%tail' был оракулом —
   перебором хвостов можно было смотреть чужие заказы. Теперь: полная длина (10-11 цифр)
   и точное совпадение нормализованного номера (8↔7 эквивалентны), плюс rate-limit. */
/* W97-fixB1 (B1-2): квота rate-limit тратится ТОЛЬКО на реальный поиск (телефон задан и
   валиден по длине) — простой просмотр страницы бакет не сжигает (паттерн api/orders:
   невалидные попытки квоту не инкрементируют). Сообщение честное: N минут из Retry-After
   (rl_retry_after возвращает секунды, кратные минуте), а не «подождите минуту». */
if (strlen($normalized) === 10 || strlen($normalized) === 11) {
    if (!rl_check('track', 10, 600)) {
        $rlLimited = true;
        $rlWaitSec = max(60, rl_retry_after('track', 600));
        $rlWaitMin = max(1, (int)ceil($rlWaitSec / 60));
        http_response_code(429);
        header('Retry-After: ' . $rlWaitSec);
    } else {
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
}

$shopPhone = setting('shop_phone', '');
$pageTitle = 'Где мой заказ? — ' . setting('shop_name', 'Nilov Flowers');

/* W99-fixG (G12): статусы — чистый текст без emoji (цвет статуса несёт
   пилюля .track-card__status); «Шаг N из 3» — отдельная visually-hidden строка */
$statusText = [
    'new' => 'Новый',
    'confirmed' => 'Подтверждён',
    'in_progress' => 'В работе',
    'done' => 'Выполнен',
    'canceled' => 'Отменён',
    'unredeemed' => 'Не выкуплен',
];
function trackStep(string $status): int {
    return match ($status) {
        'new' => 1, 'confirmed' => 2, 'done' => 3, default => 0,
    };
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex, follow">
<meta name="description" content="Проверьте статус заказа букета по номеру телефона — Nilov Flowers, доставка цветов в Санкт-Петербурге.">
<?php require __DIR__ . '/partials/head.php'; ?>
<style>
/* Локальные стили результатов трекинга (fc-* их не покрывает); цвета — токены 5cv */
.track-card{border:1.5px solid var(--line);border-radius:16px;padding:18px 20px;margin-top:12px;background:#fff}
.track-card__top{display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap}
.track-card__id{font-weight:700;color:var(--ink)}
.track-card__status{padding:3px 12px;border-radius:999px;font-size:.8rem;font-weight:700;white-space:nowrap}
.track-card__status.new{background:var(--surface-warm);color:var(--pink-dark)}
.track-card__status.confirmed{background:#e8f0fb;color:#3a6db3}
.track-card__status.done{background:#e7f5ec;color:#2e7d4f}
.track-card__status.canceled,.track-card__status.unredeemed{background:var(--surface-subtle);color:var(--ink-muted)}
.track-card__meta{color:var(--ink-muted);font-size:.88rem;margin:8px 0 0;line-height:1.55}
.track-steps{display:flex;gap:4px;margin-top:14px}
.track-steps span{flex:1;height:6px;border-radius:3px;background:var(--line)}
.track-steps span.on{background:var(--pink)}
/* K8 (W101): видимая подпись шага — «Шаг N из 3 · Статус» (раньше «Шаг N из 3»
   жила только в visually-hidden — глаз видел безымянные точки) */
.track-steps__label{margin:7px 0 0;font-size:.8rem;font-weight:600;color:var(--ink-muted)}
/* W99-fixG (G12): визуально-скрытый текст для скринридера (класс sr-only в css/ нет) */
.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.track-empty{padding:36px 20px;text-align:center;color:var(--ink-muted);border:1.5px dashed var(--line);border-radius:16px;margin-top:16px}
</style>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap" style="max-width:560px">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> / <span aria-current="page">Где мой заказ?</span>
      </nav>
      <div class="page-hero">
        <h1 class="page-hero__title">Где мой заказ?</h1>
        <p class="section-sub">Введите телефон, который указали при оформлении, — покажем статус ваших последних заказов.</p>
      </div>

      <form class="order-form" method="post" action="/track" style="grid-template-columns:1fr">
        <div class="order-form__field">
          <label for="trackPhone">Телефон из заказа</label>
          <input type="tel" id="trackPhone" name="phone" value="<?= e($phone) ?>" placeholder="+7 (900) 123-45-67" required>
          <span class="order-form__hint">Без пароля: показываем только район доставки</span>
        </div>
        <button type="submit" class="btn btn--accent order-form__submit">Проверить статус</button>
      </form>

      <?php if ($rlLimited): ?>
        <div class="track-empty">Слишком много проверок подряд — подождите <?= (int)$rlWaitMin ?> мин. и попробуйте снова. Если срочно — позвоните: <a href="tel:<?= e($shopPhone) ?>"><?= e($shopPhone) ?></a></div>
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
              <span class="track-card__id">Заказ № <?= (int)$o['id'] ?> · <?= e((static function (string $iso): string {
            /* W101 (редактор): сырая SQL-дата — не для покупателя */
            $m = [1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'];
            $t = strtotime($iso);
            return $t === false ? $iso : sprintf('%d %s, %s', (int)date('j', $t), $m[(int)date('n', $t)], date('H:i', $t));
        })((string)$o['created_at'])) ?></span>
              <span class="track-card__status <?= e($o['status']) ?>"><?= e($statusText[$o['status']] ?? $o['status']) ?></span>
            </div>
            <p class="track-card__meta">
              <?= e($addr) ?> · Сумма: <strong><?= formatPrice((int)$o['total']) ?></strong>
              <?php if ($o['status'] === 'new'): ?><br>Мы свяжемся с вами для подтверждения в течение 15 минут.
              <?php elseif ($o['status'] === 'confirmed'): ?><br>Букет собираем — фото пришлём перед отправкой.
              <?php elseif ($o['status'] === 'done'): ?><br>Доставлено. Спасибо, что выбираете нас! 💐
              <?php endif; ?>
            </p>
            <?php if ($step > 0): ?>
            <?php /* W99-fixG (G12): точки-прогресс — декоративны (aria-hidden).
                   K8 (W101): видимый текст «Шаг N из 3 · {Статус}» под точками —
                   статус с названием (фактические статусы из кода: Новый →
                   Подтверждён → Выполнен), SR читает ту же строку. */ ?>
            <div class="track-steps" aria-hidden="true">
              <span class="<?= $step >= 1 ? 'on' : '' ?>"></span>
              <span class="<?= $step >= 2 ? 'on' : '' ?>"></span>
              <span class="<?= $step >= 3 ? 'on' : '' ?>"></span>
            </div>
            <p class="track-steps__label">Шаг <?= (int)$step ?> из 3 · <?= e($statusText[$o['status']] ?? $o['status']) ?></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>

      <p style="margin-top:24px;color:var(--ink-soft);font-size:.85rem"><a href="/">← Вернуться в каталог</a></p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
