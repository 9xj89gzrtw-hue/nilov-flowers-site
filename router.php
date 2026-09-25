<?php
/* Роутер для встроенного сервера PHP (php -S 127.0.0.1:8123 router.php).
   На хостинге с Apache работает .htaccess — этот файл там не нужен. */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?: '';

/* W97-fixB1 (B1-4a): /index.php — дубль главной → 301 на каноничный / (query сохраняем;
   паритет с .htaccess прода). ОБЯЗАТЕЛЬНО до is_file: иначе физический index.php
   отдасться со статусом 200 и дубль останется в выдаче. */
if ($path === '/index.php') {
    header('Location: /' . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

if (is_file(__DIR__ . $path)) {
    return false; // физический файл — отдать как есть
}

/* W97-fixB1 (B1-4b): канонизация дублей витринных URL — 301 на нижний регистр без
   хвостового слэша (/product/slug/ и /PRODUCT/SLUG раньше отдавали 404 вместо 301;
   /product/slug/ на проде уже редиректится .htaccess — здесь паритет).
   Аккуратно: ТОЛЬКО витринные маршруты (/product|occasion|category/*, /track,
   /policy, /offer, /order-thanks, /; /help оставляем в списке — старые ссылки
   любого регистра приходят сюда нижним регистром и дальше 301 → /admin/help.php).
   /admin, /api и статика не канонизируем.
   Проверка «каноничный путь — валидный маршрут» отсекает мусорные пути: /TRACKS не
   редиректится, а честно 404. */
$isStorefrontRoute = static function (string $p): bool {
    if (in_array($p, ['/', '/help', '/track', '/policy', '/offer', '/order-thanks'], true)) {
        return true;
    }
    return (bool)preg_match('#^/(?:product|occasion|category)/[a-z0-9\-]+$#', $p);
};
$canonicalPath = mb_strtolower(rtrim($path, '/'), 'UTF-8');
if ($canonicalPath !== $path && $canonicalPath !== '' && $isStorefrontRoute($canonicalPath)) {
    header('Location: ' . $canonicalPath . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
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
/* W97-fixB3a (B3a-2): /help — инструкция владельца переехала под админку
   (/admin/help.php): кука сессии имеет path=/admin — вне /admin страницу было
   не открыть под залогиненным админом (хвост B1). Публичный /help — 301 на новое
   место (паритет с прод-ревайтом .htaccess), query сохраняем. */
if ($path === '/help') {
    header('Location: /admin/help.php' . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
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
/* W97-fixB3b (B3b-1c): посадочные страницы категорий /category/{slug} — паттерн /occasion/ */
if (preg_match('#^/category/([a-z0-9\-]+)$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/category.php';
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
