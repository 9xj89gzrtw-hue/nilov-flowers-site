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
<link id="faviconLink" rel="icon" href="<?= e($iconHref) ?>?v=<?= e(substr(md5_file(__DIR__ . '/../' . ltrim($iconHref, '/')), 0, 8)) ?>" sizes="any" data-base-icon="<?= e($iconHref) ?>?v=<?= e(substr(md5_file(__DIR__ . '/../' . ltrim($iconHref, '/')), 0, 8)) ?>">
<link rel="icon" type="image/svg+xml" href="/img/favicon.svg?v=<?= e(substr(md5_file(__DIR__ . '/../img/favicon.svg'), 0, 8)) ?>">
<link rel="apple-touch-icon" href="/img/favicon-180.png?v=<?= e(substr(md5_file(__DIR__ . '/../img/favicon-180.png'), 0, 8)) ?>">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#F6F1E6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Nilov Flowers">
<?php /* layout-критик 768px + RF-remediation: шрифты локализованы (Google Fonts display=swap
   = CLS-лавина при подгрузке). fonts.css со своим :root-стеком подключается ПОСЛЕ style.css. */ ?>
<link rel="preload" href="/fonts/GolosText-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/PlayfairDisplay-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="/css/nilov.css">
<link rel="stylesheet" href="/css/fonts.css">
<script src="/js/pwa-register.js" defer></script>
<meta property="og:site_name" content="<?= e($shopName) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="ru_RU">
<?php require __DIR__ . '/../includes/metrika.php'; ?>
