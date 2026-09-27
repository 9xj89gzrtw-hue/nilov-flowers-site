<?php
/* Честный 404 (критик security: catch-all отдавал главную с 200 на /img/nope.png, /.env и пр.
   — SEO-мусор и сокрытие роутинга). Статус 404 + понятная страница с выходом в магазин.
   Редизайн 5cv (W96/T2-d): каркас сайта + центрированная карточка.
   W105-b (5-b): craft — числовой якорь Playfair italic (тихий editorial, не
   «неоновая сигнализация» Montserrat-800), H1 с первым словом-акцентом
   (семейный паттерн лендингов), петал-бёрст — сигнатура магазина
   (тот же приём, что на странице благодарности). */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

http_response_code(404);
$pageTitle = 'Страница не найдена — ' . setting('shop_name', 'Nilov Flowers');
/* W105-b (5-b): display-ДНК вторичек (utility-ступень H1, .error-code) —
   secondary.css, версия — md5-хэш (паттерн product.php) */
$secondaryCssV = substr((string)@md5_file(__DIR__ . '/css/secondary.css'), 0, 8);
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta name="robots" content="noindex,follow">
<title><?= e($pageTitle) ?></title>
<?php require __DIR__ . '/partials/head.php'; ?>
<link rel="stylesheet" href="/css/secondary.css?v=<?= e($secondaryCssV) ?>">
</head>
<body class="page-utility">
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap" style="max-width:560px;text-align:center">
      <p class="error-code" aria-hidden="true">404</p>
      <h1 class="page-hero__title"><em class="page-hero__accent">Страница</em> не найдена</h1>
      <p class="section-sub" style="margin:0 auto 24px">Такой страницы нет. Зато свежие букеты — на главной.</p>
      <a class="btn btn--accent" href="/">На главную</a>
    </div>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php /* W105-b (5-b): петал-бёрст — сигнатура магазина и на ошибке: несколько
   лепестков вспархивают из-под числового якоря (600мс после load — страница
   сначала читается). Ядро NF_BURST.petals() — js/kinetic.js (defer, все
   страницы); спрайты — .nf-burst-petal из css/motion-w104.css;
   prefers-reduced-motion выключен в модуле и CSS-гвардом display:none. */ ?>
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    if (!window.NF_BURST || typeof window.NF_BURST.petals !== 'function') return;
    window.NF_BURST.petals({
      x: Math.round(window.innerWidth / 2),
      y: Math.round(window.innerHeight * 0.28),
      count: 6 + Math.round(Math.random() * 3),
      dist: [60, 150],
      fall: [130, 240],
      dur: [1100, 1400]
    });
  }, 600);
});
</script>
</body>
</html>
