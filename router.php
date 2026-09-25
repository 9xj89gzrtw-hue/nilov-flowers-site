<?php
/* Роутер для встроенного сервера PHP (php -S 127.0.0.1:8123 router.php).
   На хостинге с Apache работает .htaccess — этот файл там не нужен. */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

if (is_file(__DIR__ . $path)) {
    return false; // физический файл — отдать как есть
}

if ($path === '/api/orders') {
    require __DIR__ . '/api/orders.php';
    return true;
}
if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}
if ($path === '/api/promo') {
    require __DIR__ . '/api/promo.php';
    return true;
}
/* Паритет с прод-ревайтами .htaccess (verify-скрипт ходил по этим путям локально) */
if ($path === '/offer') {
    require __DIR__ . '/offer_new.php';
    return true;
}
if ($path === '/policy') {
    require __DIR__ . '/policy_new.php';
    return true;
}
if ($path === '/track') {
    require __DIR__ . '/track.php';
    return true;
}
/* W96: /help — паритет с прод-ревайтом (.htaccess) */
if ($path === '/help') {
    require __DIR__ . '/help.php';
    return true;
}
if ($path === '/api/payment/create') {
    require __DIR__ . '/api/payment-create.php';
    return true;
}
if (preg_match('#^/occasion/([a-z0-9\-]+)$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/occasion.php';
    return true;
}
if (preg_match('#^/product/([a-z0-9\-]+)$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/product.php';
    return true;
}
if ($path === '/order-thanks') {
    /* W96-fix3: паритет с прод-ревайтом — настоящая страница с номером заказа,
       «Что дальше» и ссылкой на /track (была инлайн-заглушка). */
    require __DIR__ . '/order-thanks.php';
    return true;
}

/* Критик security: неизвестные пути — честный 404 (как в .htaccess на проде),
   раньше любой мусор (/img/nope.png, /.env) отдавал главную с 200. */
require __DIR__ . '/404.php';
