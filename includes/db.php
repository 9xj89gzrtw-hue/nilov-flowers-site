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
        /* W85 (стресс-критик прогон 1): 20 параллельных POST в WAL без таймаута давали
           SQLSTATE[HY000] General error 5 (database is locked) с телом фатала в HTTP 200. */
        $pdo->exec('PRAGMA busy_timeout = 30000'); /* W87 stress: 5s мало для 20x write-шторма (было 11 HTTP 500 locked) -> 30s; WAL читаетей не блокирует */
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
        CREATE TABLE IF NOT EXISTS promo_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            kind TEXT NOT NULL DEFAULT 'percent',
            value INTEGER NOT NULL DEFAULT 0,
            min_order INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            max_uses INTEGER NOT NULL DEFAULT 0,
            used INTEGER NOT NULL DEFAULT 0
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

    /* Демо-каталог W96 (дизайн 5cv): категории под секции-карусели витрины.
       Владелец заменит товары через админку — сид только для свежих БД. */
    $pdo->exec("INSERT INTO categories (name, sort) VALUES
        ('Розы', 10), ('Сборные букеты', 20), ('Полевые цветы', 30),
        ('Авторские букеты', 40), ('В шляпной коробке', 50), ('Сладкие подарки', 60)");

    /* Демо-товары с флагами hit/premium/upsell — секции «Хиты»/«Премиум»/«Дополните букет»
       сразу живые. Фото gen*.jpg лежат в img/products (локальный дев-набор). */
    $pdo->exec("INSERT INTO products (category_id, name, slug, price, sale_price, description, image, is_hit, is_premium, show_in_upsell, sort) VALUES
        (1, 'Букет из роз', 'buket-iz-roz', 2500, NULL, 'Классический букет из свежих роз с утренней поставки.', 'roz2.jpg', 1, 0, 0, 10),
        (2, 'Весенний этюд', 'vesennij-etyud', 2500, 2100, 'Нежный сборный букет в весенней палитре.', 'p2.jpg', 0, 0, 0, 20),
        (3, 'Полевые цветы', 'polevye-tsvety', 2100, NULL, 'Лёгкий букет из полевых цветов — просто и со вкусом.', 'p3.jpg', 0, 0, 0, 30),
        (2, 'Букет из 7 альстромерий микс', 'buket-7-alstromerii-miks', 2900, NULL, 'Нежный микс альстромерий в пастельной гамме — лёгкий букет на каждый день.', 'gen7.jpg', 1, 0, 0, 40),
        (1, 'Букет из 19 красных роз с эвкалиптом', 'buket-19-roz-evkalipt', 4900, NULL, 'Эквадорские розы 50 см с эвкалиптом — классика для яркого признания.', 'gen8.jpg', 1, 0, 0, 50),
        (4, 'Букет из 51 розового пиона', 'buket-51-pion', 11900, NULL, 'Пышные розовые пионы в нежной обёртке — вау-эффект для особого случая.', 'gen9.jpg', 0, 1, 0, 60),
        (5, 'Гортензии микс в шляпной коробке', 'gortenzii-shlyapnaya-korobka', 8900, NULL, 'Белые и голубые гортензии в круглой коробке — статусный подарок.', 'gen10.jpg', 0, 1, 0, 70),
        (1, 'Букет из 5 розовых роз, Эквадор', 'buket-5-rozovyh-roz', 2300, NULL, 'Компактный букет из 5 роз — маленький знак внимания без повода.', 'gen11.jpg', 0, 0, 0, 80),
        (6, 'Мини-набор клубники в шоколаде', 'klubnika-shokolad-mini', 990, NULL, 'Клубника в бельгийском шоколаде — сладкое дополнение к букету.', 'gen12.jpg', 0, 0, 1, 90),
        (5, '9 красных роз в шляпной коробке', '9-roz-shlyapnaya-korobka', 5500, NULL, 'Классические красные розы в стильной круглой коробке с гипсофилой.', 'gen13.jpg', 1, 0, 0, 100),
        (2, 'Весенний букет из тюльпанов', 'vesennij-buket-tyulpany', 1900, NULL, 'Яркий весенний микс тюльпанов и нарциссов в крафте.', 'gen14.jpg', 0, 0, 1, 110),
        (2, 'Авторский букет «Розовое облако»', 'avtorskij-rozovoe-oblako', 4200, NULL, 'Монобукет в розовой гамме — собран нашим флористом утром.', 'gen1.jpg', 1, 0, 0, 120),
        (2, 'Букет «Белая нежность»', 'buket-belaya-nezhnost', 3500, NULL, 'Белые и кремовые цветы с эвкалиптом — спокойная элегантность.', 'gen2.jpg', 0, 0, 0, 130),
        (4, 'Пионовидная классика', 'pionovidnaya-klassika', 6800, NULL, 'Крупные пионовидные розы — букет, который запомнится.', 'gen3.jpg', 0, 1, 0, 140),
        (2, 'Солнечный микс', 'solnechnyj-miks', 2700, 2200, 'Жёлто-оранжевая гамма — букет-заряд бодрости.', 'gen4.jpg', 0, 0, 0, 150),
        (1, 'Красный акцент', 'krasnyj-akcent', 3800, NULL, 'Красные розы с зеленью — уместно всегда.', 'gen5.jpg', 0, 0, 0, 160),
        (2, 'Романтика', 'romantika', 3100, 2700, 'Пастельный букет с гипсофилой — нежность в каждой детали.', 'gen6.jpg', 0, 0, 0, 170)");

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
        ('logo_enabled', '1'),
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
    /* История настроек: снимок ВСЕХ settings перед каждым изменением из админки
       (критерий 16: «отменить последнее изменение»). Идемпотентно. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ts TEXT NOT NULL,
        source TEXT NOT NULL DEFAULT 'save',
        snapshot TEXT NOT NULL
    )");
    /* Пользовательский эталон «по умолчанию» (критерий 19): «сохрани текущее как дефолт».
       Пустая таблица → используются заводские значения из кода. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings_defaults (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    /* Промокоды (критик functional top#3). В migrateSchema, а НЕ только в сид:
       сид выполняется лишь при первом создании БД, а прод уже создан — таблица
       бы не появилась (проверено: /api/promo падал в no_such_table). */
    /* Occasion-лендинги (SEO-критик 4/10 critical#2: весь transactional head-трафик
       рынка цветов — «свадебные букеты спб», «букет на день рождения» — был недоступен).
       Таблица + сид в migrateSchema: прод-БД уже создана, сид-блок не повторится. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS occasions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        meta_title TEXT NOT NULL DEFAULT '',
        meta_description TEXT NOT NULL DEFAULT '',
        intro TEXT NOT NULL DEFAULT '',
        body TEXT NOT NULL DEFAULT '',
        faq_q1 TEXT NOT NULL DEFAULT '',
        faq_a1 TEXT NOT NULL DEFAULT '',
        faq_q2 TEXT NOT NULL DEFAULT '',
        faq_a2 TEXT NOT NULL DEFAULT '',
        product_ids TEXT NOT NULL DEFAULT '',
        active INTEGER NOT NULL DEFAULT 1,
        sort INTEGER NOT NULL DEFAULT 99
    )");
    if ((int)$pdo->query('SELECT COUNT(*) FROM occasions')->fetchColumn() === 0) {
        $ins = $pdo->prepare("INSERT INTO occasions (slug,title,meta_description,intro,body,faq_q1,faq_a1,faq_q2,faq_a2,product_ids,active,sort) VALUES (:sl,:t,:md,:i,:b,:q1,:a1,:q2,:a2,:p,1,:so)");
        $ins->execute([':sl'=>'svadebnye-bukety', ':t'=>'Свадебные букеты в Санкт-Петербурге',
          ':md'=>'Свадебные букеты на заказ: букет невесты, бутоньерки и композиции для родителей. Свежие цветы с утренней поставки, доставка к церемонии в день торжества.',
          ':i'=>'Свадебный день начинается с букета. Мы собираем композиции под стиль торжества: нежные пионовидные розы, полевые ромашки для камерной церемонии, классические охапки роз для свадебного обеда.',
          ':b'=>'Что входит в свадебный набор: букет невесты, бутоньерки для жениха и свидетелей (от 1 до 6), композиции для мам жениха и невесты, лепестки для арки или кортежа. Скажите дату, время и адрес церемонии — предложим состав по фото и бюджету в тот же день. Заказ на свадебный букет подтверждаем заранее: цветы поступают с утренней поставки в день торжества, а не лежат на складе.',
          ':q1'=>'За сколько дней заказывать свадебный букет?', ':a1'=>'Оптимально — за 2–3 дня: успеем согласовать состав, оттенки и бутоньерки. Срочные заказы в день торжества тоже принимаем до 20:00, если есть нужные цветы на поставке.',
          ':q2'=>'Можно ли заказать только бутоньерки?', ':a2'=>'Да, от трёх штук; привезём вместе с букетом невесты или отдельно — как удобнее.',
          ':p'=>'1,7,8', ':so'=>1]);
        $ins->execute([':sl'=>'buket-na-den-rozhdeniya', ':t'=>'Букет на день рождения в Санкт-Петербурге',
          ':md'=>'Букет на день рождения с доставкой сегодня: именинный букет, открытка и шарик. Соберём под возраст и характер именинника — от полевых ромашек до каретты роз.',
          ':i'=>'День рождения — повод, который не откладывают на «завтра». Если заказ оформлен до 20:00, привезём букет в тот же день — в гости, в офис или на праздник.',
          ':b'=>'Как выбрать именинный букет: классика — чётное количество не носят, дарим нечёт: 5, 7, 9, 15, 25 стеблей. Для коллеги уместен компактный микс в крафте, для мамы — пышная композиция с розами и альстромериями, для девочки-подростка — пионовидные оттенки и эвкалипт. Напишем поздравительную открытку от руки — бесплатно, текст скажите при оформлении. Если получатель стесняется цветов на работе — привезём в нейтральной упаковке и позвоним заранее.',
          ':q1'=>'Какую карту приложите к букету?', ':a1'=>'Напишем ваш текст от руки на открытке — до 500 символов, это бесплатно. Можем вложить конверт для денег или маленький подарок из каталога.',
          ':q2'=>'Узнает ли именинник, от кого букет?', ':a2'=>'Только то, что вы скажете: курьер передаст ваш текст, от себя ничего не добавляет.',
          ':p'=>'2,8,11', ':so'=>2]);
        $ins->execute([':sl'=>'traurnye-kompozicii', ':t'=>'Траурные композиции и венки в Санкт-Петербурге',
          ':md'=>'Траурные букеты, корзины и венки с доставкой в день церемонии: гвоздики, хризантемы, строгие белые тона. Поможем выбрать и привезём вовремя.',
          ':i'=>'В трудный день хочется решить задачу без лишних слов. Собираем строгие траурные композиции из свежих цветов — гвоздики, белые и тёмные хризантемы, каллы.',
          ':b'=>'Форматы: траурный букет (от 15 стеблей), корзина или композиция на столик, венок на штативе. Лента с подписью — по вашему тексту: напишем корректно, без пафоса. Доставляем в ритуальный зал, на адрес прощания или на кладбище по всему городу; время согласуем почасово. Для заказов от трёх одинаковых букетов (родственникам) — соберём комплектом.',
          ':q1'=>'Можно ли заказать ночью или очень рано?', ':a1'=>'Да: напишите или позвоните — при срочных траурных заказах согласуем раннюю доставку вне обычного графика.',
          ':q2'=>'Какие цвета уместны?', ':a2'=>'Классика — красные и бордовые гвоздики, белые или жёлтые хризантемы, тёмно-красные розы. Строго, без блёсток и декоративного кружева.',
          /* W96-fix1 (F9): клубника в шоколаде на траурной странице — меняем на
             нейтральные демо-товары (id 3 «Полевые цветы», id 16 «Красный акцент») */
          ':p'=>'3,16', ':so'=>3]);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS promo_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        kind TEXT NOT NULL DEFAULT 'percent',
        value INTEGER NOT NULL DEFAULT 0,
        min_order INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        max_uses INTEGER NOT NULL DEFAULT 0,
        used INTEGER NOT NULL DEFAULT 0
    )");
        /* W59: ремонт пустых сидов review-текстов на УЖЕ созданных БД (seed-run
       бывает только при первом создании; setting() не отличает '' от отсутствия). */
    $pdo->exec("UPDATE settings SET value='Мы не публикуем отзывы на сайте — пусть их пишут за нас. Все оценки и слова покупателей живут в профиле на Яндекс Картах: там же вы сможете оставить своё впечатление после доставки.' WHERE key='reviews_card_text' AND value=''");
    /* W63 (awwwards-жюри def.6): сид прошлой волны записал дисклеймер до миграции — на prod
       строка НЕ пустая, старый «анти-оффер» остался. Пересаживаем по точному совпадению старого. */
    $pdo->exec("UPDATE settings SET value='Нас находят по словам «цветы с доставкой СПб» и возвращаются за вторым букетом — это лучшая рекомендация. Все оценки и отзывы покупателей — в профиле на Яндекс Картах: там же можно оставить своё впечатление после доставки.' WHERE key='reviews_card_text' AND value='Мы не публикуем отзывы на сайте — пусть их пишут за нас. Все оценки и слова покупателей живут в профиле на Яндекс Картах: там же вы сможете оставить своё впечатление после доставки.'");
    $pdo->exec("UPDATE settings SET value='Отзывы о нас на Яндекс Картах' WHERE key='reviews_title' AND value=''");
    /* W65 (obvious NEW-1): нативный select режет длинные подписи зон на 390px без «…» —
       короткие подписи; старое длинное дефолтное значение самовывоза мигрируем. */
    $pdo->exec("UPDATE settings SET value='Самовывоз · 0 ₽' WHERE key='pickup_option_text' AND value='Самовывоз — бесплатно'");
    $pdo->exec("UPDATE settings SET value='Реальные отзывы покупателей — на карте города' WHERE key='reviews_sub' AND value=''");

    $orderCols = array_column($pdo->query("PRAGMA table_info(orders)")->fetchAll(), 'name');
    foreach ([
        ['given_to', "TEXT NOT NULL DEFAULT ''"],
        ['no_photo_reason', "TEXT NOT NULL DEFAULT ''"],
        ['handover_photo', "TEXT NOT NULL DEFAULT ''"],
        /* Критик functional (gift-UX + слоты): получатель, открытка, дата/слот доставки */
        ['recipient_name', "TEXT NOT NULL DEFAULT ''"],
        ['recipient_phone', "TEXT NOT NULL DEFAULT ''"],
        ['card_text', "TEXT NOT NULL DEFAULT ''"],
        ['delivery_date', "TEXT NOT NULL DEFAULT ''"],
        ['delivery_slot', "TEXT NOT NULL DEFAULT ''"],
        ['promo_code', "TEXT NOT NULL DEFAULT ''"],
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
        ('pickup_address', ''),
        ('feature_gift_fields', '1'),
        ('feature_delivery_slots', '0'),
        ('feature_gallery', '1'),
        ('feature_related', '1'),
        ('related_title', 'С этим берут'),
        ('delivery_slots', ''),
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
        ('email_enabled', '1'),
        ('reviews_title', 'Отзывы о нас на Яндекс Картах'),
        ('reviews_card_text', 'Нас находят по словам «цветы с доставкой СПб» и возвращаются за вторым букетом — это лучшая рекомендация. Все оценки и отзывы покупателей — в профиле на Яндекс Картах: там же можно оставить своё впечатление после доставки.'),
        ('yandex_verification', ''),
        ('google_site_verification', ''),
        ('metrika_counter_id', '')");
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
    /* Операционный-критик W32: отслеживание актуальности — когда карточку последний раз
       правили (для напоминания «не обновлялся N дней»). */
    if (!in_array('updated_at', $prodCols2, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''");
        $pdo->exec("UPDATE products SET updated_at = datetime('now','localtime') WHERE updated_at = ''");
    }
    /* orders: лог согласия на обработку ПД (152-ФЗ) — дата/время/IP/значение */
    $ordCols2 = array_column($pdo->query("PRAGMA table_info(orders)")->fetchAll(), 'name');
    if (!in_array('consent_log', $ordCols2, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN consent_log TEXT NOT NULL DEFAULT ''");
    }
    /* W96 (редизайн 5cv): бейджи карточек — «Хит продаж» и «Премиум» (секции витрины) */
    $prodCols3 = array_column($pdo->query("PRAGMA table_info(products)")->fetchAll(), 'name');
    if (!in_array('is_hit', $prodCols3, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN is_hit INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('is_premium', $prodCols3, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN is_premium INTEGER NOT NULL DEFAULT 0');
    }
    /* W96 (редизайн 5cv): тексты/тумблеры новых блоков — бар города, hero-промо, чипы цен,
       секции хитов/премиума/бюджета/допов, поводы, магазины, SEO-текст, журнал.
       Пустые строки = карточка/блок не показывается, пока владелец не заполнит
       (магазины и журнал по умолчанию выключены). Числовые пороги — строками (settings — TEXT). */
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES
        ('feature_citybar', '1'),
        ('citybar_text', 'Ваш город — Санкт-Петербург?'),
        ('city_label', 'Санкт-Петербург'),
        ('search_placeholder', 'Розы, пионы, букет маме…'),
        ('catalog_btn_text', 'Каталог'),
        ('hero_promo_enabled', '1'),
        ('hero_promo_badge', 'Всегда'),
        ('hero_promo_title', 'Открытка в подарок'),
        ('hero_promo_text', 'Напишем ваш текст от руки и вложим в букет — бесплатно, в каждом заказе'),
        ('hero_promo_btn_text', 'Оформить заказ'),
        ('hero_promo_link', '#order'),
        ('hero_delivery_card_enabled', '1'),
        ('hero_delivery_title', 'Доставка в день заказа'),
        ('hero_delivery_text', 'По Санкт-Петербургу — оформите до 20:00, привезём сегодня'),
        ('feature_chips', '1'),
        ('chips_price_low', '3500'),
        ('chips_price_high', '7000'),
        ('feature_carousels', '1'),
        ('feature_section_hits', '1'),
        ('section_hits_title', 'Хиты продаж'),
        ('section_hits_sub', 'Букеты, которые выбирают чаще всего'),
        ('feature_section_premium', '1'),
        ('section_premium_title', 'Премиум — для особого случая'),
        ('section_premium_sub', 'Крупные композиции для торжественных поводов'),
        ('feature_section_budget', '1'),
        ('section_budget_title', 'Букеты до %s ₽'),
        ('feature_section_addons', '1'),
        ('section_addons_title', 'Дополните букет 🎈'),
        ('badge_hit_text', 'Хит'),
        ('badge_premium_text', 'Премиум'),
        ('feature_occasions', '1'),
        ('occasions_title', 'Цветы по поводу'),
        ('feature_stores', '0'),
        ('stores_title', 'Наши магазины в Петербурге'),
        ('stores_sub', 'Заберите сами или закажите доставку — букет будет готов в течение дня'),
        ('stores_1_title', ''),
        ('stores_1_text', ''),
        ('stores_2_title', ''),
        ('stores_2_text', ''),
        ('stores_3_title', ''),
        ('stores_3_text', ''),
        ('feature_seotext', '1'),
        ('seo_text_title', 'Доставка цветов в Санкт-Петербурге'),
        ('seo_text_body', 'Доставляем букеты по всем районам Санкт-Петербурга в день заказа — от центра до удалённых кварталов. Оформите заказ до 20:00, и курьер привезёт цветы сегодня; точное время согласуем по телефону. Для срочных случаев собираем букет за 1–2 часа.

Свежие цветы поступают к нам с утренней поставки, поэтому мы не собираем букеты заранее «в стол» — каждая композиция составляется под ваш заказ. Если нужного цветка не окажется в идеальном состоянии, предложим равноценную замену до отправки, а не после вручения.

Перед доставкой курьер фотографирует готовый букет и присылает фото вам — вы видите то же, что получит адресат. Если что-то не так, заменим композицию до вручения без лишних вопросов.

Способ оплаты выберете при оформлении: наличными курьеру при получении или онлайн — если доступен в заказе. Стоимость доставки зависит от района — от 300 ₽ по центру; точную сумму посчитаем при подтверждении заказа.'),
        ('feature_journal', '0'),
        ('journal_title', 'Журнал Nilov Flowers'),
        ('journal_1_title', ''),
        ('journal_1_text', ''),
        ('journal_1_link', ''),
        ('journal_1_image', ''),
        ('journal_2_title', ''),
        ('journal_2_text', ''),
        ('journal_2_link', ''),
        ('journal_2_image', ''),
        ('journal_3_title', ''),
        ('journal_3_text', ''),
        ('journal_3_link', ''),
        ('journal_3_image', ''),
        /* W96-fix1 (F1): FAQ-блок на главной пуст, если ключей нет в БД — W96-витрина
           читает их с дефолтом '' и не показывает ни одного вопроса. Вопросы —
           «заводские» из settings-history.php (settingsDefaults), ответы — честные
           (доставка/цена, сроки, фото перед отправкой, оплата). Редактируются в админке. */
        ('faq_title', 'Частые вопросы'),
        ('faq_q1', 'Сколько стоит доставка?'),
        ('faq_a1', 'Стоимость зависит от района доставки — точную сумму посчитаем и скажем при подтверждении заказа. Самовывоз из магазина бесплатный.'),
        ('faq_q2', 'Успею ли заказать сегодня?'),
        ('faq_a2', 'Да: оформите заказ до 20:00 — соберём букет и привезём в тот же день, точное время согласуем по телефону. Срочные заказы собираем за 1–2 часа.'),
        ('faq_q3', 'Как понять, что пришёл именно мой букет?'),
        ('faq_a3', 'Перед доставкой курьер фотографирует готовый букет и присылает фото вам — вы видите то же, что получит адресат. Если что-то не так, заменим композицию до вручения.'),
        ('faq_q4', 'Как оплатить?'),
        ('faq_a4', 'Наличными курьеру при получении. Если онлайн-оплата доступна — способ можно выбрать при оформлении заказа.')");

    /* W96 one-time: редизайн под 5cv — приведение СУЩЕСТВУЮЩИХ прод-настроек к новому
       дизайн-языку (INSERT OR IGNORE выше на них не действует — ключи уже в БД).
       Маркер w96_redesign_applied защищает от повтора при последующих выкатах. */
    $w96marker = $pdo->query("SELECT value FROM settings WHERE key = 'w96_redesign_applied'")->fetchColumn();
    if ($w96marker === false) {
        /* 5cv не несёт длинный бейдж доставки на каждой карточке — шум, вырубаем.
           UPSERT: в локальных/свежих БД ключа может не быть вовсе (тогда INSERT). */
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('feature_delivery_badge', '0') ON CONFLICT(key) DO UPDATE SET value = '0'");
        /* Требование заказчика: блок отзывов не переносится в новый дизайн */
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('yandex_reviews_enabled', '0') ON CONFLICT(key) DO UPDATE SET value = '0'");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('w96_redesign_applied', '1')");
    }

    /* W96-fix1 (бизнес-критик, волна 1): приведение УЖЕ мигрированных БД к новым
       дефолтам и мелким правкам сидов. W96 ушёл в прод до фикса — INSERT OR IGNORE
       там уже отработал со старыми значениями, а сид-блоки не повторяются на
       существующих БД. Guard-условия «только при старом дефолтном значении» не
       трогают тексты, изменённые владельцем через админку. Маркер защищает от
       повтора при последующих выкатах. */
    $w96f1 = $pdo->query("SELECT value FROM settings WHERE key = 'w96fix1_applied'")->fetchColumn();
    if ($w96f1 === false) {
        /* F4: промо-карточка hero — «подписки» нет; обещаем открытку (она есть) */
        $fix = $pdo->prepare('UPDATE settings SET value = :nv WHERE key = :k AND value = :ov');
        $fix->execute([':nv' => 'Всегда', ':k' => 'hero_promo_badge', ':ov' => 'Выгодно']);
        $fix->execute([':nv' => 'Открытка в подарок', ':k' => 'hero_promo_title', ':ov' => 'Цветы по подписке']);
        $fix->execute([':nv' => 'Напишем ваш текст от руки и вложим в букет — бесплатно, в каждом заказе', ':k' => 'hero_promo_text', ':ov' => 'Регулярные букеты со скидкой до 20% — освежайте дом или радуйте близких каждую неделю']);
        $fix->execute([':nv' => 'Оформить заказ', ':k' => 'hero_promo_btn_text', ':ov' => 'Подробнее']);
        $fix->execute([':nv' => 'Доставка в день заказа', ':k' => 'hero_delivery_title', ':ov' => 'Доставка 1–2 часа']);
        $fix->execute([':nv' => 'По Санкт-Петербургу — оформите до 20:00, привезём сегодня', ':k' => 'hero_delivery_text', ':ov' => 'По Санкт-Петербургу в день заказа — оформите до 20:00']);
        /* F5: SEO-текст не обещает карту/СБП, которых может не быть */
        $pdo->exec("UPDATE settings SET value = replace(value, 'Оплатить можно онлайн картой или через СБП, либо наличными курьеру при получении.', 'Способ оплаты выберете при оформлении: наличными курьеру при получении или онлайн — если доступен в заказе.') WHERE key = 'seo_text_body' AND value LIKE '%Оплатить можно онлайн картой или через СБП%'");
        /* F12: опечатка «свежных» в траурном интро */
        $pdo->exec("UPDATE occasions SET intro = replace(intro, 'свежных', 'свежих') WHERE slug = 'traurnye-kompozicii' AND intro LIKE '%свежных%'");
        /* F9: клубника в шоколаде на траурной странице — меняем ТОЛЬКО если id 9
           и правда клубника, а id 16 существует и активен (на реальных БД владельца
           каталог другой — guard не даст поломать его подборку) */
        $pdo->exec("UPDATE occasions SET product_ids = '3,16' WHERE slug = 'traurnye-kompozicii' AND product_ids = '3,9'
            AND EXISTS (SELECT 1 FROM products WHERE id = 9 AND name LIKE '%клубник%')
            AND EXISTS (SELECT 1 FROM products WHERE id = 16 AND is_active = 1)");
        /* F10: демо-фото роз с ценником — на чистое */
        $pdo->exec("UPDATE products SET image = 'roz2.jpg' WHERE slug = 'buket-iz-roz' AND image = 'roz.jpg'");
        /* F11: «Дополните букет» из одного товара — включаем второй (тюльпаны) */
        $pdo->exec("UPDATE products SET show_in_upsell = 1 WHERE slug = 'vesennij-buket-tyulpany' AND show_in_upsell = 0");
        /* F15: канцелярит в названиях демо-товаров */
        $pdo->exec("UPDATE products SET name = replace(name, 'живых цветов ', '') WHERE name LIKE '%живых цветов %'");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('w96fix1_applied', '1')");
    }
}
