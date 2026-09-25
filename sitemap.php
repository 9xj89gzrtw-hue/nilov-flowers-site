<?php
/* Динамический sitemap.xml — главная, активные товары, статические страницы.
   lastmod товара — по последнему заказу с этим товаром, иначе — сегодня.
   Отдаётся с Content-Type: application/xml. */
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$base = 'https://flowers.interfood-catering.ru';
header('Content-Type: application/xml; charset=UTF-8');

$today = date('Y-m-d');
/* W96-fix3a (T5a): /policy и /offer убраны — страницы noindex (robots),
   в sitemap им делать нечего (mixed signals для поисковиков). */
$urls = [
    ['loc' => $base . '/', 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => $today],
];

$products = db()->query(
    "SELECT p.slug, MAX(o.created_at) AS last_order
     FROM products p
     LEFT JOIN order_items oi ON oi.product_id = p.id
     LEFT JOIN orders o ON o.id = oi.order_id
     WHERE p.is_active = 1
     GROUP BY p.id
     ORDER BY MIN(p.sort), p.id"
)->fetchAll();
foreach ($products as $p) {
    $lastmod = $p['last_order'] !== null ? date('Y-m-d', strtotime($p['last_order'])) : $today;
    $urls[] = [
        'loc' => $base . '/product/' . rawurlencode($p['slug']),
        'priority' => '0.8',
        'changefreq' => 'daily',
        'lastmod' => $lastmod,
    ];
}

try {
    foreach (db()->query('SELECT slug FROM occasions WHERE active = 1 ORDER BY sort, id')->fetchAll() as $o) {
        $urls[] = ['loc' => $base . '/occasion/' . rawurlencode($o['slug']), 'priority' => '0.7', 'changefreq' => 'weekly', 'lastmod' => $today];
    }
} catch (Throwable $e) { /* старая БД без таблицы — не роняем sitemap */ }

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . e($u['loc']) . '</loc>' . "\n";
    echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';
