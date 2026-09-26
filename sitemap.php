<?php
/* Динамический sitemap.xml — главная, активные товары, статические страницы.
   W99-fixG (G6): lastmod — ТОЛЬКО реальные даты из БД: у товара — последний заказ
   с этим товаром ИЛИ updated_at (обоих нет — тега нет вовсе, фиктивный «сегодня»
   удаляем); у главной — свежий updated_at товара (контент витрины = каталог);
   у поводов реальной даты нет (таблица без updated_at/заказов) — без lastmod.
   changefreq товаров: 23 демо-позиции не меняются ежедневно — daily → weekly.
   Отдаётся с Content-Type: application/xml. */
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$base = 'https://flowers.interfood-catering.ru';
header('Content-Type: application/xml; charset=UTF-8');

/* W96-fix3a (T5a): /policy и /offer убраны — страницы noindex (robots),
   в sitemap им делать нечего (mixed signals для поисковиков). */
/* Главная: lastmod — свежий updated_at товара (реальная дата контента витрины) */
$homeLast = db()->query('SELECT MAX(updated_at) FROM products WHERE is_active = 1')->fetchColumn();
$urls = [
    ['loc' => $base . '/', 'priority' => '1.0', 'changefreq' => 'daily',
     'lastmod' => ($homeLast !== null && $homeLast !== '' && $homeLast !== false) ? date('Y-m-d', strtotime((string)$homeLast)) : null],
];

$products = db()->query(
    "SELECT p.slug, p.updated_at, MAX(o.created_at) AS last_order
     FROM products p
     LEFT JOIN order_items oi ON oi.product_id = p.id
     LEFT JOIN orders o ON o.id = oi.order_id
     WHERE p.is_active = 1
     GROUP BY p.id
     ORDER BY MIN(p.sort), p.id"
)->fetchAll();
foreach ($products as $p) {
    /* Реальная дата: последний заказ с товаром ИЛИ обновление карточки;
       обоих нет — без lastmod (не выдумываем «сегодня») */
    $src = $p['last_order'] !== null && $p['last_order'] !== ''
        ? $p['last_order']
        : ($p['updated_at'] !== null && $p['updated_at'] !== '' ? $p['updated_at'] : null);
    $urls[] = [
        'loc' => $base . '/product/' . rawurlencode($p['slug']),
        'priority' => '0.8',
        'changefreq' => 'weekly',
        'lastmod' => $src !== null ? date('Y-m-d', strtotime($src)) : null,
    ];
}

try {
    foreach (db()->query('SELECT slug FROM occasions WHERE active = 1 ORDER BY sort, id')->fetchAll() as $o) {
        /* У поводов нет ни заказов, ни updated_at — честно без lastmod */
        $urls[] = ['loc' => $base . '/occasion/' . rawurlencode($o['slug']), 'priority' => '0.7', 'changefreq' => 'weekly', 'lastmod' => null];
    }
} catch (Throwable $e) { /* старая БД без таблицы — не роняем sitemap */ }

/* W97-fixB3b (B3b-1d): посадочные страницы категорий /category/{slug} — все категории,
   у которых есть хотя бы один активный товар (пустая категория = thin page для
   поисковика). lastmod — не выдумываем: дата свежего товара категории (MAX
   updated_at), нет данных — без lastmod вовсе. Слаг — на лету из name (в таблице
   колонки slug нет, паттерн slugify как у товаров). */
try {
    $catStmt = db()->query('SELECT c.name, MAX(p.updated_at) AS lastmod
        FROM categories c JOIN products p ON p.category_id = c.id AND p.is_active = 1
        GROUP BY c.id ORDER BY MIN(c.sort), c.id');
    foreach ($catStmt->fetchAll() as $c) {
        /* W103 (критик-9 P1): lastmod='' (категория без правок товаров) давал
           strtotime('')=false → date(false)=TypeError под strict_types → молчаливый
           catch обрывал цикл: в sitemap оставалась только первая категория. */
        $catLm = null;
        if (!empty($c['lastmod'])) {
            $ts = strtotime((string)$c['lastmod']);
            if ($ts !== false) $catLm = date('Y-m-d', $ts);
        }
        $urls[] = [
            'loc' => $base . '/category/' . rawurlencode(slugify((string)$c['name'])),
            'priority' => '0.7',
            'changefreq' => 'weekly',
            'lastmod' => $catLm,
        ];
    }
} catch (Throwable $e) { /* старая БД без колонок/таблиц — не роняем sitemap */ }

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . e($u['loc']) . '</loc>' . "\n";
    /* lastmod опционален (категории без свежих товаров — без даты, не выдумываем) */
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    }
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';
