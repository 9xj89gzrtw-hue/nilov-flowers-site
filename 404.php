<?php
/* Честный 404 (критик security: catch-all отдавал главную с 200 на /img/nope.png, /.env и пр.
   — SEO-мусор и сокрытие роутинга). Статус 404 + понятная страница с выходом в магазин. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

http_response_code(404);
$canonicalUrl = 'https://flowers.interfood-catering.ru/';
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,follow">
<title>Страница не найдена — <?= e(setting('shop_name', 'Nilov Flowers')) ?></title>
<link rel="icon" href="/img/favicon-32.png" sizes="32x32">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Golos+Text:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#F6F1E6;--ink:#2B2D2F;--ink-soft:#6E6A61;--rose:#AE4A71;--font-display:'Playfair Display',Georgia,serif;--font-ui:'Golos Text',system-ui,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-ui);background:var(--bg);color:var(--ink);min-height:100vh;display:grid;place-items:center;padding:24px;text-align:center}
h1{font-family:var(--font-display);font-size:clamp(2.4rem,8vw,4.6rem);margin-bottom:.3em}
p{color:var(--ink-soft);max-width:34ch;margin:0 auto 1.6rem;font-size:1rem}
a.btn{display:inline-block;background:var(--rose);color:#fff;border-radius:999px;padding:14px 30px;font:600 .95rem var(--font-ui);text-decoration:none}
a.btn:hover{background:#9E4062}
:focus-visible{outline:2px solid var(--rose);outline-offset:2px}
</style>
</head>
<body>
  <div>
    <h1>404</h1>
    <p>Такой страницы нет. Зато свежие букеты — на главной.</p>
    <a class="btn" href="/">В каталог</a>
  </div>
</body>
</html>
