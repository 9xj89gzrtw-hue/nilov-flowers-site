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
if ($path === '/api/payment/create') {
    require __DIR__ . '/api/payment-create.php';
    return true;
}
if (preg_match('#^/product/([a-z0-9\-]+)$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/product.php';
    return true;
}
if ($path === '/order-thanks') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    $shopName = 'Nilov Flowers';
    if (is_file(__DIR__ . '/includes/config.php')) {
        require_once __DIR__ . '/includes/config.php';
        require_once __DIR__ . '/includes/db.php';
        require_once __DIR__ . '/includes/util.php';
        $shopName = setting('shop_name', $shopName);
    }
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Спасибо за заказ — ' . htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') . '</title></head>'
        . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#F6F1E6;color:#2B2D2F">'
        . '<div style="text-align:center;padding:32px">'
        . '<h1 style="font-size:2rem;margin-bottom:8px">Спасибо за заказ!</h1>'
        . '<p style="color:#6E6A61">Мы свяжемся с вами в ближайшее время для подтверждения.</p>'
        . '<p style="margin-top:20px"><a href="/" style="color:#E2799C;font-weight:600">Вернуться в магазин</a></p>'
        . '</div></body></html>';
    return true;
}

/* Критик security: неизвестные пути — честный 404 (как в .htaccess на проде),
   раньше любой мусор (/img/nope.png, /.env) отдавал главную с 200. */
require __DIR__ . '/404.php';
