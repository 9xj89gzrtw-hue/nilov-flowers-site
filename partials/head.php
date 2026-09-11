<?php
/* Общий head всех витринных страниц: кодировка, вьюпорт, стили, шрифты, favicon, OG-база */
$shopName = setting('shop_name', 'Nilov Flowers');
$favicon = setting('site_favicon', '');
$iconHref = $favicon !== '' ? '/img/uploads/' . rawurlencode($favicon) : '/img/favicon.ico';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<link rel="icon" href="<?= e($iconHref) ?>" sizes="any">
<link rel="icon" type="image/svg+xml" href="/img/favicon.svg">
<link rel="apple-touch-icon" href="/img/favicon-180.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css">
<meta property="og:site_name" content="<?= e($shopName) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="ru_RU">
