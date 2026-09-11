<?php
/* Общий head всех витринных страниц: кодировка, вьюпорт, стили, шрифты, favicon, OG-база.
   Canonical и метатеги верификации: страница передаёт $canonicalUrl до require;
   если не задан — canonical не печатается. */
$shopName = setting('shop_name', 'Nilov Flowers');
$favicon = setting('site_favicon', '');
$iconHref = $favicon !== '' ? '/img/uploads/' . rawurlencode($favicon) : '/img/favicon.ico';
$yandexVerification = trim(setting('yandex_verification', ''));
$googleVerification = trim(setting('google_site_verification', ''));
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<?php if (!empty($canonicalUrl)): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<?php if ($yandexVerification !== ''): ?><meta name="yandex-verification" content="<?= e($yandexVerification) ?>">
<?php endif; ?>
<?php if ($googleVerification !== ''): ?><meta name="google-site-verification" content="<?= e($googleVerification) ?>">
<?php endif; ?>
<link rel="icon" href="<?= e($iconHref) ?>" sizes="any">
<link rel="icon" type="image/svg+xml" href="/img/favicon.svg">
<link rel="apple-touch-icon" href="/img/favicon-180.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#F6F1E6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Nilov Flowers">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="/css/nilov.css">
<script src="/js/pwa-register.js" defer></script>
<meta property="og:site_name" content="<?= e($shopName) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="ru_RU">
<?php require __DIR__ . '/../includes/metrika.php'; ?>
