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
            createBaseTables($pdo);
        }
        migrateSchema($pdo);
        /* W103 (фикшен сишего сида): сид-INSERT использует колонки is_hit/is_premium/
           show_in_upsell, которые добавляет migrateSchema — сначала схема, потом данные. */
        if ($new) {
            seedDemoData($pdo);
        }
    }
    return $pdo;
}

function createBaseTables(PDO $pdo): void
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
}

/* Демо-данные для свежих БД: каталог, настройки, зоны. Выполняется ПОСЛЕ
   migrateSchema (INSERT-ы используют колонки, добавляемые миграцией). */
function seedDemoData(PDO $pdo): void
{
    /* Демо-каталог W96 (дизайн 5cv): категории под секции-карусели витрины.
       Владелец заменит товары через админку — сид только для свежих БД. */
    $pdo->exec("INSERT INTO categories (name, sort) VALUES
        ('Розы', 10), ('Сборные букеты', 20), ('Полевые цветы', 30),
        ('Авторские букеты', 40), ('В шляпной коробке', 50), ('Сладкие подарки', 60)");

    /* Демо-товары с флагами hit/premium/upsell — секции «Хиты»/«Премиум»/«Дополните букет»
       сразу живые. Фото gen*.jpg лежат в img/products (локальный дев-набор). */
    $pdo->exec("INSERT INTO products (category_id, name, slug, price, sale_price, description, image, is_hit, is_premium, show_in_upsell, sort) VALUES
        (1, 'Утренние розы', 'buket-iz-roz', 2500, NULL, '15 роз тёплого красного оттенка из утренней поставки, собранные в плотный круглый букет. Размер — около 40–45 см в высоту, упаковка — крафт с атласной лентой. Подходит для свидания, дня рождения и благодарности без повода. В вазе стоит 7–10 дней: подрежьте стебли под углом, меняйте воду раз в сутки и держите букет вдали от солнца и батарей.', 'roz2.jpg', 1, 0, 0, 10),
        (2, 'Весенний этюд', 'vesennij-etyud', 2500, 2100, 'Сборный букет в весенней палитре: сезонные цветы нежных оттенков и свежая зелень. Размер — около 40–45 см, упаковка — крафт с атласной лентой. Повод: 8 Марта, день рождения, визит в гости. Свежесть до 7 дней — подрезайте стебли и меняйте воду ежедневно, увядшие бутоны убирайте сразу.', 'p2.jpg', 0, 0, 0, 20),
        (3, 'Летний луг', 'polevye-tsvety', 2100, NULL, 'Лёгкий букет из полевых цветов — ромашки, колокольчики и сезонные травы. Размер — около 35–40 см, упаковка — крафт без лишнего декора. Уместен как знак внимания без повода и как подарок для дачи или загородного дома. Живёт в вазе 5–7 дней: прохлада и ежедневная смена воды продлевают свежесть.', 'p3.jpg', 0, 0, 0, 30),
        (2, 'Семь оттенков', 'buket-7-alstromerii-miks', 2900, NULL, 'Семь альстромерий в пастельной гамме — лёгкий букет на каждый день. Размер — компактный, около 35–40 см. Упаковка — плёнка с лентой в тон. Повод: благодарность коллеге, врачу или учителю, небольшой юбилей. Альстромерии стоят в вазе 10–14 дней: меняйте воду раз в два дня и подрезайте стебли.', 'gen7.jpg', 1, 0, 0, 40),
        (1, 'Признание', 'buket-19-roz-evkalipt', 4900, NULL, '19 красных эквадорских роз длиной 50 см с веточками эвкалипта. Размер букета — около 50–55 см, флорист подберёт упаковку в тон букета. Классика для признания, годовщины и юбилея. Стоит в вазе 7–10 дней: подрежьте розы под углом, удалите нижние листья и не ставьте букет рядом с фруктами.', 'gen8.jpg', 1, 0, 0, 50),
        (4, 'Пионовый сад', 'buket-51-pion', 11900, NULL, 'Пятьдесят одна пионовидная роза кремово-розовой гаммы в нежной обёртке — крупная композиция для особого случая. Размер — около 50–55 см в диаметре, упаковка — фетр. Повод: свадьба, юбилей, рождение ребёнка. Пионовидные розы раскрываются медленно и стоят в вазе 7–10 дней: прохлада, свежая вода и подрезка стеблей продлевают праздник.', 'gen9.jpg', 0, 1, 0, 60),
        (5, 'Фарфор', 'gortenzii-shlyapnaya-korobka', 8900, NULL, 'Белые и голубые гортензии в круглой шляпной коробке — статусный подарок, который не нужно переставлять в вазу. Диаметр композиции — около 30–35 см. Повод: новоселье, день рождения, деловой подарок. Гортензии пьют много: доливайте воду в коробку каждые 1–2 дня, свежесть сохранится до недели.', 'gen10.jpg', 0, 1, 0, 70),
        (1, 'Пудра', 'buket-5-rozovyh-roz', 2300, NULL, 'Компактный букет из пяти розовых эквадорских роз — маленький знак внимания без повода. Размер — около 30–35 см, флорист подберёт упаковку в тон букета. Подходит для благодарности коллеге, первого свидания и подарка учителю. Стоит в вазе 7–10 дней при ежедневной смене воды.', 'gen11.jpg', 0, 0, 0, 80),
        (6, 'Мини-набор клубники в шоколаде', 'klubnika-shokolad-mini', 990, NULL, 'Мини-набор клубники в бельгийском шоколаде: свежие ягоды в тёмной и молочной глазури. Порция рассчитана на двоих — сладкое дополнение к букету. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике и съешьте в течение суток: клубника не любит тепло.\n\nСостав: свежая клубника, шоколад тёмный и молочный (какао-продукты, сахар, какао-масло). Масса нетто: ~200 г. Срок годности: 24 часа. Хранить при +2…+6 °C. Изготовитель и декларация — уточняются при подтверждении заказа.', 'gen12.jpg', 0, 0, 1, 90),
        (5, 'Красная классика', '9-roz-shlyapnaya-korobka', 5500, NULL, 'Девять красных роз с гипсофилой в круглой шляпной коробке — подарок, не требующий вазы. Диаметр — около 25–30 см. Повод: годовщина, признание, визит к родителям. Розы стоят 7–10 дней: доливайте воду в коробку через день и держите композицию вдали от солнца.', 'gen13.jpg', 1, 0, 0, 100),
        (2, 'Оттепель', 'vesennij-buket-tyulpany', 1900, NULL, 'Голландские тюльпаны тёплых оттенков в крафт-упаковке. Размер — около 35–40 см. Повод: день рождения, свидание или весеннее настроение в любой сезон — тюльпаны приезжают из Голландии круглый год. Тюльпаны живут в вазе 5–7 дней и продолжают расти: подрезайте стебли и держите букет в прохладе, подальше от батареи.', 'gen14.jpg', 0, 0, 1, 110),
        (2, 'Розовое облако', 'avtorskij-rozovoe-oblako', 4200, NULL, 'Авторский букет в розовой гамме: гортензии, розы и альстромерии в одном тоне — состав дышит вместе с сезоном, флорист собирает его утром. Размер — около 45 см, упаковка — крафт с лентой в тон. Повод: день рождения, рождение дочки, романтический повод без даты. Свежесть 7–10 дней при ежедневной смене воды.', 'gen1.jpg', 1, 0, 0, 120),
        (2, 'Белая нежность', 'buket-belaya-nezhnost', 3500, NULL, 'Белые и кремовые цветы с эвкалиптом — спокойная элегантность без ярких акцентов. Размер — около 40–45 см, флорист подберёт упаковку в тон букета. Подходит для поздравления коллеги, выписки из роддома и цветов «на стол». Стоит в вазе до 7 дней: эвкалипт ароматен, воду меняйте раз в сутки.', 'gen2.jpg', 0, 0, 0, 130),
        (4, 'Первый танец', 'pionovidnaya-klassika', 6800, NULL, 'Крупные пионовидные розы — букет, который запомнится. Размер — около 50 см, упаковка — фетр. Повод: помолвка, юбилей, годовщина свадьбы. Пионовидные розы раскрываются медленно и стоят в вазе 10–14 дней: подрежьте стебли под углом и меняйте воду раз в сутки.', 'gen3.jpg', 0, 1, 0, 140),
        (2, 'Солнечный микс', 'solnechnyj-miks', 2700, 2200, 'Жёлто-оранжевый микс сезонных цветов — букет-заряд бодрости. Размер — около 40 см, упаковка — крафт. Повод: выздоровление, поздравление учителю, день рождения друга. Живёт в вазе 5–7 дней: подрезайте стебли и меняйте воду ежедневно, букет любит свет без прямого солнца.', 'gen4.jpg', 0, 0, 0, 150),
        (1, 'Красный акцент', 'krasnyj-akcent', 3800, NULL, 'Красные розы с сезонной зеленью — букет, уместный всегда. Размер — около 45 см, флорист подберёт упаковку в тон букета. Повод: свидание, годовщина, «просто так». Стоит в вазе 7–10 дней: уберите нижние листья, подрежьте стебли под углом и не ставьте рядом с фруктами.', 'gen5.jpg', 0, 0, 0, 160),
        (2, 'Романтика', 'romantika', 3100, 2700, 'Пастельный букет с гипсофилой — нежность в каждой детали. Размер — около 40 см, упаковка — плёнка с атласной лентой. Повод: первое свидание, день знакомства, извинение. Свежесть до 7 дней: гипсофила осыпается к концу недели, основные цветы проживут дольше.', 'gen6.jpg', 0, 0, 0, 170),
        (4, 'Кремовый сон', 'avtorskij-kremovyj-son', 5400, NULL, 'Ранункулюсы и лизиантусы в шёлковой обёртке — нежная авторская композиция. Размер — около 40–45 см. Повод: свадебный подарок, день рождения, рождение ребёнка. Ранункулюсы стоят 5–7 дней, лизиантусы — до 10: подрезайте стебли и меняйте воду ежедневно.', 'gen15.jpg', 0, 1, 0, 180),
        (5, 'Белые ночи', '25-roz-gortenzii-korobka', 9500, NULL, '25 роз и гортензии в круглой шляпной коробке с атласной лентой — крупный подарок без вазы и упаковки. Диаметр — около 35 см. Повод: юбилей, годовщина, важное поздравление. Свежесть 7–10 дней: доливайте воду в коробку каждые 1–2 дня.', 'gen16.jpg', 0, 1, 0, 190),
        (4, 'Красное и белое', 'buket-godovshina-krasnoe-beloe', 4500, NULL, 'Красные и белые розы с гипсофилой — классика юбилея отношений. Размер — около 45–50 см, флорист подберёт упаковку в тон букета. Повод: годовщина свадьбы и знакомства, круглая дата. Стоит в вазе 7–10 дней при ежедневной смене воды и подрезке стеблей.', 'gen17.jpg', 1, 0, 0, 200),
        (1, 'Чёрное золото', '25-roz-cherno-zoloto', 6900, NULL, '25 красных роз в чёрной с золотом упаковке — страстный жест. Размер — около 50–55 см в высоту. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.', 'gen18.jpg', 0, 1, 0, 210),
        (5, 'Белый зал', 'orhidei-rozy-kvadrat-korobka', 7900, NULL, 'Белые орхидеи с розами в квадратной коробке — статусная композиция для делового подарка. Сторона коробки — около 30 см, высота композиции — около 35–40 см. Повод: поздравление партнёра, открытие офиса, юбилей руководителя. Свежесть 10–14 дней: орхидеи стойкие, доливайте воду во флористическую губку.', 'gen19.jpg', 0, 1, 0, 220),
        (6, 'Клубника и макаруны в розовой коробке', 'klubnika-makarony-korobka', 1490, NULL, 'Клубника в шоколаде и макаруны в розовой коробке — сладкое дополнение к букету. Порция на двоих. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике не более суток: макаруны чувствительны к теплу, ягоды — ко времени.\n\nСостав: свежая клубника в шоколаде (какао-продукты, сахар, какао-масло); макаруны (миндальная мука, белок, сахар, крем). Масса нетто: ~250 г. Срок годности: 24 часа. Хранить при +2…+6 °C. Изготовитель и декларация — уточняются при подтверждении заказа.', 'gen20.jpg', 0, 0, 1, 230)");

    /* OR IGNORE: часть ключей мог вставить migrateSchema (он теперь идёт раньше) */
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES
        ('shop_name', 'Nilov Flowers'),
        ('shop_phone', '+7 911 941-72-05'),
        ('shop_address', 'г. Санкт-Петербург, Петроградская сторона'),
        ('hero_title', 'Доставка цветов по Санкт-Петербургу'),
        ('hero_subtitle', 'К празднику или просто так — повод не обязателен'),
        ('hero_button_text', 'Выбрать букет'),
        ('hero_button_link', '#fcChips'),
        ('hero_image', 'img/editorial/petals-hero-4x3.jpg'),
        ('logo_image', ''),
        ('logo_enabled', '1'),
        ('steps_title', 'Как это работает'),
        ('step_1', 'Выбираете букет в каталоге и добавляете в корзину'),
        ('step_2', 'Оформляете заказ — мы связываемся с вами для подтверждения'),
        ('step_3', 'Собираем букет и доставляем в выбранный район'),
        ('guarantees_title', 'Гарантии'),
        ('guarantee_1', 'Фото букета до отправки'),
        ('guarantee_2', 'Каждый букет — из свежего среза'),
        ('guarantee_3', 'Заменяем увядшие цветы в день доставки')");

    /* W106-C3 (критик-флорист 6.9): реальные районы СПб вместо демо-8 — «Северного»
       и «Южного» районов в городе нет, Кировского (района самовывоза) не было вовсе.
       12 зон, лестница цен 300–500 ₽; Петроградка объединена с Центром. Составные
       имена — пояснение в скобках: селект формы режет пояснение в title (W70),
       zone-check матчит первое слово. Самовывоз — НЕ зона: отдельная опция
       value="0" в форме (pickup_option_text) — механика прежняя. Существующие
       БД переводит guard-миграция W106-C3 в migrateSchema. */
    $pdo->exec("INSERT INTO delivery_zones (name, price, sort) VALUES
        ('Центральный (и Петроградка)', 300, 10),
        ('Василеостровский', 300, 20),
        ('Адмиралтейский', 300, 30),
        ('Выборгский', 350, 40),
        ('Калининский', 350, 50),
        ('Приморский', 400, 60),
        ('Московский', 400, 70),
        ('Фрунзенский', 400, 80),
        ('Невский', 450, 90),
        ('Красногвардейский', 450, 100),
        ('Кировский (и Красносельский)', 450, 110),
        ('Пушкин (Павловск, Петергоф)', 500, 120)");

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

    /* B5/W106: вторые ракурсы галереи PDP (фото-волна B1, манифест
       state/b1-manifest.md) — тот же guard-паттерн, что и демо-фото выше;
       существующие БД получает тот же набор через migrateSchema. */
    $b5SeedImg2 = $pdo->prepare('UPDATE products SET image2 = :f WHERE slug = :s AND image2 = :empty');
    foreach ([
        'buket-iz-roz', 'buket-7-alstromerii-miks', 'buket-19-roz-evkalipt', 'buket-51-pion',
        'gortenzii-shlyapnaya-korobka', 'pionovidnaya-klassika', 'avtorskij-kremovyj-son',
        'avtorskij-rozovoe-oblako', '25-roz-gortenzii-korobka', '25-roz-cherno-zoloto',
        '9-roz-shlyapnaya-korobka', 'orhidei-rozy-kvadrat-korobka', 'buket-godovshina-krasnoe-beloe',
        /* W106-G (фото-критик 5-c P1: вторые фото у всех товаров) — +6;
           рендер product.php с is_file-guard — безопасно до появления файлов */
        'buket-5-rozovyh-roz', 'vesennij-buket-tyulpany', 'buket-belaya-nezhnost',
        'solnechnyj-miks', 'klubnika-shokolad-mini', 'klubnika-makarony-korobka',
    ] as $b5SeedSlug) {
        $b5SeedImg2->execute([':f' => 'b1-' . $b5SeedSlug . '-2.jpg', ':s' => $b5SeedSlug, ':empty' => '']);
    }

    /* A-b4/W106: отзывы (критики 6.4/6.5 — «ноль отзывов на всём сайте»).
       Общий сид-хелпер для свежих и существующих БД: каталог уже вставлен,
       product_id резолвится по slug, а не хардкодится. Guard внутри —
       повторный прогон no-op. */
    seedReviewsOnce($pdo);
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
          ':md'=>'Свадебные букеты на заказ: букет невесты, бутоньерки и композиции для родителей. Свежие цветы из утренней поставки, доставка к церемонии в день торжества.',
          ':i'=>'Свадебный день начинается с букета. Мы собираем композиции под стиль торжества: нежные пионовидные розы, полевые ромашки для камерной церемонии, классические охапки роз для свадебного обеда.',
          ':b'=>'Что входит в свадебный набор: букет невесты, бутоньерки для жениха и свидетелей (от 1 до 6), композиции для мам жениха и невесты, лепестки для арки или кортежа. Скажите дату, время и адрес церемонии — предложим состав по фото и бюджету в тот же день. Заказ на свадебный букет подтверждаем заранее: цветы поступают из утренней поставки в день торжества, а не лежат на складе.',
          ':q1'=>'За сколько дней заказывать свадебный букет?', ':a1'=>'Оптимально — за 2–3 дня: успеем согласовать состав, оттенки и бутоньерки. Срочные заказы в день торжества тоже принимаем до 20:00, если есть нужные цветы на поставке.',
          ':q2'=>'Можно ли заказать только бутоньерки?', ':a2'=>'Да, от трёх штук; привезём вместе с букетом невесты или отдельно — как удобнее.',
          /* B5/W106: лестница цен (бизнес 6.5 P1) — 6 товаров от Пудры до Фарфора,
             2 премиума; сортировку (хиты/премиум вперёд, потом цена) делает occasion.php */
          ':p'=>'13,7,1,8,12,14', ':so'=>1]);
        $ins->execute([':sl'=>'buket-na-den-rozhdeniya', ':t'=>'Букет на день рождения в Санкт-Петербурге',
          ':md'=>'Букет на день рождения с доставкой сегодня: именинный букет, открытка и шарик. Соберём под возраст и характер именинника — от полевых ромашек до каретты роз.',
          ':i'=>'День рождения — повод, который не откладывают на «завтра». Если заказ оформлен до 20:00, привезём букет в тот же день — в гости, в офис или на праздник.',
          ':b'=>'Как выбрать именинный букет: классика — чётное число не дарят. Мы собираем букеты из нечётного числа стеблей — 5, 7, 9, 15, 25. Для коллеги уместен компактный микс в крафте, для мамы — пышная композиция с розами и альстромериями, для девочки-подростка — пионовидные розы и эвкалипт. Напишем поздравительную открытку от руки — бесплатно, текст укажите при оформлении. Если получатель стесняется цветов на работе — привезём в нейтральной упаковке и позвоним заранее.',
          ':q1'=>'Какую карту приложите к букету?', ':a1'=>'Напишем ваш текст от руки на открытке — до 500 символов, это бесплатно. Можем вложить конверт для денег или маленький подарок из каталога.',
          ':q2'=>'Узнает ли именинник, от кого букет?', ':a2'=>'Только то, что вы скажете: курьер передаст ваш текст, от себя ничего не добавляет.',
          /* B5/W106: лестница цен — 7 товаров от Оттепели до Белых ночей, 2 премиума */
          ':p'=>'11,2,8,4,12,18,19', ':so'=>2]);
        $ins->execute([':sl'=>'traurnye-kompozicii', ':t'=>'Траурные композиции и венки в Санкт-Петербурге',
          ':md'=>'Траурные букеты, корзины и венки с доставкой в день церемонии: гвоздики, хризантемы, строгие белые тона. Поможем выбрать и привезём вовремя.',
          ':i'=>'В трудный день хочется решить задачу без лишних слов. Собираем строгие траурные композиции из свежих цветов — гвоздики, белые и тёмные хризантемы, каллы.',
          ':b'=>'Форматы: траурный букет (от 15 стеблей), корзина или композиция на столик, венок на штативе. Лента с подписью — по вашему тексту: напишем корректно, без пафоса. Доставляем в ритуальный зал, на адрес прощания или на кладбище по всему городу; время согласуем почасово. Для заказов от трёх одинаковых букетов (родственникам) — соберём комплектом.',
          ':q1'=>'Можно ли заказать ночью или очень рано?', ':a1'=>'Да: напишите или позвоните — при срочных траурных заказах согласуем раннюю доставку вне обычного графика.',
          ':q2'=>'Какие цвета уместны?', ':a2'=>'Классика — красные и бордовые гвоздики, белые или жёлтые хризантемы, тёмно-красные розы. Строго, без блёсток и декоративного кружева.',
          /* W96-fix1 (F9): клубника в шоколаде на траурной странице — меняем на
             нейтральные демо-товары (id 3 «Полевые цветы», id 16 «Красный акцент») */
          /* B5/W106: лестница цен — 6 строгих по тону товаров, 2 премиума */
          ':p'=>'3,13,16,5,7,19', ':so'=>3]);
        /* W96-fix3: поводы «Годовщина» и «Для любимой» — конверсионная дыра
           (критик-покупатель: anniversary/для жены — частейший запрос, страницы не было) */
        $ins->execute([':sl'=>'buket-na-godovshinu', ':t'=>'Букет на годовщину в Санкт-Петербурге',
          ':md'=>'Букет на годовщину отношений и свадьбы: красные и белые розы, романтические композиции. Доставка в день заказа, открытка от руки бесплатно.',
          ':i'=>'Годовщина — повод, который требует особенного букета: символика цветов важнее количества. Красные розы говорят о страсти, белые — о чистоте чувств, а смешанные красно-белые букеты — классика юбилеев отношений.',
          ':b'=>'Как выбрать букет на годовщину: ориентируйтесь на количество лет — 1 год дарят одну эффектную охапку, на «круглые» даты уместны крупные композиции из 25+ роз или пионовидных роз. Для серебряной и золотой свадьбы подойдут благородные белые и пастельные тона. Открытку с тёплыми словами напишем от руки и вложим в букет бесплатно. Если годовщина сегодня — оформите заказ до 20:00, доставим в тот же день.',
          ':q1'=>'Доставите букет на годовщину сегодня?', ':a1'=>'Да, при оформлении до 20:00 доставим в тот же день по Санкт-Петербургу. В комментарии укажите удобное время — курьер привезёт букет к нужному часу.',
          ':q2'=>'Можно добавить к букету подарок?', ':a2'=>'Да, в разделе «Дополните букет» есть клубника в шоколаде и сладкие наборы — привезём вместе с цветами.',
          /* B5/W106: лестница цен — 7 товаров от Пудры до Белых ночей, 2 премиума */
          ':p'=>'20,5,21,8,17,16,19', ':so'=>4]);
        $ins->execute([':sl'=>'buket-dlya-lyubimoj', ':t'=>'Букет для любимой в Санкт-Петербурге',
          ':md'=>'Романтические букеты для любимой: пионовидные и классические розы, нежные пастельные композиции с доставкой в день заказа и открыткой от руки.',
          ':i'=>'Сказать «люблю» без слов проще всего цветами: пышные пионовидные розы и пастельные миксы — беспроигрышный язык чувств.',
          ':b'=>'Какой букет подарить любимой без повода: нежные пионовидные розы — самый «влюблённый» выбор; красные розы — классика признания; пастельный микс с эвкалиптом — если хотите удивить. Не уверены в её вкусах — закажите авторский букет: соберём в актуальной гамме сезона. К каждому букету — открытка с вашим текстом от руки, бесплатно.',
          ':q1'=>'Можно заказать букет сюрпризом?', ':a1'=>'Да: укажите в комментарии время и адрес получательницы, а свой телефон — для подтверждения. Курьер позвонит ей, а не вам — сюрприз не раскроется.',
          ':q2'=>'Что подарить, если она не любит розы?', ':a2'=>'Гортензии, тюльпаны или авторский сезонный микс — соберём букет под её вкус, покажем фото до отправки.',
          /* B5/W106: лестница цен — 7 товаров от Пудры до Пионового сада, 2 премиума */
          ':p'=>'1,17,6,8,12,20,14', ':so'=>5]);
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
    $pdo->exec("UPDATE settings SET value='О нас говорят' WHERE key='reviews_title' AND value=''");
    /* W106-C3fix (флорист/редактор волны 1): сид W100 писал «Отзывы о нас на Яндекс Картах» —
       внешнего профиля нет, бутафория. Нейтральный заголовок + guard для существующих БД. */
    $pdo->exec("UPDATE settings SET value='О нас говорят' WHERE key='reviews_title' AND value='Отзывы о нас на Яндекс Картах'");
    /* W106-G (редактор волны 5): точечные текст-фиксы — сид+guard */
    $pdo->exec("UPDATE settings SET value='Заменяем увядшие цветы в день доставки' WHERE key='guarantee_3' AND value='Заменяем увядшие в день доставки'");
    $pdo->exec("UPDATE occasions SET body = REPLACE(body, 'пионовидные оттенки и эвкалипт', 'пионовидные розы и эвкалипт') WHERE body LIKE '%пионовидные оттенки%'");
    $pdo->exec("UPDATE occasions SET body = REPLACE(body, 'текст скажите при оформлении', 'текст укажите при оформлении') WHERE body LIKE '%скажите при оформлении%'");
    /* W106-G (фото-критик 5-c: «Пудра» ведёт 3 повода из 5 — разные лиды): guard по точным старым CSV */
    $pdo->exec("UPDATE occasions SET product_ids='13,7,1,8,12,14' WHERE slug='svadebnye-bukety' AND product_ids='8,1,13,12,14,7'");
    $pdo->exec("UPDATE occasions SET product_ids='20,5,21,8,17,16,19' WHERE slug='buket-na-godovshinu' AND product_ids='8,17,16,20,5,21,19'");
    $pdo->exec("UPDATE occasions SET product_ids='1,17,6,8,12,20,14' WHERE slug='buket-dlya-lyubimoj' AND product_ids='8,1,17,12,20,14,6'");
    /* W65 (obvious NEW-1): нативный select режет длинные подписи зон на 390px без «…» —
       короткие подписи; старое длинное дефолтное значение самовывоза мигрируем. */
    $pdo->exec("UPDATE settings SET value='Самовывоз · 0\u{00A0}₽' WHERE key='pickup_option_text' AND value='Самовывоз — бесплатно'");
    /* W105-6fix2: ₽ не отрывается от числа (option — вне DOM-скоупа js/glue.js) */
    $pdo->exec("UPDATE settings SET value='Самовывоз · 0\u{00A0}₽' WHERE key='pickup_option_text' AND value='Самовывоз · 0 ₽'");
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
        /* D-d3 (конкурент P0, волна 2): улицы «Полевая Сабировская» в СПб нет —
           самовывоз без выдуманной улицы: честное описание студии уровнем района.
           Рендеры (форма «Как получить», PDP, order-thanks) подписывают значение
           («Адрес самовывоза:», «Самовывоз:», «Ждём вас по адресу:») — поэтому
           значение — продолжение фразы, без собственного «Самовывоз —» в начале. */
        ('pickup_address', 'из студии в центре Петроградской стороны (точный адрес и время сообщим при подтверждении заказа)'),
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
        ('reviews_title', 'О нас говорят'),
        ('reviews_card_text', 'Нас находят по запросам о доставке цветов в Санкт-Петербурге и возвращаются за вторым букетом — это лучшая рекомендация. Все оценки и отзывы покупателей — в профиле на Яндекс Картах: там же можно оставить своё впечатление после доставки.'),
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
        ('search_placeholder', 'Розы, пионы, тюльпаны…'),
        ('catalog_btn_text', 'Каталог'),
        ('hero_promo_enabled', '1'),
        ('hero_promo_badge', 'Всегда'),
        ('hero_promo_title', 'Открытка в подарок'),
        ('hero_promo_text', 'Напишем ваш текст от руки и вложим в букет — бесплатно, в каждом заказе'),
        ('hero_promo_btn_text', 'Выбрать букет'),
        ('hero_promo_link', '#fcChips'),
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
        ('occasions_title', 'Цветы по поводам'),
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
        ('seo_text_body', 'Доставляем букеты по районам Санкт-Петербурга — от центра до удалённых кварталов. Оформите заказ до 20:00, и курьер привезёт цветы сегодня; точное время согласуем по телефону. Для срочных случаев собираем букет за 1–2 часа.

Свежие цветы поступают к нам из утренней поставки, поэтому мы не собираем букеты заранее «в стол» — каждая композиция составляется под ваш заказ. Если нужного цветка не окажется в идеальном состоянии, предложим равноценную замену до отправки, а не после вручения.

Каждый заказ сопровождаем фото: вы видите букет до того, как его вручат.

Способ оплаты выберете при оформлении: наличными или картой курьеру при получении. Стоимость доставки зависит от района — от 300 ₽ по центру; точную сумму посчитаем при подтверждении заказа.'),
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
           (доставка/цена, сроки, фото до отправки, оплата). Редактируются в админке. */
        ('faq_title', 'Частые вопросы'),
        ('faq_q1', 'Сколько стоит доставка?'),
        ('faq_a1', 'Зависит от района: 300–500 ₽ по Санкт-Петербургу, самовывоз бесплатный. Точная сумма сразу видна при оформлении заказа.'),
        ('faq_q2', 'Успею ли заказать сегодня?'),
        ('faq_a2', 'Оформите заказ до 20:00 — соберём и привезём в тот же день; срочно — за 1–2 часа.'),
        ('faq_q3', 'Как понять, что пришёл именно мой букет?'),
        ('faq_a3', 'До отправки курьер фотографирует готовый букет и присылает фото вам — вы видите то же, что получит адресат. Если что-то не так, заменим композицию до вручения.'),
        ('faq_q4', 'Как оплатить?'),
        ('faq_a4', 'Наличными или картой курьеру при получении. Онлайн-оплата — сообщим, когда появится.')");

    /* W97-fixB3a (критик контента): честные ответы FAQ про доставку и оплату + hero-заголовок
       под поисковой интент «доставка цветов спб» (старые тексты обещали «посчитаем при
       подтверждении» и «онлайн-оплату», которой нет). Guard-UPDATE по ТОЧНОМУ старому дефолту —
       значения, правленые владельцем через админку, не трогаем (паттерн W63/W96-fix1).
       Идемпотентно по построению: после первого применения value уже не равен old. */
    $fixB3a = $pdo->prepare('UPDATE settings SET value = :nv WHERE key = :k AND value = :ov');
    $fixB3a->execute([':nv' => 'Зависит от района: 300–400 ₽ по СПб, самовывоз — бесплатно. Точная сумма сразу видна при оформлении заказа.',
        ':k' => 'faq_a1',
        ':ov' => 'Стоимость зависит от района доставки — точную сумму посчитаем и скажем при подтверждении заказа. Самовывоз из магазина бесплатный.']);
    $fixB3a->execute([':nv' => 'Наличными или картой курьеру при получении. Онлайн-оплата — сообщим, когда появится.',
        ':k' => 'faq_a4',
        ':ov' => 'Наличными курьеру при получении. Если онлайн-оплата доступна — способ можно выбрать при оформлении заказа.']);
    $fixB3a->execute([':nv' => 'Доставка цветов по Санкт-Петербургу',
        ':k' => 'hero_title',
        ':ov' => 'Свежие цветы с утренней поставки']);

    /* W97-fixB3a-4 (SEO-критик): тонкие сид-описания демо-товаров (40–81 символов) не давали
       карточке контента для поиска. Развёрнутые 200–400 символов: состав, размер, упаковка,
       повод, свежесть/уход. Guard: обновляем ТОЛЬКО товары, чей description до сих пор равен
       старому сидовому значению — кастомные описания из админки не трогаем. Демо-конкретика
       (размер «около N см», упаковка) — как в сиде свежих установок. */
    $fixB3aProd = $pdo->prepare("UPDATE products SET description = :nv, updated_at = datetime('now','localtime') WHERE slug = :sl AND description = :ov");
    foreach ([
        ['buket-iz-roz', 'Классический букет из свежих роз с утренней поставки.', 'Свежие розы с утренней поставки, собранные в круглый букет. Размер — стандартный, около 40–45 см в высоту. Упаковка — крафт с лентой. Подходит для свидания, дня рождения и благодарности без повода. В вазе стоит 7–10 дней: подрежьте стебли под углом, меняйте воду раз в сутки и держите букет вдали от солнца и батарей.'],
        ['vesennij-etyud', 'Нежный сборный букет в весенней палитре.', 'Сборный букет в весенней палитре: сезонные цветы нежных оттенков и свежая зелень. Размер — около 40–45 см, упаковка — крафт с атласной лентой. Повод: 8 Марта, день рождения, визит в гости. Свежесть до 7 дней — подрезайте стебли и меняйте воду ежедневно, увядшие бутоны убирайте сразу.'],
        ['polevye-tsvety', 'Лёгкий букет из полевых цветов — просто и со вкусом.', 'Лёгкий букет из полевых цветов — ромашки, колокольчики и сезонные травы. Размер — около 35–40 см, упаковка — крафт без лишнего декора. Уместен как знак внимания без повода и как подарок для дачи или загородного дома. Живёт в вазе 5–7 дней: прохлада и ежедневная смена воды продлевают свежесть.'],
        ['buket-7-alstromerii-miks', 'Нежный микс альстромерий в пастельной гамме — лёгкий букет на каждый день.', 'Семь альстромерий в пастельной гамме — лёгкий букет на каждый день. Размер — компактный, около 35–40 см. Упаковка — плёнка с лентой в тон. Повод: благодарность коллеге, врачу или учителю, небольшой юбилей. Альстромерии стоят в вазе 10–14 дней: меняйте воду раз в два дня и подрезайте стебли.'],
        ['buket-19-roz-evkalipt', 'Эквадорские розы 50 см с эвкалиптом — классика для яркого признания.', '19 красных эквадорских роз длиной 50 см с веточками эвкалипта. Размер букета — около 50–55 см, упаковка — крафт или фетр. Классика для признания, годовщины и юбилея. Стоит в вазе 7–10 дней: подрежьте розы под углом, удалите нижние листья и не ставьте букет рядом с фруктами.'],
        ['buket-51-pion', 'Пышные розовые пионы в нежной обёртке — вау-эффект для особого случая.', '51 розовый пион в нежной обёртке — крупная композиция для особого случая. Размер — около 50–55 см в диаметре, упаковка — фетр. Повод: свадьба, юбилей, рождение ребёнка. Пионы раскрываются на второй день и цветут 5–7 дней: прохлада, свежая вода и подрезка стеблей продлевают праздник.'],
        ['gortenzii-shlyapnaya-korobka', 'Белые и голубые гортензии в круглой коробке — статусный подарок.', 'Белые и голубые гортензии в круглой шляпной коробке — статусный подарок, который не нужно переставлять в вазу. Диаметр композиции — около 30–35 см. Повод: новоселье, день рождения, деловой подарок. Гортензии пьют много: доливайте воду в коробку каждые 1–2 дня, свежесть сохранится до недели.'],
        ['buket-5-rozovyh-roz', 'Компактный букет из 5 роз — маленький знак внимания без повода.', 'Компактный букет из пяти розовых эквадорских роз — маленький знак внимания без повода. Размер — около 30–35 см, упаковка — плёнка или крафт. Подходит для благодарности коллеге, первого свидания и подарка учителю. Стоит в вазе 7–10 дней при ежедневной смене воды.'],
        ['klubnika-shokolad-mini', 'Клубника в бельгийском шоколаде — сладкое дополнение к букету.', 'Мини-набор клубники в бельгийском шоколаде: свежие ягоды в тёмной и молочной глазури. Порция рассчитана на двоих — сладкое дополнение к букету. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике и съешьте в течение суток: клубника не любит тепло.'],
        ['9-roz-shlyapnaya-korobka', 'Классические красные розы в стильной круглой коробке с гипсофилой.', 'Девять красных роз с гипсофилой в круглой шляпной коробке — подарок, не требующий вазы. Диаметр — около 25–30 см. Повод: годовщина, признание, визит к родителям. Розы стоят 7–10 дней: доливайте воду в коробку через день и держите композицию вдали от солнца.'],
        ['vesennij-buket-tyulpany', 'Яркий весенний микс тюльпанов и нарциссов в крафте.', 'Весенний микс тюльпанов и нарциссов в крафт-упаковке. Размер — около 35–40 см. Повод: 8 Марта, Пасха или просто весеннее настроение. Тюльпаны живут в вазе 5–7 дней и продолжают расти: подрезайте стебли и держите букет в прохладе, подальше от батареи.'],
        ['avtorskij-rozovoe-oblako', 'Монобукет в розовой гамме — собран нашим флористом утром.', 'Авторский монобукет в розовой гамме: сезонные цветы одного тона (гортензии, розы и альстромерии по наличию), собранные флористом утром. Размер — около 45 см, упаковка — крафт с лентой в тон. Повод: день рождения, рождение дочки, романтический повод без даты. Свежесть 7–10 дней при ежедневной смене воды.'],
        ['buket-belaya-nezhnost', 'Белые и кремовые цветы с эвкалиптом — спокойная элегантность.', 'Белые и кремовые цветы с эвкалиптом — спокойная элегантность без ярких акцентов. Размер — около 40–45 см, упаковка — белый крафт или фетр. Подходит для поздравления коллеги, выписки из роддома и цветов «на стол». Стоит в вазе до 7 дней: эвкалипт ароматен, воду меняйте раз в сутки.'],
        ['pionovidnaya-klassika', 'Крупные пионовидные розы — букет, который запомнится.', 'Крупные пионовидные розы — букет, который запомнится. Размер — около 50 см, упаковка — фетр. Повод: помолвка, юбилей, годовщина свадьбы. Пионовидные розы раскрываются медленно и стоят в вазе 10–14 дней: подрежьте стебли под углом и меняйте воду раз в 1–2 дня.'],
        ['solnechnyj-miks', 'Жёлто-оранжевая гамма — букет-заряд бодрости.', 'Жёлто-оранжевый микс сезонных цветов — букет-заряд бодрости. Размер — около 40 см, упаковка — крафт. Повод: выздоровление, поздравление учителю, день рождения друга. Живёт в вазе 5–7 дней: подрезайте стебли и меняйте воду ежедневно, букет любит свет без прямого солнца.'],
        ['krasnyj-akcent', 'Красные розы с зеленью — уместно всегда.', 'Красные розы с сезонной зеленью — букет, уместный всегда. Размер — около 45 см, упаковка — крафт или фетр. Повод: свидание, годовщина, «просто так». Стоит в вазе 7–10 дней: уберите нижние листья, подрежьте стебли под углом и не ставьте рядом с фруктами.'],
        ['romantika', 'Пастельный букет с гипсофилой — нежность в каждой детали.', 'Пастельный букет с гипсофилой — нежность в каждой детали. Размер — около 40 см, упаковка — плёнка с атласной лентой. Повод: первое свидание, день знакомства, извинение. Свежесть до 7 дней: гипсофила осыпается к концу недели, основные цветы проживут дольше.'],
        ['avtorskij-kremovyj-son', 'Ранункулюсы и лизиантусы в шёлковой обёртке — нежная авторская композиция.', 'Ранункулюсы и лизиантусы в шёлковой обёртке — нежная авторская композиция. Размер — около 40–45 см. Повод: свадебный подарок, день рождения, рождение ребёнка. Ранункулюсы стоят 5–7 дней, лизиантусы — до 10: подрезайте стебли и меняйте воду ежедневно.'],
        ['25-roz-gortenzii-korobka', 'Пышная композиция в круглой коробке с атласной лентой — вау-подарок без упаковки.', '25 роз и гортензии в круглой шляпной коробке с атласной лентой — крупный подарок без вазы и упаковки. Диаметр — около 35 см. Повод: юбилей, годовщина, важное поздравление. Свежесть 7–10 дней: доливайте воду в коробку каждые 1–2 дня.'],
        ['buket-godovshina-krasnoe-beloe', 'Красные и белые розы с гипсофилой — классика юбилея отношений.', 'Красные и белые розы с гипсофилой — классика юбилея отношений. Размер — около 45–50 см, упаковка — крафт или фетр. Повод: годовщина свадьбы и знакомства, круглая дата. Стоит в вазе 7–10 дней при ежедневной смене воды и подрезке стеблей.'],
        ['25-roz-cherno-zoloto', 'Премиальная упаковка матовый чёрный + золото — страстный жест.', '25 красных роз в премиальной обёртке «матовый чёрный + золото» — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.'],
        ['orhidei-rozy-kvadrat-korobka', 'Белые орхидеи с розами — статусная композиция для делового подарка.', 'Белые орхидеи с розами в квадратной коробке — статусная композиция для делового подарка. Сторона коробки — около 30 см. Повод: поздравление партнёра, открытие офиса, юбилей руководителя. Свежесть 10–14 дней: орхидеи стойкие, доливайте воду во флористическую губку.'],
        ['klubnika-makarony-korobka', 'Клубника в шоколаде и макаруны — сладкое дополнение к букету.', 'Клубника в шоколаде и макаруны в розовой коробке — сладкое дополнение к букету. Порция на двоих. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике не более суток: макаруны чувствительны к теплу, ягоды — ко времени.'],
    ] as [$b3aSlug, $b3aOld, $b3aNew]) {
        $fixB3aProd->execute([':sl' => $b3aSlug, ':ov' => $b3aOld, ':nv' => $b3aNew]);
    }

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
        $pdo->exec("UPDATE settings SET value = replace(value, 'Оплатить можно онлайн картой или через СБП, либо наличными курьеру при получении.', 'Способ оплаты выберете при оформлении: наличными или картой курьеру при получении.') WHERE key = 'seo_text_body' AND value LIKE '%Оплатить можно онлайн картой или через СБП%'");
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

    /* W96-fix3 (критик-покупатель, волна 2, CRITICAL): слоты даты/времени доставки —
       фича построена (W-волны), но выключена дефолтом; для сценария «нужно сегодня к 19:00»
       без слотов покупатель уходит. Включаем (владелец может выключить тумблером
       в админке). Маркер — от повтора. */
    $w96f3 = $pdo->query("SELECT value FROM settings WHERE key = 'w96fix3_applied'")->fetchColumn();
    if ($w96f3 === false) {
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('feature_delivery_slots', '1') ON CONFLICT(key) DO UPDATE SET value = '1'");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('w96fix3_applied', '1')");
    }

    /* W96-fix4 (финальный критик-покупатель): подборки поводов и шкалы цен.
       Маркер — от повтора; guard-значения не трогают выборки владельца. */
    $w96f4 = $pdo->query("SELECT value FROM settings WHERE key = 'w96fix4_applied'")->fetchColumn();
    if ($w96f4 === false) {
        /* Слоты «Любое время дня» → реальные интервалы (пустая строка = дефолт из кода
           не подхватится, ключ существует) */
        $pdo->exec("UPDATE settings SET value = 'Утро 9:00–14:00\nДень 14:00–18:00\nВечер 18:00–22:00' WHERE key = 'delivery_slots' AND value = ''");
        /* Шкала селекта каталога = шкале чипов (3500/7000), старые дефолты 2500/4000 путали */
        $pdo->exec("UPDATE settings SET value = '3500' WHERE key = 'price_filter_low' AND value IN ('', '2500')");
        $pdo->exec("UPDATE settings SET value = '7000' WHERE key = 'price_filter_high' AND value IN ('', '4000')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('price_filter_low', '3500')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('price_filter_high', '7000')");
        /* Подборка «годовщины»: юбилейный букет (id 20 в демо-каталоге) не попадал в подборку —
           guard: заменяем ТОЛЬКО демо-набор '2,4,17' (реальные БД владельца не трогаем) */
        $pdo->exec("UPDATE occasions SET product_ids = '20,5,21' WHERE slug = 'buket-na-godovshinu' AND product_ids = '2,4,17'");
        $pdo->exec("UPDATE occasions SET product_ids = '12,6,20' WHERE slug = 'buket-dlya-lyubimoj' AND product_ids = '2,6,11'");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('w96fix4_applied', '1')");
    }

    /* W98-fixE (критики: редактор/юрист/CRO/конкурент). Все правки идемпотентны:
       INSERT OR IGNORE для новых ключей, guard-UPDATE «только если значение равно
       старому дефолту» — тексты, изменённые владельцем через админку, не трогаем. */

    /* E1: интро посадочных /category/{slug} — ручные тексты вместо шаблонного
       «Свежие {name} с утренней поставки» (калека: «Свежие В шляпной коробке…»).
       Слаги — фактические из slugify(name) (как в sitemap.php/footer.php). */
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES
        ('category_intro_rozy', 'Розы из утренней поставки — от компактного знака внимания до крупных композиций в авторской упаковке. Подберём оттенок под повод и получателя: красная классика для признания, пудровые и кремовые тона — для нежного повода. Доставка по Санкт-Петербургу в день заказа, оплата при получении.'),
        ('category_intro_sbornye-bukety', 'Сборные букеты из сезонных цветов — флорист соберёт под ваш повод и бюджет. Доставка по Санкт-Петербургу в день заказа.'),
        ('category_intro_polevye-cvety', 'Букеты из полевых и садовых цветов — лёгкие, ароматные, как из деревенского сада. Доставка по Санкт-Петербургу в день заказа, оплата при получении.'),
        ('category_intro_avtorskie-bukety', 'Авторские композиции нашего флориста — собираем утром из свежей поставки, фото букета пришлём до отправки. Доставка по Санкт-Петербургу в день заказа.'),
        ('category_intro_v-shlyapnoy-korobke', 'Букеты в шляпных коробках — подарок, который не требует вазы: открыл и поставил. Доставим в день заказа по Санкт-Петербургу.'),
        ('category_intro_sladkie-podarki', 'Сладкие дополнения к букету — клубника в шоколаде и макаруны в подарочных коробках. Доставим вместе с цветами в день заказа.')");

    /* E3: бейдж скидки «Акционная цена −16%» (канцелярит) → «Скидка 16%».
       Guard по точному старому значению; fresh-БД — сразу новое дефолт-значение. */
    $pdo->exec("UPDATE settings SET value = 'Скидка' WHERE key = 'badge_sale_text' AND value = 'Акционная цена'");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('badge_sale_text', 'Скидка')");

    /* E7: пищевые товары без обязательной информации (состав/масса/срок/хранение).
       Guard по slug + ТОЧНОМУ старому описанию (значения — из SELECT до правки);
       аппендим честный демо-блок без выдуманных ИНН/изготовителей. */
    $fixE7 = $pdo->prepare("UPDATE products SET description = :nv, updated_at = datetime('now','localtime') WHERE slug = :sl AND description = :ov");
    $e7Append = "\n\nСостав: свежая клубника, шоколад (какао-продукты, сахар, какао-масло / макаруны: миндальная мука, белок, сахар, крем). Масса нетто: ~200 г. Срок годности: 24 часа с момента изготовления. Хранить при температуре от +2 до +6 °C. Изготовитель и декларация — уточняются при подтверждении заказа.";
    $fixE7->execute([':sl' => 'klubnika-shokolad-mini', ':ov' => 'Мини-набор клубники в бельгийском шоколаде: свежие ягоды в тёмной и молочной глазури. Порция рассчитана на двоих — сладкое дополнение к букету. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике и съешьте в течение суток: клубника не любит тепло.', ':nv' => 'Мини-набор клубники в бельгийском шоколаде: свежие ягоды в тёмной и молочной глазури. Порция рассчитана на двоих — сладкое дополнение к букету. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике и съешьте в течение суток: клубника не любит тепло.' . $e7Append]);
    $fixE7->execute([':sl' => 'klubnika-makarony-korobka', ':ov' => 'Клубника в шоколаде и макаруны в розовой коробке — сладкое дополнение к букету. Порция на двоих. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике не более суток: макаруны чувствительны к теплу, ягоды — ко времени.', ':nv' => 'Клубника в шоколаде и макаруны в розовой коробке — сладкое дополнение к букету. Порция на двоих. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике не более суток: макаруны чувствительны к теплу, ягоды — ко времени.' . $e7Append]);

    /* E10: «каретта роз» — несуществующий сорт, калека редактора. Guard по точному
       старому meta_description повода (значение — SELECT из БД до правки). */
    $pdo->exec("UPDATE occasions SET meta_description = 'Букет на день рождения с доставкой сегодня: именинный букет, открытка и шарик. Соберём под возраст и характер именинника — от полевых ромашек до акцентов из садовых роз.' WHERE slug = 'buket-na-den-rozhdeniya' AND meta_description = 'Букет на день рождения с доставкой сегодня: именинный букет, открытка и шарик. Соберём под возраст и характер именинника — от полевых ромашек до каретты роз.'");

    /* E11: вложенные «ёлочки» внутри «ёлочек» → немецкие „лапки“. Guard по точным
       старым текстам (description товара / intro повода — из SELECT до правки). */
    $pdo->exec("UPDATE products SET description = '25 красных роз в премиальной обёртке „матовый чёрный + золото“ — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.', updated_at = datetime('now','localtime') WHERE slug = '25-roz-cherno-zoloto' AND description = '25 красных роз в премиальной обёртке «матовый чёрный + золото» — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.'");
    $pdo->exec("UPDATE occasions SET intro = 'День рождения — повод, который не откладывают на „завтра“. Если заказ оформлен до 20:00, привезём букет в тот же день — в гости, в офис или на праздник.' WHERE slug = 'buket-na-den-rozhdeniya' AND intro = 'День рождения — повод, который не откладывают на «завтра». Если заказ оформлен до 20:00, привезём букет в тот же день — в гости, в офис или на праздник.'");

    /* E13а: обещание «по всем районам» при 3 зонах — дополняем демо-набор до 8.
       Guard: применяем ТОЛЬКО если в таблице ровно демо-набор из трёх (Центральный
       300 / Северный 400 / Южный 400) — зоны, правленые владельцем, не трогаем;
       внутри — INSERT только отсутствующих имён (повторный прогон — no-op).
       Позиционные параметры: PDO-sqlite не любит повтор одного именованного. */
    $e13DemoRows = (int)$pdo->query("SELECT COUNT(*) FROM delivery_zones WHERE
        (name = 'Центральный' AND price = 300) OR (name = 'Северный' AND price = 400) OR (name = 'Южный' AND price = 400)")->fetchColumn();
    $e13AllRows = (int)$pdo->query('SELECT COUNT(*) FROM delivery_zones')->fetchColumn();
    if ($e13DemoRows === 3 && $e13AllRows === 3) {
        $e13Zone = $pdo->prepare("INSERT INTO delivery_zones (name, price, sort)
            SELECT ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM delivery_zones WHERE name = ?)");
        foreach ([
            ['Петроградский', 350, 40],
            ['Василеостровский', 400, 50],
            ['Московский', 450, 60],
            ['Невский', 450, 70],
            ['Приморский', 500, 80],
        ] as [$e13Name, $e13Price, $e13Sort]) {
            $e13Zone->execute([$e13Name, $e13Price, $e13Sort, $e13Name]);
        }
    }

    /* E13б: SEO-текст главной «по всем районам Санкт-Петербурга» → «по районам
       Санкт-Петербурга» (guard по LIKE — replace идемпотентен: после применения
       фразы-поиска больше нет). Дефолт в index.php уже обновлён. */
    $pdo->exec("UPDATE settings SET value = replace(value, 'по всем районам Санкт-Петербурга', 'по районам Санкт-Петербурга') WHERE key = 'seo_text_body' AND value LIKE '%по всем районам Санкт-Петербурга%'");

    /* E14: hero-промо CTA «Оформить заказ» → #order вёл в тупик пустой корзины.
       Guard по точным старым дефолтам; дефолты в index.php/сиде уже новые. */
    $pdo->exec("UPDATE settings SET value = '#catalog' WHERE key = 'hero_promo_link' AND value = '#order'");
    $pdo->exec("UPDATE settings SET value = 'Выбрать букет' WHERE key = 'hero_promo_btn_text' AND value = 'Оформить заказ'");

    /* E13 (сопутствующее): FAQ «300–400 ₽» после расширения до 8 зон (макс. 500 ₽)
       обещал заниженный тариф. Guard по точному старому значению. */
    $pdo->exec("UPDATE settings SET value = 'Зависит от района: 300–500 ₽ по СПб, самовывоз — бесплатно. Точная сумма сразу видна при оформлении заказа.' WHERE key = 'faq_a1' AND value = 'Зависит от района: 300–400 ₽ по СПб, самовывоз — бесплатно. Точная сумма сразу видна при оформлении заказа.'");

    /* W99-fixG (G16): лейбл email-поля — поле НЕ required (атрибутов нет), email
       нужен для письма-подтверждения, а не только онлайн-оплаты. Guard по точному
       старому сид-значению 'Email'; дефолт в index.php уже новый. */
    $pdo->exec("UPDATE settings SET value = 'Email — для письма о заказе (необязательно)' WHERE key = 'form_email_label' AND value = 'Email'");

    /* W99-fixG (G17 nit): заголовок секции апселла без эмодзи (h2 с эмодзи —
       читалки озвучивают «балун»). Guard по точному старому значению
       'Дополните букет 🎈'; дефолт в index.php уже без эмодзи. */
    $pdo->exec("UPDATE settings SET value = 'Дополните букет' WHERE key = 'section_addons_title' AND value = 'Дополните букет 🎈'");

    /* W100-fixH1 (критики: редактор/юрист/CRO). Guard-UPDATE по ТОЧНЫМ старым
       значениям (паттерн W98-fixE): тексты, правленые владельцем через админку,
       не трогаем; идемпотентно — после применения value уже не равен old. */

    /* I4: заголовок секции поводов — «Цветы по поводу» → «Цветы по поводам»
       (грамматика: поводов много). Дефолт в index.php уже новый. */
    $pdo->exec("UPDATE settings SET value = 'Цветы по поводам' WHERE key = 'occasions_title' AND value = 'Цветы по поводу'");

    /* I8: title главной — тавтология «доставка… доставкой» и 73 символа;
       новый короткий «Доставка цветов по СПб — Nilov Flowers». Дефолт уже новый. */
    $pdo->exec("UPDATE settings SET value = 'Доставка цветов по СПб — Nilov Flowers' WHERE key = 'seo_title' AND value = 'Доставка цветов в СПб — Nilov Flowers | Свежие букеты с доставкой сегодня'");

    /* I9: FAQ a1 — «самовывоз — бесплатно» → «самовывоз бесплатный» (дефолт в
       index.php уже новый; guard по обоим историческим значениям — W97 и E13). */
    $pdo->exec("UPDATE settings SET value = 'Зависит от района: 300–500 ₽ по СПб, самовывоз бесплатный. Точная сумма сразу видна при оформлении заказа.' WHERE key = 'faq_a1' AND value = 'Зависит от района: 300–500 ₽ по СПб, самовывоз — бесплатно. Точная сумма сразу видна при оформлении заказа.'");
    $pdo->exec("UPDATE settings SET value = 'Зависит от района: 300–500 ₽ по СПб, самовывоз бесплатный. Точная сумма сразу видна при оформлении заказа.' WHERE key = 'faq_a1' AND value = 'Зависит от района: 300–400 ₽ по СПб, самовывоз — бесплатно. Точная сумма сразу видна при оформлении заказа.'");

    /* I17: zone_check_fallback — «не нашли» (неясно, что не нашли) → «район не
       найден». Дефолт в index.php уже новый. */
    $pdo->exec("UPDATE settings SET value = 'район не найден — уточним по телефону' WHERE key = 'zone_check_fallback' AND value = 'не нашли — уточним по телефону'");

    /* I14: Вебвизор Метрики — отдельная настройка metrika_webvisor, дефолт ВЫКЛ
       (приватность: запись сессий — самый чувствительный режим; включается
       владельцем осознанно через админку). metrika.php читает setting(). */
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('metrika_webvisor', '0')");

    /* I2: составы сладких наборов (блок E7) — переформат без слэш-склейки
       «шоколад (… / макаруны: …)»; мини-набор клубники — БЕЗ макарун в составе
       (рассинхрон с названием «мини-набор клубники»); «клубника и макаруны» —
       раздельные составы и масса ~250 г. Guard по slug + ТОЧНОМУ старому
       описанию (base + старый E7-аппенд — из SELECT до правки). */
    $h1BaseMini = 'Мини-набор клубники в бельгийском шоколаде: свежие ягоды в тёмной и молочной глазури. Порция рассчитана на двоих — сладкое дополнение к букету. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике и съешьте в течение суток: клубника не любит тепло.';
    $h1BaseMak = 'Клубника в шоколаде и макаруны в розовой коробке — сладкое дополнение к букету. Порция на двоих. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике не более суток: макаруны чувствительны к теплу, ягоды — ко времени.';
    $h1OldTail = "\n\nСостав: свежая клубника, шоколад (какао-продукты, сахар, какао-масло / макаруны: миндальная мука, белок, сахар, крем). Масса нетто: ~200 г. Срок годности: 24 часа с момента изготовления. Хранить при температуре от +2 до +6 °C. Изготовитель и декларация — уточняются при подтверждении заказа.";
    $fixH1Prod = $pdo->prepare("UPDATE products SET description = :nv, updated_at = datetime('now','localtime') WHERE slug = :sl AND description = :ov");
    $fixH1Prod->execute([':sl' => 'klubnika-shokolad-mini', ':ov' => $h1BaseMini . $h1OldTail,
        ':nv' => $h1BaseMini . "\n\nСостав: свежая клубника, шоколад тёмный и молочный (какао-продукты, сахар, какао-масло). Масса нетто: ~200 г. Срок годности: 24 часа. Хранить при +2…+6 °C. Изготовитель и декларация — уточняются при подтверждении заказа."]);
    $fixH1Prod->execute([':sl' => 'klubnika-makarony-korobka', ':ov' => $h1BaseMak . $h1OldTail,
        ':nv' => $h1BaseMak . "\n\nСостав: свежая клубника в шоколаде (какао-продукты, сахар, какао-масло); макаруны (миндальная мука, белок, сахар, крем). Масса нетто: ~250 г. Срок годности: 24 часа. Хранить при +2…+6 °C. Изготовитель и декларация — уточняются при подтверждении заказа."]);

    /* W101-fixI (K2/K9, дефекты хвоста W101). Guard-UPDATE по ТОЧНЫМ старым значениям
       (паттерн W97-fixB3a): тексты, правленые владельцем через админку, не трогаем;
       идемпотентно — после применения value уже не равен old. */

    /* K2: плейсхолдер поиска обещал пустой результат («букет маме» → 0 товаров)
       → дефолт «Розы, пионы, тюльпаны…» (все три стеммы находят букеты).
       Дефолт в partials/header.php уже новый; свежие БД — новое значение из сида. */
    $pdo->exec("UPDATE settings SET value = 'Розы, пионы, тюльпаны…' WHERE key = 'search_placeholder' AND value = 'Розы, пионы, букет маме…'");

    /* K9: «Розовое облако» без состава — «сезонные цветы одного тона» не говорило,
       что именно получит покупатель. Уточняем видами (по наличию — оферта
       допускает замену). Guard по slug + ТОЧНОМУ старому описанию. */
    $fixIProd = $pdo->prepare("UPDATE products SET description = :nv, updated_at = datetime('now','localtime') WHERE slug = :sl AND description = :ov");
    $fixIProd->execute([':sl' => 'avtorskij-rozovoe-oblako',
        ':ov' => 'Авторский монобукет в розовой гамме: сезонные цветы одного тона, собранные флористом утром. Размер — около 45 см, упаковка — крафт с лентой в тон. Повод: день рождения, рождение дочки, романтический повод без даты. Свежесть 7–10 дней при ежедневной смене воды.',
        ':nv' => 'Авторский монобукет в розовой гамме: сезонные цветы одного тона (гортензии, розы и альстромерии по наличию), собранные флористом утром. Размер — около 45 см, упаковка — крафт с лентой в тон. Повод: день рождения, рождение дочки, романтический повод без даты. Свежесть 7–10 дней при ежедневной смене воды.']);
    /* W102 (редактор-реванш): чистка текстов */
    $pdo->exec("UPDATE occasions SET body = replace(body, 'классика — чётное количество не носят, дарим нечётное число стеблей: 5, 7, 9, 15, 25.', 'классика — чётное число не дарят. Мы собираем букеты из нечётного числа стеблей — 5, 7, 9, 15, 25.') WHERE slug = 'buket-na-den-rozhdeniya' AND body LIKE '%чётное количество не носят%'");
    $pdo->exec("UPDATE settings SET value = 'Всегда бесплатно' WHERE key = 'hero_promo_badge' AND value = 'Всегда'");

    /* W102 (покупатель): способ оплаты на витрине — как в оферте (наличные ИЛИ карта курьеру) */
    $pdo->exec("UPDATE settings SET value = 'Наличными или картой при получении' WHERE key = 'pay_cash_label' AND value = 'При получении'");

    /* W101 (оркестратор/редактор): копирайтерская чистка повода «День рождения». */
$pdo->exec("UPDATE occasions SET meta_description = replace(meta_description, 'именинный букет, открытка и шарик', 'свежие цветы, бесплатная открытка с вашим текстом') WHERE slug = 'buket-na-den-rozhdeniya' AND meta_description LIKE '%открытка и шарик%'");
$pdo->exec("UPDATE occasions SET body = replace(body, 'дарим нечёт: 5, 7, 9, 15, 25 стеблей', 'дарим нечётное число стеблей: 5, 7, 9, 15, 25') WHERE slug = 'buket-na-den-rozhdeniya' AND body LIKE '%дарим нечёт:%'");
$pdo->exec("UPDATE occasions SET faq_q1 = replace(faq_q1, 'Какую карту приложите к букету?', 'Какую открытку приложить к букету?') WHERE slug = 'buket-na-den-rozhdeniya' AND faq_q1 LIKE '%Какую карту%'");

    /* W104 (редизайн-волна: типографико-контентные фиксы критиков C1-D1/C1-T).
       Guard-UPDATE по ТОЧНЫМ старым значениям (паттерн W97-fixE): правленое
       владельцем не трогаем; идемпотентно — после применения value ≠ old.
       Дефолты в шаблонах уже новые — это перенос на прод-БД, где ключи
       сохранены со старыми значениями. */
    /* Телефон-заглушка → реальный телефон владельца (шапка/футер/PDP/оферта) */
    $pdo->exec("UPDATE settings SET value = '+7 911 941-72-05' WHERE key = 'shop_phone' AND value IN ('+7 (900) 000-00-00', '+79000000000', '')");
    /* Давящий тон таймера → спокойный сервис (и ночной вариант без эмодзи) */
    $pdo->exec("UPDATE settings SET value = 'Заказ до {D} — доставим сегодня' WHERE key = 'countdown_text' AND value = 'Успейте заказать сегодня — осталось {T} до {D}'");
    $pdo->exec("UPDATE settings SET value = 'Примем заказ сейчас — доставим с 9:00 утра' WHERE key = 'countdown_night_text' AND value = 'Ночь. Заказ примем сейчас — доставим сегодня после 9:00'");
    /* Empty-state без эмодзи и с подсказкой */
    $pdo->exec("UPDATE settings SET value = 'Под эти фильтры ничего не подошло' WHERE key = 'catalog_empty_title' AND value = 'По этим фильтрам букетов не нашлось 🌷'");
    $pdo->exec("UPDATE settings SET value = 'Сбросьте цену или загляните в соседнюю категорию' WHERE key = 'catalog_empty_hint' AND value = 'Попробуйте убрать фильтр цены или выбрать другую категорию'");
    /* FAQ-вопросы: конкретика вместо тем */
    $pdo->exec("UPDATE settings SET value = 'Как быстро вы доставите букет?' WHERE key = 'faq_q2' AND value = 'Успею ли заказать сегодня?'");
    $pdo->exec("UPDATE settings SET value = 'Я увижу букет до доставки?' WHERE key = 'faq_q3' AND value = 'Как понять, что пришёл именно мой букет?'");
    /* Апселл-заголовок: уверенный вместо мнущегося */
    $pdo->exec("UPDATE settings SET value = 'Добавьте к букету' WHERE key = 'upsell_title' AND value = 'Возможно, пригодится'");
    /* SEO-титул главной: бренд вперёд, без разговорного «СПб» */
    $pdo->exec("UPDATE settings SET value = 'Nilov Flowers — доставка цветов по Санкт-Петербургу' WHERE key = 'seo_title' AND value = 'Доставка цветов по СПб — Nilov Flowers'");
    /* Город-бар: убрать дублирование слов */
    $pdo->exec("UPDATE settings SET value = 'Санкт-Петербург — ваш город?' WHERE key = 'citybar_text' AND value = 'Ваш город — Санкт-Петербург?'");

    /* W104-γ (css-identity-fixer — дедупликация текстов C2-T2). Guard-UPDATE
       по ТОЧНЫМ старым значениям (паттерн W104 выше): правленое владельцем
       не трогаем; идемпотентно — после применения value ≠ old. Дефолты
       в index.php/admin/settings.php уже новые — это перенос на прод-БД. */
    /* Саб секции 01 (хиты): «Хиты продаж / ВЫБОР покупателей / выбирают чаще
       всего» — одна мысль трижды → тихая подпись без канцелярита */
    $pdo->exec("UPDATE settings SET value = 'Выбор, который сложно испортить' WHERE key = 'section_hits_sub' AND value = 'Букеты, которые выбирают чаще всего'");
    /* Eyebrow 05 «ВЫГОДНО каждый день» (давящий торг) → «Красиво — не значит
       дорого»; формат капс|курсив|хвост (3-я часть — прямой текст) */
    $pdo->exec("UPDATE settings SET value = 'Красиво —|не значит|дорого' WHERE key = 'section_budget_eyebrow' AND value = 'Выгодно|каждый день'");
    /* Промо-карточка: «бесплатно» ×2 в двух строках → по одному разу */
    $pdo->exec("UPDATE settings SET value = 'К каждому букету' WHERE key = 'hero_promo_badge' AND value = 'Всегда бесплатно'");
    $pdo->exec("UPDATE settings SET value = 'Напишем ваш текст от руки и вложим в букет — это бесплатно' WHERE key = 'hero_promo_text' AND value = 'Напишем ваш текст от руки и вложим в букет — бесплатно, в каждом заказе'");
    /* Marquee пункт 2 дублировал пункт 1 («в день заказа» ×2) → конкретика SLA */
    $pdo->exec("UPDATE settings SET value = 'Срочная сборка — за 1–2 часа' WHERE key = 'marquee_2' AND value = 'Собираем и доставляем в день заказа'");

    /* W104-ζ (fix-волна 3 по критикам C3-D3/C3-T3 — тексты). Guard-UPDATE
       по ТОЧНЫМ старым значениям (паттерн W104 выше): правленое владельцем
       не трогаем; идемпотентно — после применения value ≠ old. Дефолты
       в шаблонах уже новые — это перенос на прод-БД. */
    /* C3-T3 P0-2: FAQ a2 начинался «Да:» — остаток yes/no-шаблона на вопрос
       без да/нет («Как быстро вы доставите букет?»). Прямой ответ без префикса. */
    $pdo->exec("UPDATE settings SET value = 'Оформите заказ до 20:00 — соберём и привезём в тот же день; срочно — за 1–2 часа.' WHERE key = 'faq_a2' AND value = 'Да: оформите заказ до 20:00 — соберём букет и привезём в тот же день, точное время согласуем по телефону. Срочные заказы собираем за 1–2 часа.'");
    /* C3-T3 P1.5: SEO-абзац про фото повторял почти дословно ответ FAQ a3 на
       той же странице — переформулирован (replace идемпотентен: после
       применения LIKE-подстроки больше нет). Дефолт index.php уже новый. */
    $pdo->exec("UPDATE settings SET value = replace(value, 'Перед доставкой курьер фотографирует готовый букет и присылает фото вам — вы видите то же, что получит адресат. Если что-то не так, заменим композицию до вручения без лишних вопросов.', 'Каждый заказ сопровождаем фото: вы видите букет до того, как его вручат.') WHERE key = 'seo_text_body' AND value LIKE '%Перед доставкой курьер фотографирует готовый букет и присылает фото вам%' AND value LIKE '%заменим композицию до вручения без лишних вопросов%'");
    $pdo->exec("UPDATE settings SET value = replace(value, 'Перед доставкой курьер фотографирует готовый букет и присылает фото вам — вы видите то же, что получит адресат. Если что-то не так, заменим композицию до вручения.', 'Каждый заказ сопровождаем фото: вы видите букет до того, как его вручат.') WHERE key = 'seo_text_body' AND value LIKE '%Перед доставкой курьер фотографирует готовый букет и присылает фото вам%' AND value LIKE '%заменим композицию до вручения%'");
    /* C3-T3 P1.4: манифест — тире открывало вторую строку (js/kinetic.js
       при сплите слов нормализует пробелы и съедает NBSP): дефолт переведён
       на бессрочную версию; NBSP-склейку на рендере делает index.php. */
    $pdo->exec("UPDATE settings SET value = 'Собираем букеты утром и везём вам сегодня' WHERE key = 'manifesto_text' AND value = 'Собираем букеты утром — и везём вам сегодня'");

    /* W105-7fix1 (арт-критик 7-a P1#1): hero-мастер 4:3 — старый petals-macro
       1440×736 (широкий) в слоте .fc-hero__main ~763×566 давал 1.45× вертикальный
       апскейл (мыло на ретине). Новый мастер img/editorial/petals-hero-4x3.jpg
       1152×864 = ровно 1.5× слота на DPR2, srcset 480/768/1024/1152w уже
       генерируется hero_img_size(). Guard по ТОЧНОМУ старому значению:
       правленое владельцем (свой аплоад) не трогаем; идемпотентно.
       Premium-фон по-прежнему petals-macro (осознанно). */
    $pdo->exec("UPDATE settings SET value='img/editorial/petals-hero-4x3.jpg' WHERE key='hero_image' AND value='img/editorial/petals-macro.jpg'");

    /* W105-8fix1 (критик-UX 8-b P1#3): CTA «Выбрать букет» прыгал к #catalog
       (~6258px), минуя ряд чипов цен (~781px) — золотой путь не видел
       быстрых фильтров. Якорь обоих hero-кнопок — #fcChips (id ряда чипов,
       index.php). Guard по ТОЧНОМУ прежнему дефолту '#catalog' — правленое
       владельцем значение не трогаем; идемпотентно. */
    $pdo->exec("UPDATE settings SET value='#fcChips' WHERE key='hero_button_link' AND value='#catalog'");
    $pdo->exec("UPDATE settings SET value='#fcChips' WHERE key='hero_promo_link' AND value='#catalog'");

    /* A-b4/W106 (критики: копирайтер 6.4, бизнес 6.5). Бутиковый нейминг —
       «продаём имена и эмоции, а не Букет из N роз»; количество стеблей
       переезжает из названия в описание. Guard-UPDATE по ТОЧНЫМ старым
       значениям (паттерн W97-fixB3a/W104): правленое владельцем через
       админку не трогаем; идемпотентно — после применения value ≠ old.
       Сид свежих БД (seedDemoData) уже вставляет финальные значения —
       этот блок переносит их на существующие БД. Slug НЕ меняем —
       стабильный идентификатор (ссылки, избранное, поиск). */

    /* 1) Переименования, описание не меняется */
    $b4Rename = $pdo->prepare("UPDATE products SET name = :nn, updated_at = datetime('now','localtime') WHERE slug = :sl AND name = :on");
    foreach ([
        ['polevye-tsvety', 'Полевые цветы', 'Летний луг'],
        ['buket-7-alstromerii-miks', 'Букет из 7 альстромерий микс', 'Семь оттенков'],
        ['buket-51-pion', 'Букет из 51 розового пиона', 'Пионовый сад'],
        ['gortenzii-shlyapnaya-korobka', 'Гортензии микс в шляпной коробке', 'Фарфор'],
        ['9-roz-shlyapnaya-korobka', '9 красных роз в шляпной коробке', 'Красная классика'],
        ['vesennij-buket-tyulpany', 'Весенний букет из тюльпанов', 'Оттепель'],
        ['pionovidnaya-klassika', 'Пионовидная классика', 'Первый танец'],
        ['avtorskij-kremovyj-son', 'Авторский букет «Кремовый сон»', 'Кремовый сон'],
        ['25-roz-gortenzii-korobka', '25 роз и гортензии в шляпной коробке', 'Белые ночи'],
        ['orhidei-rozy-kvadrat-korobka', 'Орхидеи и розы в квадратной коробке', 'Белый зал'],
    ] as [$b4Sl, $b4Old, $b4New]) {
        $b4Rename->execute([':sl' => $b4Sl, ':on' => $b4Old, ':nn' => $b4New]);
    }

    /* 2) Переименование + правка описания: флагман без состава (P0);
       складское «упаковка — крафт или фетр»; «монобукет» при смешанном
       составе; «по наличию»; немецкие кавычки E11. «Чёрное золото» —
       две строки: старое описание с „лапками“ (E11 применён) и с
       вложенными «ёлочками» (сид до E11); обе сводятся к одному тексту. */
    $b4Prod = $pdo->prepare("UPDATE products SET name = :nn, description = :nd, updated_at = datetime('now','localtime') WHERE slug = :sl AND name = :on AND description = :od");
    foreach ([
        ['buket-iz-roz', 'Букет из роз', 'Утренние розы',
            'Свежие розы с утренней поставки, собранные в круглый букет. Размер — стандартный, около 40–45 см в высоту. Упаковка — крафт с лентой. Подходит для свидания, дня рождения и благодарности без повода. В вазе стоит 7–10 дней: подрежьте стебли под углом, меняйте воду раз в сутки и держите букет вдали от солнца и батарей.',
            '15 роз тёплого красного оттенка с утренней поставки, собранные в плотный круглый букет. Размер — около 40–45 см в высоту, упаковка — крафт с атласной лентой. Подходит для свидания, дня рождения и благодарности без повода. В вазе стоит 7–10 дней: подрежьте стебли под углом, меняйте воду раз в сутки и держите букет вдали от солнца и батарей.'],
        ['buket-19-roz-evkalipt', 'Букет из 19 красных роз с эвкалиптом', 'Признание',
            '19 красных эквадорских роз длиной 50 см с веточками эвкалипта. Размер букета — около 50–55 см, упаковка — крафт или фетр. Классика для признания, годовщины и юбилея. Стоит в вазе 7–10 дней: подрежьте розы под углом, удалите нижние листья и не ставьте букет рядом с фруктами.',
            '19 красных эквадорских роз длиной 50 см с веточками эвкалипта. Размер букета — около 50–55 см, флорист подберёт упаковку в тон букета. Классика для признания, годовщины и юбилея. Стоит в вазе 7–10 дней: подрежьте розы под углом, удалите нижние листья и не ставьте букет рядом с фруктами.'],
        ['buket-5-rozovyh-roz', 'Букет из 5 розовых роз, Эквадор', 'Пудра',
            'Компактный букет из пяти розовых эквадорских роз — маленький знак внимания без повода. Размер — около 30–35 см, упаковка — плёнка или крафт. Подходит для благодарности коллеге, первого свидания и подарка учителю. Стоит в вазе 7–10 дней при ежедневной смене воды.',
            'Компактный букет из пяти розовых эквадорских роз — маленький знак внимания без повода. Размер — около 30–35 см, флорист подберёт упаковку в тон букета. Подходит для благодарности коллеге, первого свидания и подарка учителю. Стоит в вазе 7–10 дней при ежедневной смене воды.'],
        ['avtorskij-rozovoe-oblako', 'Авторский букет «Розовое облако»', 'Розовое облако',
            'Авторский монобукет в розовой гамме: сезонные цветы одного тона (гортензии, розы и альстромерии по наличию), собранные флористом утром. Размер — около 45 см, упаковка — крафт с лентой в тон. Повод: день рождения, рождение дочки, романтический повод без даты. Свежесть 7–10 дней при ежедневной смене воды.',
            'Авторский букет в розовой гамме: гортензии, розы и альстромерии в одном тоне — состав дышит вместе с сезоном, флорист собирает его утром. Размер — около 45 см, упаковка — крафт с лентой в тон. Повод: день рождения, рождение дочки, романтический повод без даты. Свежесть 7–10 дней при ежедневной смене воды.'],
        ['buket-belaya-nezhnost', 'Букет «Белая нежность»', 'Белая нежность',
            'Белые и кремовые цветы с эвкалиптом — спокойная элегантность без ярких акцентов. Размер — около 40–45 см, упаковка — белый крафт или фетр. Подходит для поздравления коллеги, выписки из роддома и цветов «на стол». Стоит в вазе до 7 дней: эвкалипт ароматен, воду меняйте раз в сутки.',
            'Белые и кремовые цветы с эвкалиптом — спокойная элегантность без ярких акцентов. Размер — около 40–45 см, флорист подберёт упаковку в тон букета. Подходит для поздравления коллеги, выписки из роддома и цветов «на стол». Стоит в вазе до 7 дней: эвкалипт ароматен, воду меняйте раз в сутки.'],
        ['buket-godovshina-krasnoe-beloe', 'Букет на годовщину «Красное и белое»', 'Красное и белое',
            'Красные и белые розы с гипсофилой — классика юбилея отношений. Размер — около 45–50 см, упаковка — крафт или фетр. Повод: годовщина свадьбы и знакомства, круглая дата. Стоит в вазе 7–10 дней при ежедневной смене воды и подрезке стеблей.',
            'Красные и белые розы с гипсофилой — классика юбилея отношений. Размер — около 45–50 см, флорист подберёт упаковку в тон букета. Повод: годовщина свадьбы и знакомства, круглая дата. Стоит в вазе 7–10 дней при ежедневной смене воды и подрезке стеблей.'],
        ['25-roz-cherno-zoloto', '25 красных роз в чёрно-золотой обёртке', 'Чёрное золото',
            '25 красных роз в премиальной обёртке „матовый чёрный + золото“ — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.',
            '25 красных роз в чёрно-золотой премиальной обёртке — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.'],
        ['25-roz-cherno-zoloto', '25 красных роз в чёрно-золотой обёртке', 'Чёрное золото',
            '25 красных роз в премиальной обёртке «матовый чёрный + золото» — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.',
            '25 красных роз в чёрно-золотой премиальной обёртке — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.'],
    ] as [$b4Sl, $b4Old, $b4New, $b4OldD, $b4NewD]) {
        $b4Prod->execute([':sl' => $b4Sl, ':on' => $b4Old, ':nn' => $b4New, ':od' => $b4OldD, ':nd' => $b4NewD]);
    }

    /* 3) «Красный акцент» — имя живо, описание: та же упаковка «или-или» */
    $pdo->exec("UPDATE products SET description = 'Красные розы с сезонной зеленью — букет, уместный всегда. Размер — около 45 см, флорист подберёт упаковку в тон букета. Повод: свидание, годовщина, «просто так». Стоит в вазе 7–10 дней: уберите нижние листья, подрежьте стебли под углом и не ставьте рядом с фруктами.', updated_at = datetime('now','localtime') WHERE slug = 'krasnyj-akcent' AND description = 'Красные розы с сезонной зеленью — букет, уместный всегда. Размер — около 45 см, упаковка — крафт или фетр. Повод: свидание, годовщина, «просто так». Стоит в вазе 7–10 дней: уберите нижние листья, подрежьте стебли под углом и не ставьте рядом с фруктами.'");

    /* 4) «СПб» → «Санкт-Петербург» в живых текстах настроек. Исторические
       guard-значения предыдущих волн выше не трогаем — на текущих БД они
       уже не применимы, а история миграций остаётся нетронутой. */
    $b4Set = $pdo->prepare('UPDATE settings SET value = :nv WHERE key = :k AND value = :ov');
    $b4Set->execute([':k' => 'faq_a1',
        ':ov' => 'Зависит от района: 300–500 ₽ по СПб, самовывоз бесплатный. Точная сумма сразу видна при оформлении заказа.',
        ':nv' => 'Зависит от района: 300–500 ₽ по Санкт-Петербургу, самовывоз бесплатный. Точная сумма сразу видна при оформлении заказа.']);
    $b4Set->execute([':k' => 'reviews_card_text',
        ':ov' => 'Нас находят по словам «цветы с доставкой СПб» и возвращаются за вторым букетом — это лучшая рекомендация. Все оценки и отзывы покупателей — в профиле на Яндекс Картах: там же можно оставить своё впечатление после доставки.',
        ':nv' => 'Нас находят по запросам о доставке цветов в Санкт-Петербурге и возвращаются за вторым букетом — это лучшая рекомендация. Все оценки и отзывы покупателей — в профиле на Яндекс Картах: там же можно оставить своё впечатление после доставки.']);
    $b4Set->execute([':k' => 'category_intro_polevye-cvety',
        ':ov' => 'Букеты из полевых и садовых цветов — лёгкие, ароматные, как из деревенского сада. Доставка по СПб в день заказа, оплата при получении.',
        ':nv' => 'Букеты из полевых и садовых цветов — лёгкие, ароматные, как из деревенского сада. Доставка по Санкт-Петербургу в день заказа, оплата при получении.']);
    $b4Set->execute([':k' => 'category_intro_avtorskie-bukety',
        ':ov' => 'Авторские композиции нашего флориста — собираем утром из свежей поставки, фото букета пришлём до отправки. Доставка по СПб в день заказа.',
        ':nv' => 'Авторские композиции нашего флориста — собираем утром из свежей поставки, фото букета пришлём до отправки. Доставка по Санкт-Петербургу в день заказа.']);

    /* 5) Немецкие кавычки E11 → ёлочки: вложенности «ёлочки в ёлочках» тут
       нет, „лапки“ излишни. Guard по точному старому значению (E11 мог
       уже примениться). */
    $pdo->exec("UPDATE occasions SET intro = 'День рождения — повод, который не откладывают на «завтра». Если заказ оформлен до 20:00, привезём букет в тот же день — в гости, в офис или на праздник.' WHERE slug = 'buket-na-den-rozhdeniya' AND intro = 'День рождения — повод, который не откладывают на „завтра“. Если заказ оформлен до 20:00, привезём букет в тот же день — в гости, в офис или на праздник.'");

    /* 6) Отзывы (соцдоказательства — «ноль отзывов на всём сайте»). Таблица —
       здесь по конвенции occasions: прод-БД уже создана, createBaseTables
       не повторится. product_id NULL = отзыв о магазине в целом; FK ON DELETE
       SET NULL — удалили товар, отзыв остаётся общим. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
        author TEXT NOT NULL,
        text TEXT NOT NULL,
        rating INTEGER NOT NULL DEFAULT 5 CHECK (rating BETWEEN 1 AND 5),
        source TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT (date('now','localtime'))
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_product ON reviews(product_id, created_at DESC)');

    /* Сид отзывов: существующие БД — прямо здесь (каталог уже есть); на
       свежих БД guard «товаров ещё нет» выводит, посев выполняет
       seedDemoData после вставки каталога. Результат идентичен. */
    seedReviewsOnce($pdo);

    /* ===== B5/W106: PDP-галерея (вторые ракурсы) + поводы (лестница цен) ===== */

    /* (а) products.image2 — имя файла второго фото в img/products (фото-волна
       B1: b1-<slug>-2.jpg + .webp + thumbs -400/-600, манифест
       state/b1-manifest.md). '' = второго ракурса нет — галерея PDP живёт
       как раньше (псевдослайд «Крупный план» из основного снимка).
       Конвенция guard-проверки колонок — как is_hit/is_premium выше. */
    $prodColsB5 = array_column($pdo->query("PRAGMA table_info(products)")->fetchAll(), 'name');
    if (!in_array('image2', $prodColsB5, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN image2 TEXT NOT NULL DEFAULT ''");
    }
    /* Сид вторых фото для существующих БД (свежие получает тот же набор из
       seedDemoData — на этом этапе товары ещё не вставлены). Guard по slug
       + пустой image2: правленое значение не трогаем, повтор — no-op. */
    $b5Img2 = $pdo->prepare('UPDATE products SET image2 = :f WHERE slug = :s AND image2 = :empty');
    foreach ([
        'buket-iz-roz', 'buket-7-alstromerii-miks', 'buket-19-roz-evkalipt', 'buket-51-pion',
        'gortenzii-shlyapnaya-korobka', 'pionovidnaya-klassika', 'avtorskij-kremovyj-son',
        'avtorskij-rozovoe-oblako', '25-roz-gortenzii-korobka', '25-roz-cherno-zoloto',
        '9-roz-shlyapnaya-korobka', 'orhidei-rozy-kvadrat-korobka', 'buket-godovshina-krasnoe-beloe',
        /* W106-G (фото-критик 5-c P1: вторые фото у всех товаров) — +6;
           рендер product.php с is_file-guard — безопасно до появления файлов */
        'buket-5-rozovyh-roz', 'vesennij-buket-tyulpany', 'buket-belaya-nezhnost',
        'solnechnyj-miks', 'klubnika-shokolad-mini', 'klubnika-makarony-korobka',
    ] as $b5Slug) {
        $b5Img2->execute([':f' => 'b1-' . $b5Slug . '-2.jpg', ':s' => $b5Slug, ':empty' => '']);
    }

    /* (б) Поводы: лестница цен (бизнес 6.5 P1 — «3 дешёвых букета, shopper
       с бюджетом 5–7к уходит без сценарного контекста»). Guard по ТОЧНОМУ
       демо-набору product_ids (после guard-цепочки W96 выше); подборки
       владельца из админки не трогаем. Свежие БД получают новые наборы
       прямо из сида occasions выше. Порядок вывода (хиты/премиум вперёд,
       потом по цене) считает occasion.php. */
    $b5Occ = $pdo->prepare('UPDATE occasions SET product_ids = :new WHERE slug = :sl AND product_ids = :old');
    foreach ([
        ['svadebnye-bukety', '1,7,8', '8,1,13,12,14,7'],
        ['buket-na-den-rozhdeniya', '2,8,11', '11,2,8,4,12,18,19'],
        ['traurnye-kompozicii', '3,16', '3,13,16,5,7,19'],
        ['buket-na-godovshinu', '20,5,21', '8,17,16,20,5,21,19'],
        ['buket-dlya-lyubimoj', '12,6,20', '8,1,17,12,20,14,6'],
    ] as [$b5OccSlug, $b5OldIds, $b5NewIds]) {
        $b5Occ->execute([':sl' => $b5OccSlug, ':old' => $b5OldIds, ':new' => $b5NewIds]);
    }

    /* (в) /category/rozy: интро без счёта стеблей (копирайтер 6.4 P1 —
       «до 25 роз» продаёт стебли, а не букет). Guard по ТОЧНОМУ старому
       значению; сид E1 выше уже вставляет новый текст в свежие БД. */
    $pdo->exec("UPDATE settings SET value = 'Розы с утренней поставки — от компактного знака внимания до крупных композиций в авторской упаковке. Подберём оттенок под повод и получателя: красная классика для признания, пудровые и кремовые тона — для нежного повода. Доставка по Санкт-Петербургу в день заказа, оплата при получении.' WHERE key = 'category_intro_rozy' AND value = 'Свежие розы с утренней поставки — от компактных монобукетов до 25 роз в премиальной упаковке. Подберём оттенок под повод — от красной классики до нежно-розовых, оплата при получении.'");

    /* ===== W106-C3 (флорист-критик 6.9 + редактор 7.4): сезонная честность,
       реальные зоны СПб, грамматика «из утренней поставки». Guard-UPDATE по
       ТОЧНЫМ старым значениям (паттерн W97-fixB3a/W98-fixE): правленое
       владельцем через админку не трогаем; идемпотентно — после применения
       value ≠ old. Сид свежих БД (seedDemoData/occasions выше) уже вставляет
       финальные значения — этот блок переносит их на существующие БД. ===== */

    /* (1) Зоны доставки: «Северный»/«Южный» — несуществующие районы СПб,
       Кировского (района самовывоза) не было. Демо-8 (W98-fixE) → 12 реальных
       районов лестницей 300–500 ₽, Петроградка объединена с Центром. Guard:
       применяем ТОЛЬКО к нетронутому демо-набору из 8 строк (зоны, правленые
       владельцем, не трогаем) — идемпотентно, после применения набор исчезает.
       Существующие строки — UPDATE (id и связи заказов сохраняются); фантомные
       «Северный»/«Южный» и слитый с Центром «Петроградский» — DELETE (orders.
       delivery_zone_id → NULL по FK ON DELETE SET NULL — заказы целы);
       недостающие районы — INSERT. Самовывоз — НЕ зона, а отдельная опция
       value="0" формы заказа (index.php: pickup_option_text/pickup_address;
       api/orders.php: «Зона доставки: 0 = самовывоз») — механика не меняется. */
    $w106c3ZoneHit = (int)$pdo->query("SELECT COUNT(*) FROM delivery_zones WHERE
        (name = 'Центральный' AND price = 300) OR (name = 'Северный' AND price = 400) OR
        (name = 'Южный' AND price = 400) OR (name = 'Петроградский' AND price = 350) OR
        (name = 'Василеостровский' AND price = 400) OR (name = 'Московский' AND price = 450) OR
        (name = 'Невский' AND price = 450) OR (name = 'Приморский' AND price = 500)")->fetchColumn();
    $w106c3ZoneAll = (int)$pdo->query('SELECT COUNT(*) FROM delivery_zones')->fetchColumn();
    if ($w106c3ZoneHit === 8 && $w106c3ZoneAll === 8) {
        $w106c3ZoneUpd = $pdo->prepare('UPDATE delivery_zones SET name = :n, price = :p, sort = :s WHERE name = :on AND price = :op');
        $w106c3ZoneUpd->execute([':on' => 'Центральный', ':op' => 300, ':n' => 'Центральный (и Петроградка)', ':p' => 300, ':s' => 10]);
        $w106c3ZoneUpd->execute([':on' => 'Василеостровский', ':op' => 400, ':n' => 'Василеостровский', ':p' => 300, ':s' => 20]);
        $w106c3ZoneUpd->execute([':on' => 'Приморский', ':op' => 500, ':n' => 'Приморский', ':p' => 400, ':s' => 60]);
        $w106c3ZoneUpd->execute([':on' => 'Московский', ':op' => 450, ':n' => 'Московский', ':p' => 400, ':s' => 70]);
        $w106c3ZoneUpd->execute([':on' => 'Невский', ':op' => 450, ':n' => 'Невский', ':p' => 450, ':s' => 90]);
        $pdo->exec("DELETE FROM delivery_zones WHERE name IN ('Северный', 'Южный', 'Петроградский')");
        $w106c3ZoneIns = $pdo->prepare("INSERT INTO delivery_zones (name, price, sort)
            SELECT ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM delivery_zones WHERE name = ?)");
        foreach ([
            ['Адмиралтейский', 300, 30],
            ['Выборгский', 350, 40],
            ['Калининский', 350, 50],
            ['Фрунзенский', 400, 80],
            ['Красногвардейский', 450, 100],
            ['Кировский (и Красносельский)', 450, 110],
            ['Пушкин (Павловск, Петергоф)', 500, 120],
        ] as [$w106c3Zn, $w106c3Zp, $w106c3Zs]) {
            $w106c3ZoneIns->execute([$w106c3Zn, $w106c3Zp, $w106c3Zs, $w106c3Zn]);
        }
    }

    /* (2) Товарные тексты (guard: slug + ТОЧНОЕ старое описание; поле image
       НЕ трогаем — фото меняет отдельная волна):
       «Пионовый сад» — 51 пион в сентябре невозможен экономически; честный
       состав: 51 пионовидная роза (garden roses, круглогодично), имя живёт;
       стойкость пионовидных роз 7–10 дней, а не 5–7, как у пионов.
       «Оттепель» — нарциссов осенью нет: голландские тюльпаны (поставки
       круглый год); «8 Марта»/«Пасха» → «весеннее настроение в любой сезон»;
       стойкость тюльпанов 5–7 дней и «подальше от батареи» (актуально
       осенью-зимой) сохранены.
       «Чёрное золото»/«Белый зал» — премиум-карточки беднее хитов (редактор
       P1): размер «~N см» конкретно (25 роз — 50–55 см в высоту; коробка +
       высота композиции).
       «Утренние розы» — «с утренней поставки» → «из утренней поставки». */
    $w106c3Prod = $pdo->prepare("UPDATE products SET description = :nv, updated_at = datetime('now','localtime') WHERE slug = :sl AND description = :ov");
    foreach ([
        ['buket-51-pion',
            '51 розовый пион в нежной обёртке — крупная композиция для особого случая. Размер — около 50–55 см в диаметре, упаковка — фетр. Повод: свадьба, юбилей, рождение ребёнка. Пионы раскрываются на второй день и цветут 5–7 дней: прохлада, свежая вода и подрезка стеблей продлевают праздник.',
            'Пятьдесят одна пионовидная роза кремово-розовой гаммы в нежной обёртке — крупная композиция для особого случая. Размер — около 50–55 см в диаметре, упаковка — фетр. Повод: свадьба, юбилей, рождение ребёнка. Пионовидные розы раскрываются медленно и стоят в вазе 7–10 дней: прохлада, свежая вода и подрезка стеблей продлевают праздник.'],
        ['vesennij-buket-tyulpany',
            'Весенний микс тюльпанов и нарциссов в крафт-упаковке. Размер — около 35–40 см. Повод: 8 Марта, Пасха или просто весеннее настроение. Тюльпаны живут в вазе 5–7 дней и продолжают расти: подрезайте стебли и держите букет в прохладе, подальше от батареи.',
            'Голландские тюльпаны тёплых оттенков в крафт-упаковке. Размер — около 35–40 см. Повод: день рождения, свидание или весеннее настроение в любой сезон — тюльпаны приезжают из Голландии круглый год. Тюльпаны живут в вазе 5–7 дней и продолжают расти: подрезайте стебли и держите букет в прохладе, подальше от батареи.'],
        ['25-roz-cherno-zoloto',
            '25 красных роз в чёрно-золотой премиальной обёртке — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.',
            '25 красных роз в чёрно-золотой премиальной обёртке — страстный жест. Размер — около 50–55 см в высоту. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.'],
        ['orhidei-rozy-kvadrat-korobka',
            'Белые орхидеи с розами в квадратной коробке — статусная композиция для делового подарка. Сторона коробки — около 30 см. Повод: поздравление партнёра, открытие офиса, юбилей руководителя. Свежесть 10–14 дней: орхидеи стойкие, доливайте воду во флористическую губку.',
            'Белые орхидеи с розами в квадратной коробке — статусная композиция для делового подарка. Сторона коробки — около 30 см, высота композиции — около 35–40 см. Повод: поздравление партнёра, открытие офиса, юбилей руководителя. Свежесть 10–14 дней: орхидеи стойкие, доливайте воду во флористическую губку.'],
        ['buket-iz-roz',
            '15 роз тёплого красного оттенка с утренней поставки, собранные в плотный круглый букет. Размер — около 40–45 см в высоту, упаковка — крафт с атласной лентой. Подходит для свидания, дня рождения и благодарности без повода. В вазе стоит 7–10 дней: подрежьте стебли под углом, меняйте воду раз в сутки и держите букет вдали от солнца и батарей.',
            '15 роз тёплого красного оттенка из утренней поставки, собранные в плотный круглый букет. Размер — около 40–45 см в высоту, упаковка — крафт с атласной лентой. Подходит для свидания, дня рождения и благодарности без повода. В вазе стоит 7–10 дней: подрежьте стебли под углом, меняйте воду раз в сутки и держите букет вдали от солнца и батарей.'],
    ] as [$w106c3Sl, $w106c3Old, $w106c3New]) {
        $w106c3Prod->execute([':sl' => $w106c3Sl, ':ov' => $w106c3Old, ':nv' => $w106c3New]);
    }

    /* (3) Поводы: «пионы» как обещание состава (сентябрь — свежих пионов нет
       до следующего сезона) → пионовидные розы; FAQ «если она не любит розы» —
       ответ без роз (было «Пионы, …» — а пионовидная роза тоже роза).
       Свадебные мета/текст — «из утренней поставки». LIKE+replace —
       идемпотентно: после применения подстроки поиска больше нет. */
    $pdo->exec("UPDATE occasions SET meta_description = replace(meta_description, 'Свежие цветы с утренней поставки, доставка к церемонии', 'Свежие цветы из утренней поставки, доставка к церемонии') WHERE slug = 'svadebnye-bukety' AND meta_description LIKE '%Свежие цветы с утренней поставки, доставка к церемонии%'");
    $pdo->exec("UPDATE occasions SET body = replace(body, 'поступают с утренней поставки в день торжества', 'поступают из утренней поставки в день торжества') WHERE slug = 'svadebnye-bukety' AND body LIKE '%поступают с утренней поставки в день торжества%'");
    $pdo->exec("UPDATE occasions SET body = replace(body, 'из 25+ роз или пионы.', 'из 25+ роз или пионовидных роз.') WHERE slug = 'buket-na-godovshinu' AND body LIKE '%из 25+ роз или пионы%'");
    $pdo->exec("UPDATE occasions SET meta_description = replace(meta_description, 'для любимой: пионы, розы, нежные', 'для любимой: пионовидные и классические розы, нежные') WHERE slug = 'buket-dlya-lyubimoj' AND meta_description LIKE '%для любимой: пионы, розы%'");
    $pdo->exec("UPDATE occasions SET intro = replace(intro, 'пышные пионы, пионовидные розы и пастельные миксы', 'пышные пионовидные розы и пастельные миксы') WHERE slug = 'buket-dlya-lyubimoj' AND intro LIKE '%пышные пионы%'");
    $pdo->exec("UPDATE occasions SET body = replace(body, 'нежные розовые пионы или пионовидные розы — самый', 'нежные пионовидные розы — самый') WHERE slug = 'buket-dlya-lyubimoj' AND body LIKE '%нежные розовые пионы%'");
    $pdo->exec("UPDATE occasions SET faq_a2 = replace(faq_a2, 'Пионы, гортензии или авторский сезонный микс', 'Гортензии, тюльпаны или авторский сезонный микс') WHERE slug = 'buket-dlya-lyubimoj' AND faq_a2 LIKE 'Пионы, гортензии%'");

    /* (4) «Розы с утренней поставки» → «Розы из утренней поставки» (интро
       категории /rozy) и тот же оборот в SEO-абзаце главной. LIKE+replace. */
    $pdo->exec("UPDATE settings SET value = replace(value, 'Розы с утренней поставки — от компактного', 'Розы из утренней поставки — от компактного') WHERE key = 'category_intro_rozy' AND value LIKE 'Розы с утренней поставки — от компактного%'");
    $pdo->exec("UPDATE settings SET value = replace(value, 'поступают к нам с утренней поставки', 'поступают к нам из утренней поставки') WHERE key = 'seo_text_body' AND value LIKE '%поступают к нам с утренней поставки%'");

    /* ===== D-d3 (конкурент P0, волна 2): честный адрес без выдуманной улицы.
       «Полевая Сабировская ул., 47» — улицы в Санкт-Петербурге нет, локальный
       покупатель проверяет за минуту — доверие обнуляется. shop_address —
       районный уровень без улицы/дома (рендер: футер «Контакты», JSON-LD
       addressString); pickup_address — честное описание самовывоза (рендер:
       форма «Как получить» index.php, мета-блок product.php, шаги order-thanks).
       Guard по ТОЧНЫМ старым значениям — адрес, правленный владельцем, не
       трогаем; после применения value ≠ old → идемпотентно. pickup_address
       в старых сидах был пуст (рендер падал в фолбэк на shop_address с той же
       выдуманной улицей) — заполняем ТОЛЬКО пустое значение. Свежие БД получают
       оба значения прямо из INSERT OR IGNORE выше (сид == миграция). ===== */
    $pdo->exec("UPDATE settings SET value = 'г. Санкт-Петербург, Петроградская сторона' WHERE key = 'shop_address' AND value = 'г. Санкт-Петербург, Полевая Сабировская ул., 47, корп. 1'");
    $pdo->exec("UPDATE settings SET value = 'из студии в центре Петроградской стороны (точный адрес и время сообщим при подтверждении заказа)' WHERE key = 'pickup_address' AND value = ''");

    /* ===== E-e3 (корректор 7.7 + креативный директор 7.5, волна E): терминология
       «фото букета до отправки» (было вперемешку «перед отправкой»/«перед
       доставкой»), «обёртка» → «упаковка» в премиум-описании, единый стандарт
       смены воды «раз в сутки». Guard-UPDATE по ТОЧНЫМ старым значениям /
       идемпотентный LIKE+replace (паттерн W97-fixB3a/W98-fixE/W106-C3): правленое
       владельцем через админку не трогаем; после применения value ≠ old.
       Сид свежих БД уже вставляет финальные значения — блок переносит их на
       существующие БД. Исторические guard-цепочки выше не меняем: они ведут
       к прежнему финальному тексту, который ловит guard ниже в этом же проходе. ===== */

    /* (1) «Чёрное золото»: «в чёрно-золотой премиальной обёртке» удешевляет
           премиум-карточку (креативный директор) → «в чёрной с золотом упаковке».
           Guard по ТОЧНОМУ старому — финальному тексту W106-C3 (цепочка
           E11 → b4 → C3 выше приводит именно к нему). */
    $pdo->exec("UPDATE products SET description = '25 красных роз в чёрной с золотом упаковке — страстный жест. Размер — около 50–55 см в высоту. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.', updated_at = datetime('now','localtime') WHERE slug = '25-roz-cherno-zoloto' AND description = '25 красных роз в чёрно-золотой премиальной обёртке — страстный жест. Размер — около 50–55 см в высоту. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.'");

    /* (2) Уход за букетом — противоречие воды: розы каталога «меняйте воду раз
           в сутки» vs «Первый танец» «раз в 1–2 дня». Это смена воды в вазе —
           унифицируем к стандарту «раз в сутки». Долив в коробку («каждые
           1–2 дня», «через день» у коробочных) — другой смысл, не трогаем.
           LIKE+replace — идемпотентно. */
    $pdo->exec("UPDATE products SET description = replace(description, 'меняйте воду раз в 1–2 дня', 'меняйте воду раз в сутки'), updated_at = datetime('now','localtime') WHERE slug = 'pionovidnaya-klassika' AND description LIKE '%меняйте воду раз в 1–2 дня%'");

    /* (3) Терминология «фото букета до отправки» — живые тексты: гарантия
           (траст-ряд главной + мета-блок PDP), FAQ главной, FAQ повода. */
    $pdo->exec("UPDATE settings SET value = 'Фото букета до отправки' WHERE key = 'guarantee_1' AND value = 'Фото букета перед отправкой'");
    $pdo->exec("UPDATE settings SET value = replace(value, 'Перед доставкой курьер фотографирует готовый букет и присылает фото вам', 'До отправки курьер фотографирует готовый букет и присылает фото вам') WHERE key = 'faq_a3' AND value LIKE '%Перед доставкой курьер фотографирует%'");
    $pdo->exec("UPDATE occasions SET faq_a2 = replace(faq_a2, 'покажем фото перед отправкой', 'покажем фото до отправки') WHERE slug = 'buket-dlya-lyubimoj' AND faq_a2 LIKE '%покажем фото перед отправкой%'");

    /* (4) Хвост-CTA каталога «фото готового букета пришлём перед доставкой» →
           «до отправки». Ключ не в админ-allowlist — в БД его нет, обе страницы
           (index/category) рендерят PHP-дефолты; сеем единое значение в БД,
           чтобы дефолты страниц не расходились. Guard — на случай уже
           сохранённого старого дефолта (практически невозможно: ключ
           недоступен из админки). */
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('catalog_tail_text', 'Под ваш повод, палитру и бюджет — фото готового букета пришлём до отправки')");
    $pdo->exec("UPDATE settings SET value = 'Под ваш повод, палитру и бюджет — фото готового букета пришлём до отправки' WHERE key = 'catalog_tail_text' AND value = 'Под ваш повод, палитру и бюджет — фото готового букета пришлём перед доставкой'");

    /* (5) Тот же термин в ключах, которых до сих пор НЕ было в БД (рендерились
           PHP-дефолты index.php со старой формулировкой): SEO-description главной
           (meta name + twitter:description), строка карточки доставки hero,
           строка-разделитель каталога. Ключи админ-allowlisted — сеем единые
           значения (владелец правит через админку как обычно). og:description
           главной захардкожен в index.php — зона index-агента, осознанный остаток. */
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES
        ('seo_description', 'Доставка букетов по Санкт-Петербургу в день заказа. Свежий срез каждое утро, фото букета до отправки. Заказы до 20:00 — доставим сегодня.'),
        ('hero_delivery_point_2', 'Фото до отправки'),
        ('catalog_strip_text', 'Каждый букет собираем утром — и фотографируем до отправки')");

}

/* A-b4/W106: сид отзывов — ОБЩИЙ для свежих и существующих БД (сид для
   свежих + миграция для существующих дают одинаковый результат). Вызывается
   из migrateSchema (существующие БД) и из seedDemoData (свежие — после
   вставки товаров). product_id резолвим по slug, а не хардкодим id: на
   каталоге владельца демо-отзывы о товарах просто не сеем — «висячих»
   ссылок нет. Guard: только если каталог не пуст и таблица отзывов пуста.
   Даты — последние ~3 недели, формат 'YYYY-MM-DD' (день, без времени).
   Тон живой, СПб-контекст, без рекламных суперлативов. */
function seedReviewsOnce(PDO $pdo): void
{
    /* Свежая БД: migrateSchema выполняется ДО seedDemoData, товаров ещё нет —
       уходим; отзывы сеет вызов из seedDemoData, когда slug уже резолвится. */
    if ((int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        return;
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn() > 0) {
        return;
    }
    $bySlug = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND is_active = 1');
    $ins = $pdo->prepare('INSERT INTO reviews (product_id, author, text, rating, source, created_at)
        VALUES (:pid, :author, :text, :rating, :source, :created)');
    $day = fn (int $n): string => date('Y-m-d', time() - $n * 86400);
    /* [slug товара | null = отзыв о магазине, автор, текст, рейтинг, источник, дата] */
    $reviews = [
        ['buket-iz-roz', 'Марина', 'Заказывала «Утренние розы» просто так, без повода — стоят четвёртый день, ни одного поникшего листа. Собран плотно, выглядит как на фото.', 5, 'Яндекс Карты', $day(2)],
        [null, 'Анна К.', 'Курьер прислал фото до отправки — букет совпал с картинкой, даже оттенок тот же. Доставили на Петроградку за пару часов.', 5, 'Яндекс Карты', $day(3)],
        ['25-roz-gortenzii-korobka', 'Сергей', 'Заказывал «Белые ночи» на мамин юбилей. Коробка смотрится дорого, розы с гортензиями простояли больше недели — мама доливала воду, как в описании.', 5, 'Яндекс Карты', $day(4)],
        [null, 'Ольга', 'Живу в Московском районе, привезли в тот же день. Опоздали минут на десять, но курьер предупредил по телефону. Букет свежий, простоял как обещали.', 4, 'Яндекс Карты', $day(5)],
        [null, 'Дмитрий', 'Заказал утром, к вечеру жена уже переставляла букет в вазу. Открытку написали от руки — она решила, что я писал сам.', 5, 'Яндекс Карты', $day(7)],
        [null, 'Ирина', 'Альстромерии простояли почти две недели. Брала на юбилей коллеге, потом заказала такие же себе — пастельные оттенки, как в описании.', 5, '2ГИС', $day(9)],
        [null, 'Екатерина В.', 'Гортензии простояли неделю — для гортензий это хороший результат. Воду доливала прямо в коробку, удобно.', 5, 'Яндекс Карты', $day(11)],
        [null, 'Павел', 'Оформил заказ в 18:40, успел до отсечки в 20:00 — привезли в тот же день. Хотелось бы больше выбора сладких дополнений, а так всё чётко.', 4, 'Яндекс Карты', $day(13)],
        [null, 'Наталья', 'Не разбираюсь в цветах — позвонила, объяснила повод и бюджет, флорист собрала букет сама. Простоял девять дней, гости спрашивали, где брала.', 5, '2ГИС', $day(15)],
        ['buket-5-rozovyh-roz', 'Александр', 'Дарил «Пудру» на первом свидании — маленький букет, но собран со вкусом. Она потом спросила, как он называется.', 5, 'Яндекс Карты', $day(17)],
        [null, 'Виктор', 'Заказывал в офис коллеге на день рождения. Позвонили, уточнили время, приехали минута в минуту. Коробка аккуратная, вазу искать не пришлось.', 5, 'Яндекс Карты', $day(19)],
        [null, 'Светлана', 'Клубника в шоколаде приехала холодной, ягоды свежие. Букет и подарок привезли одной доставкой — удобно, когда идёшь в гости.', 5, '2ГИС', $day(21)],
    ];
    foreach ($reviews as [$slug, $author, $text, $rating, $source, $created]) {
        $productId = null;
        if ($slug !== null) {
            $bySlug->execute([$slug]);
            $id = $bySlug->fetchColumn();
            if ($id === false) {
                continue; /* демо-товар отсутствует (каталог владельца) — не сеем */
            }
            $productId = (int)$id;
        }
        $ins->execute([':pid' => $productId, ':author' => $author, ':text' => $text,
            ':rating' => $rating, ':source' => $source, ':created' => $created]);
    }
}

/* A-b4/W106: отзывы о магазине (product_id IS NULL) или о конкретном товаре.
   Кэш в рамках запроса — static-паттерн как в db(). Порядок: свежие сверху. */
function getReviews(?int $productId = null, int $limit = 6): array
{
    static $cache = [];
    $key = $productId === null ? 'site' : 'p' . $productId;
    if (!isset($cache[$key])) {
        if ($productId === null) {
            $cache[$key] = db()->query('SELECT id, product_id, author, text, rating, source, created_at
                FROM reviews WHERE product_id IS NULL ORDER BY created_at DESC, id DESC')->fetchAll();
        } else {
            $st = db()->prepare('SELECT id, product_id, author, text, rating, source, created_at
                FROM reviews WHERE product_id = ? ORDER BY created_at DESC, id DESC');
            $st->execute([$productId]);
            $cache[$key] = $st->fetchAll();
        }
    }
    return array_slice($cache[$key], 0, max(0, $limit));
}

/* A-b4/W106: агрегат рейтинга по всем отзывам сайта.
   Возвращает массив ['count' => int, 'avg' => float] (avg — округление до 0.1). */
function getRatingAggregate(): array
{
    static $agg = null;
    if ($agg === null) {
        $row = db()->query('SELECT COUNT(*) AS cnt, ROUND(AVG(rating), 1) AS avg FROM reviews')->fetch();
        $agg = ['count' => (int)$row['cnt'], 'avg' => (float)($row['avg'] ?? 0)];
    }
    return $agg;
}
