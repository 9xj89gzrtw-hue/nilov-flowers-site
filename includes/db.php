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
        (1, 'Букет из роз', 'buket-iz-roz', 2500, NULL, 'Свежие розы с утренней поставки, собранные в круглый букет. Размер — стандартный, около 40–45 см в высоту. Упаковка — крафт с лентой. Подходит для свидания, дня рождения и благодарности без повода. В вазе стоит 7–10 дней: подрежьте стебли под углом, меняйте воду раз в сутки и держите букет вдали от солнца и батарей.', 'roz2.jpg', 1, 0, 0, 10),
        (2, 'Весенний этюд', 'vesennij-etyud', 2500, 2100, 'Сборный букет в весенней палитре: сезонные цветы нежных оттенков и свежая зелень. Размер — около 40–45 см, упаковка — крафт с атласной лентой. Повод: 8 Марта, день рождения, визит в гости. Свежесть до 7 дней — подрезайте стебли и меняйте воду ежедневно, увядшие бутоны убирайте сразу.', 'p2.jpg', 0, 0, 0, 20),
        (3, 'Полевые цветы', 'polevye-tsvety', 2100, NULL, 'Лёгкий букет из полевых цветов — ромашки, колокольчики и сезонные травы. Размер — около 35–40 см, упаковка — крафт без лишнего декора. Уместен как знак внимания без повода и как подарок для дачи или загородного дома. Живёт в вазе 5–7 дней: прохлада и ежедневная смена воды продлевают свежесть.', 'p3.jpg', 0, 0, 0, 30),
        (2, 'Букет из 7 альстромерий микс', 'buket-7-alstromerii-miks', 2900, NULL, 'Семь альстромерий в пастельной гамме — лёгкий букет на каждый день. Размер — компактный, около 35–40 см. Упаковка — плёнка с лентой в тон. Повод: благодарность коллеге, врачу или учителю, небольшой юбилей. Альстромерии стоят в вазе 10–14 дней: меняйте воду раз в два дня и подрезайте стебли.', 'gen7.jpg', 1, 0, 0, 40),
        (1, 'Букет из 19 красных роз с эвкалиптом', 'buket-19-roz-evkalipt', 4900, NULL, '19 красных эквадорских роз длиной 50 см с веточками эвкалипта. Размер букета — около 50–55 см, упаковка — крафт или фетр. Классика для признания, годовщины и юбилея. Стоит в вазе 7–10 дней: подрежьте розы под углом, удалите нижние листья и не ставьте букет рядом с фруктами.', 'gen8.jpg', 1, 0, 0, 50),
        (4, 'Букет из 51 розового пиона', 'buket-51-pion', 11900, NULL, '51 розовый пион в нежной обёртке — крупная композиция для особого случая. Размер — около 50–55 см в диаметре, упаковка — фетр. Повод: свадьба, юбилей, рождение ребёнка. Пионы раскрываются на второй день и цветут 5–7 дней: прохлада, свежая вода и подрезка стеблей продлевают праздник.', 'gen9.jpg', 0, 1, 0, 60),
        (5, 'Гортензии микс в шляпной коробке', 'gortenzii-shlyapnaya-korobka', 8900, NULL, 'Белые и голубые гортензии в круглой шляпной коробке — статусный подарок, который не нужно переставлять в вазу. Диаметр композиции — около 30–35 см. Повод: новоселье, день рождения, деловой подарок. Гортензии пьют много: доливайте воду в коробку каждые 1–2 дня, свежесть сохранится до недели.', 'gen10.jpg', 0, 1, 0, 70),
        (1, 'Букет из 5 розовых роз, Эквадор', 'buket-5-rozovyh-roz', 2300, NULL, 'Компактный букет из пяти розовых эквадорских роз — маленький знак внимания без повода. Размер — около 30–35 см, упаковка — плёнка или крафт. Подходит для благодарности коллеге, первого свидания и подарка учителю. Стоит в вазе 7–10 дней при ежедневной смене воды.', 'gen11.jpg', 0, 0, 0, 80),
        (6, 'Мини-набор клубники в шоколаде', 'klubnika-shokolad-mini', 990, NULL, 'Мини-набор клубники в бельгийском шоколаде: свежие ягоды в тёмной и молочной глазури. Порция рассчитана на двоих — сладкое дополнение к букету. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике и съешьте в течение суток: клубника не любит тепло.', 'gen12.jpg', 0, 0, 1, 90),
        (5, '9 красных роз в шляпной коробке', '9-roz-shlyapnaya-korobka', 5500, NULL, 'Девять красных роз с гипсофилой в круглой шляпной коробке — подарок, не требующий вазы. Диаметр — около 25–30 см. Повод: годовщина, признание, визит к родителям. Розы стоят 7–10 дней: доливайте воду в коробку через день и держите композицию вдали от солнца.', 'gen13.jpg', 1, 0, 0, 100),
        (2, 'Весенний букет из тюльпанов', 'vesennij-buket-tyulpany', 1900, NULL, 'Весенний микс тюльпанов и нарциссов в крафт-упаковке. Размер — около 35–40 см. Повод: 8 Марта, Пасха или просто весеннее настроение. Тюльпаны живут в вазе 5–7 дней и продолжают расти: подрезайте стебли и держите букет в прохладе, подальше от батареи.', 'gen14.jpg', 0, 0, 1, 110),
        (2, 'Авторский букет «Розовое облако»', 'avtorskij-rozovoe-oblako', 4200, NULL, 'Авторский монобукет в розовой гамме: сезонные цветы одного тона, собранные флористом утром. Размер — около 45 см, упаковка — крафт с лентой в тон. Повод: день рождения, рождение дочки, романтический повод без даты. Свежесть 7–10 дней при ежедневной смене воды.', 'gen1.jpg', 1, 0, 0, 120),
        (2, 'Букет «Белая нежность»', 'buket-belaya-nezhnost', 3500, NULL, 'Белые и кремовые цветы с эвкалиптом — спокойная элегантность без ярких акцентов. Размер — около 40–45 см, упаковка — белый крафт или фетр. Подходит для поздравления коллеги, выписки из роддома и цветов «на стол». Стоит в вазе до 7 дней: эвкалипт ароматен, воду меняйте раз в сутки.', 'gen2.jpg', 0, 0, 0, 130),
        (4, 'Пионовидная классика', 'pionovidnaya-klassika', 6800, NULL, 'Крупные пионовидные розы — букет, который запомнится. Размер — около 50 см, упаковка — фетр. Повод: помолвка, юбилей, годовщина свадьбы. Пионовидные розы раскрываются медленно и стоят в вазе 10–14 дней: подрежьте стебли под углом и меняйте воду раз в 1–2 дня.', 'gen3.jpg', 0, 1, 0, 140),
        (2, 'Солнечный микс', 'solnechnyj-miks', 2700, 2200, 'Жёлто-оранжевый микс сезонных цветов — букет-заряд бодрости. Размер — около 40 см, упаковка — крафт. Повод: выздоровление, поздравление учителю, день рождения друга. Живёт в вазе 5–7 дней: подрезайте стебли и меняйте воду ежедневно, букет любит свет без прямого солнца.', 'gen4.jpg', 0, 0, 0, 150),
        (1, 'Красный акцент', 'krasnyj-akcent', 3800, NULL, 'Красные розы с сезонной зеленью — букет, уместный всегда. Размер — около 45 см, упаковка — крафт или фетр. Повод: свидание, годовщина, «просто так». Стоит в вазе 7–10 дней: уберите нижние листья, подрежьте стебли под углом и не ставьте рядом с фруктами.', 'gen5.jpg', 0, 0, 0, 160),
        (2, 'Романтика', 'romantika', 3100, 2700, 'Пастельный букет с гипсофилой — нежность в каждой детали. Размер — около 40 см, упаковка — плёнка с атласной лентой. Повод: первое свидание, день знакомства, извинение. Свежесть до 7 дней: гипсофила осыпается к концу недели, основные цветы проживут дольше.', 'gen6.jpg', 0, 0, 0, 170),
        (4, 'Авторский букет «Кремовый сон»', 'avtorskij-kremovyj-son', 5400, NULL, 'Ранункулюсы и лизиантусы в шёлковой обёртке — нежная авторская композиция. Размер — около 40–45 см. Повод: свадебный подарок, день рождения, рождение ребёнка. Ранункулюсы стоят 5–7 дней, лизиантусы — до 10: подрезайте стебли и меняйте воду ежедневно.', 'gen15.jpg', 0, 1, 0, 180),
        (5, '25 роз и гортензии в шляпной коробке', '25-roz-gortenzii-korobka', 9500, NULL, '25 роз и гортензии в круглой шляпной коробке с атласной лентой — крупный подарок без вазы и упаковки. Диаметр — около 35 см. Повод: юбилей, годовщина, важное поздравление. Свежесть 7–10 дней: доливайте воду в коробку каждые 1–2 дня.', 'gen16.jpg', 0, 1, 0, 190),
        (4, 'Букет на годовщину «Красное и белое»', 'buket-godovshina-krasnoe-beloe', 4500, NULL, 'Красные и белые розы с гипсофилой — классика юбилея отношений. Размер — около 45–50 см, упаковка — крафт или фетр. Повод: годовщина свадьбы и знакомства, круглая дата. Стоит в вазе 7–10 дней при ежедневной смене воды и подрезке стеблей.', 'gen17.jpg', 1, 0, 0, 200),
        (1, '25 красных роз в чёрно-золотой обёртке', '25-roz-cherno-zoloto', 6900, NULL, '25 красных роз в премиальной обёртке «матовый чёрный + золото» — страстный жест. Размер — около 50 см. Повод: признание в любви, годовщина, крупный романтический повод. Стоит в вазе 7–10 дней: подрежьте стебли под углом и держите в прохладе.', 'gen18.jpg', 0, 1, 0, 210),
        (5, 'Орхидеи и розы в квадратной коробке', 'orhidei-rozy-kvadrat-korobka', 7900, NULL, 'Белые орхидеи с розами в квадратной коробке — статусная композиция для делового подарка. Сторона коробки — около 30 см. Повод: поздравление партнёра, открытие офиса, юбилей руководителя. Свежесть 10–14 дней: орхидеи стойкие, доливайте воду во флористическую губку.', 'gen19.jpg', 0, 1, 0, 220),
        (6, 'Клубника и макаруны в розовой коробке', 'klubnika-makarony-korobka', 1490, NULL, 'Клубника в шоколаде и макаруны в розовой коробке — сладкое дополнение к букету. Порция на двоих. Повод: свидание, день рождения, сюрприз ребёнку. Храните в холодильнике не более суток: макаруны чувствительны к теплу, ягоды — ко времени.', 'gen20.jpg', 0, 0, 1, 230)");

    $pdo->exec("INSERT INTO settings (key, value) VALUES
        ('shop_name', 'Nilov Flowers'),
        ('shop_phone', '+7 (900) 000-00-00'),
        ('shop_address', 'г. Санкт-Петербург, Полевая Сабировская ул., 47, корп. 1'),
        ('hero_title', 'Доставка цветов по Санкт-Петербургу'),
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
        /* W96-fix3: поводы «Годовщина» и «Для любимой» — конверсионная дыра
           (критик-покупатель: anniversary/для жены — частейший запрос, страницы не было) */
        $ins->execute([':sl'=>'buket-na-godovshinu', ':t'=>'Букет на годовщину в Санкт-Петербурге',
          ':md'=>'Букет на годовщину отношений и свадьбы: красные и белые розы, романтические композиции. Доставка в день заказа, открытка от руки бесплатно.',
          ':i'=>'Годовщина — повод, который требует особенного букета: символика цветов важнее количества. Красные розы говорят о страсти, белые — о чистоте чувств, а смешанные красно-белые букеты — классика юбилеев отношений.',
          ':b'=>'Как выбрать букет на годовщину: ориентируйтесь на количество лет — 1 год дарят одну эффектную охапку, на «круглые» даты уместны крупные композиции из 25+ роз или пионы. Для серебряной и золотой свадьбы подойдут благородные белые и пастельные тона. Открытку с тёплыми словами напишем от руки и вложим в букет бесплатно. Если годовщина сегодня — оформите заказ до 20:00, доставим в тот же день.',
          ':q1'=>'Доставите букет на годовщину сегодня?', ':a1'=>'Да, при оформлении до 20:00 доставим в тот же день по Санкт-Петербургу. В комментарии укажите удобное время — курьер привезёт букет к нужному часу.',
          ':q2'=>'Можно добавить к букету подарок?', ':a2'=>'Да, в разделе «Дополните букет» есть клубника в шоколаде и сладкие наборы — привезём вместе с цветами.',
          ':p'=>'20,5,21', ':so'=>4]);
        $ins->execute([':sl'=>'buket-dlya-lyubimoj', ':t'=>'Букет для любимой в Санкт-Петербурге',
          ':md'=>'Романтические букеты для любимой: пионы, розы, нежные пастельные композиции с доставкой в день заказа и открыткой от руки.',
          ':i'=>'Сказать «люблю» без слов проще всего цветами: пышные пионы, пионовидные розы и пастельные миксы — беспроигрышный язык чувств.',
          ':b'=>'Какой букет подарить любимой без повода: нежные розовые пионы или пионовидные розы — самый «влюблённый» выбор; красные розы — классика признания; пастельный микс с эвкалиптом — если хотите удивить. Не уверены в её вкусах — закажите авторский букет: соберём в актуальной гамме сезона. К каждому букету — открытка с вашим текстом от руки, бесплатно.',
          ':q1'=>'Можно заказать букет сюрпризом?', ':a1'=>'Да: укажите в комментарии время и адрес получательницы, а свой телефон — для подтверждения. Курьер позвонит ей, а не вам — сюрприз не раскроется.',
          ':q2'=>'Что подарить, если она не любит розы?', ':a2'=>'Пионы, гортензии или авторский сезонный микс — соберём букет под её вкус, покажем фото перед отправкой.',
          ':p'=>'12,6,20', ':so'=>5]);
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
        ('faq_a1', 'Зависит от района: 300–400 ₽ по СПб, самовывоз — бесплатно. Точная сумма сразу видна при оформлении заказа.'),
        ('faq_q2', 'Успею ли заказать сегодня?'),
        ('faq_a2', 'Да: оформите заказ до 20:00 — соберём букет и привезём в тот же день, точное время согласуем по телефону. Срочные заказы собираем за 1–2 часа.'),
        ('faq_q3', 'Как понять, что пришёл именно мой букет?'),
        ('faq_a3', 'Перед доставкой курьер фотографирует готовый букет и присылает фото вам — вы видите то же, что получит адресат. Если что-то не так, заменим композицию до вручения.'),
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
        ['avtorskij-rozovoe-oblako', 'Монобукет в розовой гамме — собран нашим флористом утром.', 'Авторский монобукет в розовой гамме: сезонные цветы одного тона, собранные флористом утром. Размер — около 45 см, упаковка — крафт с лентой в тон. Повод: день рождения, рождение дочки, романтический повод без даты. Свежесть 7–10 дней при ежедневной смене воды.'],
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
}
