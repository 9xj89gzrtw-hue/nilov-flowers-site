<?php
/* Честный 404 (критик security: catch-all отдавал главную с 200 на /img/nope.png, /.env и пр.
   — SEO-мусор и сокрытие роутинга). Статус 404 + понятная страница с выходом в магазин.
   Редизайн 5cv (W96/T2-d): каркас сайта + центрированная карточка. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

http_response_code(404);
$pageTitle = 'Страница не найдена — ' . setting('shop_name', 'Nilov Flowers');
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta name="robots" content="noindex,follow">
<title><?= e($pageTitle) ?></title>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap" style="max-width:560px;text-align:center">
      <p aria-hidden="true" style="margin:0;font-weight:800;font-size:clamp(4.5rem,16vw,7.5rem);line-height:1;letter-spacing:-.04em;color:var(--pink)">404</p>
      <h1 class="page-hero__title" style="margin-bottom:10px">Страница не найдена</h1>
      <p class="section-sub" style="margin:0 auto 24px">Такой страницы нет. Зато свежие букеты — на главной.</p>
      <a class="btn btn--accent" href="/">На главную</a>
    </div>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
