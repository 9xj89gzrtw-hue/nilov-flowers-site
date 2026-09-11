<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $new = !is_file(DB_PATH);
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        if ($new) {
            seedDatabase($pdo);
        }
        migrateSchema($pdo);
    }
    return $pdo;
}

function seedDatabase(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            sort INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            price INTEGER NOT NULL DEFAULT 0,
            sale_price INTEGER,
            description TEXT NOT NULL DEFAULT '',
            image TEXT NOT NULL DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1,
            sort INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS delivery_zones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            price INTEGER NOT NULL DEFAULT 0,
            sort INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            customer_name TEXT NOT NULL,
            phone TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            delivery_zone_id INTEGER REFERENCES delivery_zones(id) ON DELETE SET NULL,
            delivery_address TEXT NOT NULL DEFAULT '',
            comment TEXT NOT NULL DEFAULT '',
            payment_method TEXT NOT NULL DEFAULT 'cash',
            total INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'new',
            payment_token TEXT NOT NULL DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
            product_id INTEGER,
            name TEXT NOT NULL,
            price INTEGER NOT NULL,
            qty INTEGER NOT NULL DEFAULT 1
        );
        CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_items_order ON order_items(order_id);
    ");

    $pdo->exec("INSERT INTO categories (name, sort) VALUES
        ('Розы', 10), ('Сборные букеты', 20), ('Полевые цветы', 30)");

    $pdo->exec("INSERT INTO products (category_id, name, slug, price, sale_price, description, sort) VALUES
        (1, 'Букет из роз', 'buket-iz-roz', 2500, NULL, 'Классический букет из свежих роз с утренней поставки.', 10),
        (2, 'Весенний этюд', 'vesennij-etyud', 2500, 2100, 'Нежный сборный букет в весенней палитре.', 20),
        (3, 'Полевые цветы', 'polevye-tsvety', 2100, NULL, 'Лёгкий букет из полевых цветов — просто и со вкусом.', 30)");

    $pdo->exec("INSERT INTO settings (key, value) VALUES
        ('shop_name', 'Nilov Flowers'),
        ('shop_phone', '+7 (900) 000-00-00'),
        ('shop_address', 'г. Санкт-Петербург, Полевая Сабировская ул., 47, корп. 1'),
        ('hero_title', 'Свежие цветы с утренней поставки'),
        ('hero_subtitle', 'Соберём и доставим букет в течение дня — к празднику или просто так'),
        ('hero_button_text', 'Выбрать букет'),
        ('hero_button_link', '#catalog'),
        ('hero_image', ''),
        ('logo_image', ''),
        ('steps_title', 'Как это работает'),
        ('step_1', 'Выбираете букет в каталоге и добавляете в корзину'),
        ('step_2', 'Оформляете заказ — мы связываемся с вами для подтверждения'),
        ('step_3', 'Собираем букет и доставляем в выбранный район'),
        ('guarantees_title', 'Гарантии'),
        ('guarantee_1', 'Фото букета перед отправкой'),
        ('guarantee_2', 'Свежие цветы с утренней поставки'),
        ('guarantee_3', 'Заменяем увядшие в день доставки')");

    $pdo->exec("INSERT INTO delivery_zones (name, price, sort) VALUES
        ('Центральный', 300, 10), ('Северный', 400, 20), ('Южный', 400, 30)");

    /* Идемпотентные дополнения (миграция для существующих БД):
       новые settings-ключи + демо-фото для seed-продуктов. */
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES
        ('cart_mode', 'drawer'),
        ('shop_whatsapp', ''),
        ('shop_telegram', ''),
        ('legal_subject_type', ''),
        ('legal_name', ''),
        ('legal_number', ''),
        ('legal_address', ''),
        ('legal_contact_email', '')");
    $pdo->exec("UPDATE products SET image = 'roz.jpg' WHERE slug = 'buket-iz-roz' AND image = ''");
    $pdo->exec("UPDATE products SET image = 'p2.jpg' WHERE slug = 'vesennij-etyud' AND image = ''");
    $pdo->exec("UPDATE products SET image = 'p3.jpg' WHERE slug = 'polevye-tsvety' AND image = ''");
}

/* Миграции для существующих БД: колонки заказов под подтверждение вручения,
   флаг срочности товара и новые settings-ключи. Выполняется при каждом
   первом db() в запросе — дёшево (PRAGMA table_info) и идемпотентно. */
function migrateSchema(PDO $pdo): void
{
    $orderCols = array_column($pdo->query("PRAGMA table_info(orders)")->fetchAll(), 'name');
    foreach ([
        ['given_to', "TEXT NOT NULL DEFAULT ''"],
        ['no_photo_reason', "TEXT NOT NULL DEFAULT ''"],
        ['handover_photo', "TEXT NOT NULL DEFAULT ''"],
    ] as [$col, $def]) {
        if (!in_array($col, $orderCols, true)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN {$col} {$def}");
        }
    }
    $prodCols = array_column($pdo->query("PRAGMA table_info(products)")->fetchAll(), 'name');
    if (!in_array('is_urgent', $prodCols, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN is_urgent INTEGER NOT NULL DEFAULT 0');
    }
    /* settings-ключи, появившиеся после первой версии (INSERT OR IGNORE) */
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES
        ('cart_mode', 'drawer'),
        ('shop_whatsapp', ''),
        ('shop_telegram', ''),
        ('legal_subject_type', ''),
        ('legal_name', ''),
        ('legal_number', ''),
        ('legal_address', ''),
        ('legal_contact_email', ''),
        ('hero_text_enabled', '1'),
        ('notify_enabled', '1'),
        ('notify_email', ''),
        ('tg_chat_id', ''),
        ('max_chat_id', ''),
        ('telegram_bot_token', ''),
        ('max_api_token', ''),
        ('quiet_from', ''),
        ('quiet_to', ''),
        ('shop_timezone', 'Europe/Moscow'),
        ('shop_hours', ''),
        ('shop_email', ''),
        ('shop_vk', ''),
        ('shop_max_link', ''),
        ('header_phone', ''),
        ('header_address', ''),
        ('vat_rate', 'none'),
        ('yk_shop_id', ''),
        ('yk_secret_key', ''),
        ('upsell_enabled', '1'),
        ('upsell_limit', '3'),
        ('upsell_title', 'Возможно, пригодится'),
        ('upsell_categories', ''),
        ('yandex_reviews_id', ''),
        ('yandex_reviews_enabled', '0'),
        ('site_favicon', ''),
        ('yk_enabled', '0'),
        ('shop_instagram', ''),
        /* Включатели соцсетей (1 = показывать на сайте) */
        ('wa_enabled', '1'),
        ('tg_enabled', '1'),
        ('vk_enabled', '1'),
        ('max_enabled', '1'),
        ('ig_enabled', '1'),
        ('email_enabled', '1')");
    /* push-подписки Web Push (VAPID) */
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        endpoint TEXT UNIQUE NOT NULL,
        sub_json TEXT NOT NULL,
        created_at TEXT DEFAULT (datetime('now'))
    )");
    /* admin_users: email-логин, имя, роль, уведомления, запасной email */
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL
    )");
    $auCols = array_column($pdo->query("PRAGMA table_info(admin_users)")->fetchAll(), 'name');
    foreach ([
        ['email', "TEXT NOT NULL DEFAULT ''"],
        ['name', "TEXT NOT NULL DEFAULT ''"],
        ['role', "TEXT NOT NULL DEFAULT 'owner'"],
        ['notify_enabled', "INTEGER NOT NULL DEFAULT 1"],
        ['notify_email', "TEXT NOT NULL DEFAULT ''"],
        ['backup_email', "TEXT NOT NULL DEFAULT ''"],
    ] as [$col, $def]) {
        if (!in_array($col, $auCols, true)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN {$col} {$def}");
        }
    }
    /* admin_users: дата добавления (created_at), idempotent-миграция.
       ALTER TABLE ADD COLUMN не допускает datetime('now') — дефолт константный. */
    $auCols2 = array_column($pdo->query("PRAGMA table_info(admin_users)")->fetchAll(), 'name');
    if (!in_array('created_at', $auCols2, true)) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN created_at TEXT NOT NULL DEFAULT ''");
        $pdo->exec("UPDATE admin_users SET created_at = datetime('now','localtime') WHERE created_at = ''");
    }
    /* password_resets: одноразовые токены (хеш, TTL) — паттерн codeshack.io 2026 */
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL,
        expires_at TEXT NOT NULL,
        used INTEGER NOT NULL DEFAULT 0
    )");
    /* login_attempts: rate-limit 5/15мин по IP */
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        attempted_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now','localtime','-15 minutes')");
    /* orders: фискальные чеки ЮKassa (предоплата/зачёт) */
    $ordCols = array_column($pdo->query("PRAGMA table_info(orders)")->fetchAll(), 'name');
    foreach ([
        ['receipt_prepay', "TEXT NOT NULL DEFAULT ''"],
        ['receipt_offset', "TEXT NOT NULL DEFAULT ''"],
    ] as [$col, $def]) {
        if (!in_array($col, $ordCols, true)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN {$col} {$def}");
        }
    }
    /* products: галочка «показывать в апсейле корзины» */
    $prodCols2 = array_column($pdo->query("PRAGMA table_info(products)")->fetchAll(), 'name');
    if (!in_array('show_in_upsell', $prodCols2, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN show_in_upsell INTEGER NOT NULL DEFAULT 0');
    }
}
