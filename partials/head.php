<?php
/* Общий head всех витринных страниц: кодировка, вьюпорт, стили, шрифты, favicon, OG-база.
   Canonical и метатеги верификации: страница передаёт $canonicalUrl до require;
   если не задан — canonical не печатается. */
$shopName = setting('shop_name', 'Nilov Flowers');
$favicon = setting('site_favicon', '');
$iconHref = $favicon !== '' ? '/img/uploads/' . rawurlencode($favicon) : '/img/favicon.ico';
$yandexVerification = trim(setting('yandex_verification', ''));
$googleVerification = trim(setting('google_site_verification', ''));
/* W96-fix3a (T4): версионирование CSS — ?v= из md5-хэша файла (паттерн favicon).
   immutable-кэш (T3 .htaccess) безопасен: смена файла меняет URL, кэш инвалидируется. */
$styleCssV = substr((string)@md5_file(__DIR__ . '/../css/style.css'), 0, 8);
$nilovCssV = substr((string)@md5_file(__DIR__ . '/../css/nilov.css'), 0, 8);
$fiveCssV = substr((string)@md5_file(__DIR__ . '/../css/five.css'), 0, 8);
$fontsCssV = substr((string)@md5_file(__DIR__ . '/../css/fonts.css'), 0, 8);
/* W97-fixB1 (B1-5): twitter-мета — парные к og (тот же источник значения).
   twitter:title: product.php/occasion.php передают $pageTitle == og:title; главная
   $pageTitle не задаёт — берём seo_title с тем же дефолтом, что index.php печатает
   в og:title (раньше фолбэк был $shopName и twitter:title расходился с og:title).
   twitter:image: og:image печатает сама страница ДО require head.php (переменную не
   передаёт) — вычисляем тот же URL по контексту (include видит переменные страницы):
   товар — $img ($ogType=product), повод — $ocImg, главная — hero_image (как index.php).
   Страницы без og:image (track/help/offer/policy) twitter:image не получают. */
$twitterTitle = $pageTitle ?? setting('seo_title', 'Доставка цветов в СПб — ' . setting('shop_name', 'Nilov Flowers') . ' | Свежие букеты с доставкой сегодня');
$ogImageUrl = '';
if (isset($ogType) && $ogType === 'product' && isset($img) && is_string($img) && $img !== '') {
    $ogImageUrl = 'https://flowers.interfood-catering.ru' . $img;
} elseif (isset($ocImg) && is_string($ocImg) && $ocImg !== '') {
    $ogImageUrl = 'https://flowers.interfood-catering.ru' . $ocImg;
} elseif (isset($pageDescription) && setting('hero_image') !== '') {
    $ogImageUrl = 'https://flowers.interfood-catering.ru/img/uploads/' . rawurlencode(setting('hero_image'));
}
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
<meta name="theme-color" content="#ffffff">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Nilov Flowers">
<?php /* layout-критик 768px + RF-remediation: шрифты локализованы (Google Fonts display=swap
   = CLS-лавина при подгрузке). fonts.css со своим :root-стеком подключается ПОСЛЕ style.css. */ ?>
<?php /* W96-fix3a (T1): Playfair-preload удалён — дизайн 5cv использует только Montserrat
   (Golos остаётся: fallback-стек fonts.css). Шрифты Playfair в /fonts/ — для legacy-страниц админки. */ ?>
<?php /* W97-fixB1 (B1-5): мёртвые preload-хинты обоих подмножеств шрифта Golos (62КБ
   в critical path) убраны — витрина 5cv использует Montserrat; шрифт остаётся в
   fallback-стеке fonts.css и подтянется по unicode-range ТОЛЬКО при использовании. */ ?>
<?php /* W96/T2-a (5cv): Montserrat — основной шрифт нового дизайна. Preload только
   cyrillic-подмножества (23КБ): latin подтянется по unicode-range при латинице —
   не грузим лишнее на мобильных. */ ?>
<link rel="preload" href="/fonts/MontserratVariable-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/style.css?v=<?= e($styleCssV) ?>">
<link rel="stylesheet" href="/css/nilov.css?v=<?= e($nilovCssV) ?>">
<?php /* W96/T2-a: дизайн-система 5cv — ПОСЛЕ nilov.css (перекрывает той же специфичностью),
   ДО fonts.css (токены --font-ui закреплены в five.css на html:root — выше :root из fonts.css). */ ?>
<link rel="stylesheet" href="/css/five.css?v=<?= e($fiveCssV) ?>">
<link rel="stylesheet" href="/css/fonts.css?v=<?= e($fontsCssV) ?>">
<script src="/js/pwa-register.js" defer></script>
<meta property="og:site_name" content="<?= e($shopName) ?>">
<?php /* SEO-критик W86: страница товара печатает свой og:type=product ДО require head —
   не дублировать og:type=website (парсеры берут первый, валидаторы warn). */ ?>
<?php if (empty($ogType)): ?><meta property="og:type" content="website"><?php endif; ?>
<meta property="og:locale" content="ru_RU">
<?php /* SEO-критик W40: twitter/X и Telegram превью без twitter:card беднее — добавляем карточку */ ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($twitterTitle) ?>">
<?php /* W96-fix3a (T5d): twitter-превью парно к og:description — печатаем только если
   страница передала $pageDescription до require (сейчас — главная; og:description
   на остальных страницах печатается локально, не через переменную). */ ?>
<?php if (!empty($pageDescription)): ?><meta name="twitter:description" content="<?= e($pageDescription) ?>">
<?php endif; ?>
<?php /* W97-fixB1 (B1-5): twitter:image — тот же URL, что og:image (когда он задан) */ ?>
<?php if ($ogImageUrl !== ''): ?><meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
<?php endif; ?>
<?php require __DIR__ . '/../includes/metrika.php'; ?>
