<?php
/* Динамический sitemap.xml — все активные товары, статические страницы.
   Стиль: canonical 2026 (Google XML sitemap protocol). */
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$base = 'https://flowers.interfood-catering.ru';
header('Content-Type: application/xml; charset=UTF-8');

$urls = [
    ['loc' => $base . '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => $base . '/policy', 'priority' => '0.3', 'changefreq' => 'yearly'],
    ['loc' => $base . '/offer', 'priority' => '0.3', 'changefreq' => 'yearly'],
];

$products = db()->query("SELECT slug, is_urgent FROM products WHERE is_active = 1 ORDER BY sort, id")->fetchAll();
foreach ($products as $p) {
    $urls[] = ['loc' => $base . '/product/' . rawurlencode($p['slug']), 'priority' => '0.8', 'changefreq' => 'daily'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . e($u['loc']) . '</loc>' . "\n";
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';
