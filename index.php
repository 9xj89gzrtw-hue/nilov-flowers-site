<?php
/* Главная витрина (редизайн 5cv, W96 → W103): город-бар, hero-бенто (Playfair +
   акцент-слово), marquee-лента, чипы цен, карусели (хиты + максимум 2 категорийные),
   manifesto-полоса, премиум-разворот (dark 7/5), каталог+вкладки, поводы (duotone +
   типографика), форма заказа, магазины, SEO-текст, FAQ, журнал, тэглайн футера.
   Ритм W103: не более двух одинаковых каруселей подряд — между форматами вставки. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$pdo = db();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort, id')->fetchAll();
$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.sort, p.id')->fetchAll();
$zones = $pdo->query('SELECT id, name, price, time FROM delivery_zones ORDER BY sort, id')->fetchAll();

/* Внешний вид: hero-текст можно отключить тумблером */
$heroTextEnabled = setting('hero_text_enabled', '1') === '1';
/* W106 (B2, копирайтер P0): H1 «Доставка цветов по Санкт-Петербургу» — поисковый
   запрос, продавал логистику, а не эмоцию. Новый дефолт — «Сказать без слов»;
   SEO-фраза сохранена в <title>, подзаголовке и seo_text. Приоритет: seo_h1
   (владелец) → hero_title, ЕСЛИ он кастомный → дефолт. Легаси-сид hero_title
   («Доставка…») считается дефолтом — guard по ТОЧНОМУ старому значению (паттерн
   migrateSchema db.php; сам db.php не трогаем), значения из БД живут. */
$heroTitleDb = trim((string)setting('hero_title', ''));
$heroH1 = trim((string)setting('seo_h1', ''));
if ($heroH1 === '') {
    $heroH1 = ($heroTitleDb !== '' && $heroTitleDb !== 'Доставка цветов по Санкт-Петербургу')
        ? $heroTitleDb
        : 'Сказать без слов';
}
/* W106 (B2): подзаголовок — конкретное обещание + SEO-фраза; легаси-сид — дефолт */
$heroSubtitleDb = trim((string)setting('hero_subtitle', ''));
$heroSubtitle = ($heroSubtitleDb !== '' && $heroSubtitleDb !== 'К празднику или просто так — повод не обязателен' && $heroSubtitleDb !== 'Соберём букет утром, сфотографируем до отправки и привезём сегодня. Заказ до 20:00 — доставка по Санкт-Петербургу за 1–2 часа.')
    ? $heroSubtitleDb
    : 'Соберём букет за 1–2 часа, сфотографируем до отправки и привезём сегодня — доставка по Санкт-Петербургу.'; /* W106-E1 (корректор P0): единая SLA-формула «Соберём за 1–2 часа и привезём сегодня» — было «доставка за 1–2 часа» (обещание тем, что сборка+доставка суммарно дольше); легаси-сид в БД гейтится как дефолт */
$heroBtnText = trim(setting('hero_button_text', 'Выбрать букет'));
/* W105-8fix1 (критик-UX 8-b P1#3): дефолт якоря — #fcChips (ряд чипов цен,
   ~781px): золотой путь сначала видит быстрые фильтры, а не прыгает сразу
   к #catalog (~6258px). Стойкое значение в БД (сид '#catalog') переведено
   guard-миграцией в includes/db.php; правленое владельцем не трогаем. */
$heroBtnLink = safe_url(trim(setting('hero_button_link', '#fcChips')));
$heroBtn = $heroTextEnabled && $heroBtnText !== '' && $heroBtnLink !== '';

/* Функции витрины (критерий 16): каждый блок отключаем из админки.
   S4: дефолты фильтров-дублей выключены — ценовые ЧИПСЫ над сеткой уже
   фильтруют по цене (low/mid/high), зонный чек дублирует выбор района в
   чекауте и меню города. Тумблеры в админке остаются рабочими. */
$featFaq = setting('feature_faq', '1') === '1';
$featCountdown = setting('feature_countdown', '1') === '1';
$featPriceFilter = setting('feature_price_filter', '0') === '1';
$featFavorites = setting('feature_favorites', '1') === '1';
$featZoneCheck = setting('feature_zone_check', '0') === '1';
$featDeliveryBadge = setting('feature_delivery_badge', '0') === '1';
/* Критик functional 6/10: gift-UX (получатель+открытка) и слоты даты — отключаемые (критерий 14) */
$featGiftFields = setting('feature_gift_fields', '1') === '1';
$featDeliverySlots = setting('feature_delivery_slots', '0') === '1';

/* Блоки 5cv: hero-промо/доставка, чипы, карусели, поводы и т.д.
   Новые ключи читаем с дефолтами — до миграции БД вернётся дефолт.
   W106-C1 (дизайн-дир P0-2): гео-топбар feature_citybar УПРАЗДНЁН — город
   тихо живёт в шапке/футере (кнопка + компактный dropdown, js/five.js);
   вопрос «ваш город?» при первом визите больше не задаётся. */
$featChips = setting('feature_chips', '1') === '1';
$featCarousels = setting('feature_carousels', '1') === '1';
$featSectionHits = setting('feature_section_hits', '1') === '1';
$featSectionPremium = setting('feature_section_premium', '1') === '1';
$featSectionBudget = setting('feature_section_budget', '1') === '1';
$featSectionAddons = setting('feature_section_addons', '1') === '1';
$featOccasions = setting('feature_occasions', '1') === '1';
$featSeotext = setting('feature_seotext', '1') === '1';
/* Журнал по умолчанию ВЫКЛ — контента ещё нет */
$featJournal = setting('feature_journal', '0') === '1';
/* W106-C1: hero_promo_enabled больше не рендерит карточку в hero (P0-4) —
   тумблер остаётся в админке/БД, витрина его не читает. */
$heroDeliveryOn = setting('hero_delivery_card_enabled', '1') === '1';
/* W106-8fix1 (8-b P1#3): кнопка hero-промо «Выбрать букет» — тот же якорь
   чипов #fcChips (дефолт и guard-миграция как у главного CTA); чипы цен
   выключены — оба возвращаются к прежнему #catalog, якорь всегда жив.
   W106-C1 (дизайн-дир P0-4): промо-карточка «Открытка в подарок» УБРАНА
   из hero (первый экран продавал витрину распродаж) — её содержание
   тихой строкой живёт в секции «Дополните букет» (см. $addonsSub);
   $heroPromoLink нужен только фолбэку чипов ниже. */
$heroPromoLink = safe_url(trim(setting('hero_promo_link', '#fcChips')));
if (!$featChips) {
    if ($heroBtnLink === '#fcChips') $heroBtnLink = '#catalog';
    if ($heroPromoLink === '#fcChips') $heroPromoLink = '#catalog';
}

/* W103 (F1) → W106 (B2, копирайтер P0): марquee без КАПСа и негатива
   («заменяем увядшие» — крик о проблеме). Регистр — как в предложении;
   текст-transform в five.css снят. Ключи те же marquee_1..4 (в БД пусто —
   рендерятся эти дефолты; правка владельца из админки по-прежнему в силе). */
$featMarquee = setting('feature_marquee', '1') === '1';
$marqueeDefaults = [
    1 => 'Свежий срез каждое утро',
    2 => 'Фото букета до отправки',
    3 => 'Соберём за 1–2 часа',
    4 => 'Оплата при получении',
];
$marqueeItems = [];
for ($mi = 1; $mi <= 4; $mi++) {
    $mv = trim(setting('marquee_' . $mi, $marqueeDefaults[$mi]));
    if ($mv !== '') {
        $marqueeItems[] = $mv;
    }
}
/* W103 (F1): ghost-CTA в hero — тихая текстовая ссылка со стрелкой;
   не конкурирует с розовой пилюлей (критик-1: «три конкурирующих CTA»). */
$heroGhostText = trim(setting('hero_ghost_text', 'Цветы по поводам'));
$heroGhostLink = safe_url(trim(setting('hero_ghost_link', '#occasions')));
$heroGhost = $heroTextEnabled && $heroGhostText !== '' && $heroGhostLink !== '';

/* W96-fix1 (F8): траст-ряд под hero — те же гарантии, что и на странице товара
   (дефолты — «заводские» из settings-history.php). Гейт — hero_text_enabled. */
$guaranteeDefaults = ['Фото букета до отправки', 'Каждый букет — из свежего среза', 'Заменяем увядшие цветы в день доставки']; /* W104-λ (C4-T4): «с утренней поставки» ×17 — вариация вместо дубли */
$guarantees = [];
for ($i = 1; $i <= 3; $i++) {
    $guaranteeVal = trim(setting('guarantee_' . $i, $guaranteeDefaults[$i - 1]));
    if ($guaranteeVal !== '') {
        $guarantees[] = $guaranteeVal;
    }
}

/* Пороги чипов/секций цен: N — низ, M — верх */
$chipsN = (int) setting('chips_price_low', '3500');
$chipsM = (int) setting('chips_price_high', '7000');

/* W106 (B2, задача 9): рейтинг для hero-зоны доверия — живой агрегат отзывов
   из БД (хелперы волны B1 в includes/db.php). Приоритет — «Яндекс Карты»
   (источник в reviews.source); яндекс-отзывов нет — общий агрегат
   getRatingAggregate(). 0 отзывов — $heroRating = null (бейдж не рендерим). */
$heroRating = null;
try {
    $yrRow = $pdo->query("SELECT COUNT(*) AS c, ROUND(AVG(rating), 1) AS a FROM reviews WHERE source = 'Яндекс Карты'")->fetch();
    if ((int)($yrRow['c'] ?? 0) > 0) {
        $heroRating = ['avg' => (float)$yrRow['a'], 'count' => (int)$yrRow['c'], 'yandex' => true];
    }
} catch (Throwable $e) {
    /* старая БД без таблицы reviews — тихо деградируем к общему агрегату */
}
if ($heroRating === null) {
    $aggRow = getRatingAggregate();
    if ($aggRow['count'] > 0) {
        $heroRating = ['avg' => $aggRow['avg'], 'count' => $aggRow['count'], 'yandex' => false];
    }
}

function product_img_url(array $p): string
{
    return $p['image'] !== '' ? static_img_v('/img/products/' . rawurlencode($p['image'])) : '';
}

/* WebP-вариант того же фото (если сгенерирован рядом: name.jpg → name.webp).
   Идемпотентно: файла нет — вернём пустую строку и <source> не напечатаем. */
function product_img_webp(array $p): string
{
    if ($p['image'] === '') return '';
    $webp = '/img/products/' . rawurlencode(preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', $p['image']));
    return is_file(BASE_PATH . urldecode($webp)) ? static_img_v($webp) : '';
}

/* W96-fix3a (T2): превью карточек каталога — webp шириной 400px в img/products/thumbs/
   ({имя без расширения}-400.webp). Генерация ленивая: файла нет — GD-ресайз на месте
   (q78); оригинал уже ≤400px / GD не справился / каталог не писуется — вернём ''
   и карточка живёт без превью (как до фикса). Запись через tmp+rename: параллельные
   запросы не прочитают половину файла; статик-кэш — одна попытка за запрос. */
function product_img_thumb(array $p): string
{
    static $cache = [];
    if (($p['image'] ?? '') === '') return '';
    $file = (string)$p['image'];
    if (isset($cache[$file])) return $cache[$file];

    $fail = static function () use (&$cache, $file): string {
        $cache[$file] = '';
        return '';
    };
    $src = IMG_PRODUCTS_DIR . '/' . $file;
    if (!is_file($src)) return $fail();
    $dim = @getimagesize($src);
    if ($dim === false) return $fail();
    [$srcW, $srcH, $type] = [(int)$dim[0], (int)$dim[1], (int)$dim[2]];
    /* Компактный оригинал — превью не даёт экономии, не апскейлим */
    if ($srcW <= 400) return $fail();

    $base = preg_replace('/\.[^.]+$/', '', $file) ?? $file;
    $thumbsDir = IMG_PRODUCTS_DIR . '/thumbs';
    $dst = $thumbsDir . '/' . $base . '-400.webp';
    $url = '/img/products/thumbs/' . rawurlencode($base . '-400.webp');
    if (is_file($dst)) return static_img_v($url);

    if (!is_dir($thumbsDir) && !@mkdir($thumbsDir, 0755, true)) return $fail();
    $srcIm = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => false, /* gif без анимации терять не хотим, прочее не поддерживаем */
    };
    if ($srcIm === false) return $fail();

    $w = 400;
    $h = max(1, (int)round($srcH * $w / $srcW));
    $dstIm = imagecreatetruecolor($w, $h);
    if ($type === IMAGETYPE_PNG) {
        /* PNG может нести альфу — сохраняем прозрачность (webp её умеет) */
        imagealphablending($dstIm, false);
        imagesavealpha($dstIm, true);
        imagefill($dstIm, 0, 0, imagecolorallocatealpha($dstIm, 0, 0, 0, 127));
    }
    $copied = imagecopyresampled($dstIm, $srcIm, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
    imagedestroy($srcIm);
    if (!$copied) {
        imagedestroy($dstIm);
        return $fail();
    }
    $tmp = $dst . '.tmp' . getmypid();
    $written = @imagewebp($dstIm, $tmp, 78);
    imagedestroy($dstIm);
    if (!$written || !@rename($tmp, $dst)) {
        if (is_file($tmp)) @unlink($tmp);
        return $fail();
    }
    return static_img_v($url);
}

/* W96-fix3a (T2): фактическая ширина оригинала в px — для честного {w}-дескриптора
   srcset (оригиналы разные: 582–900px, «864w» для всех был бы ложью). */
function product_img_width(array $p): int
{
    static $cache = [];
    if (($p['image'] ?? '') === '') return 0;
    $file = (string)$p['image'];
    if (!isset($cache[$file])) {
        $dim = @getimagesize(IMG_PRODUCTS_DIR . '/' . $file);
        $cache[$file] = $dim === false ? 0 : (int)$dim[0];
    }
    return $cache[$file];
}

/* W103 (F1): <picture> для крупных карточек премиум-разворота — тот же webp-конвейер
   (превью 400/600w + оригинал {w}w), sizes под слот разворота. Отдельно от
   render_product_card: разметка разворота другая (крупная карточка-ссылка). */
function render_premium_picture(array $p, string $sizes): void
{
    $img = product_img_url($p);
    $imgWebp = product_img_webp($p);
    $thumb = product_img_thumb($p);
    $t600 = $thumb !== '' ? (string)preg_replace('/-400(\.webp)(?=\?|$)/', '-600$1', $thumb) : '';
    $t600ok = $t600 !== '' && $t600 !== $thumb && is_file(BASE_PATH . parse_url($t600, PHP_URL_PATH));
    $srcset = [];
    if ($thumb !== '') {
        $srcset[] = $thumb . ' 400w';
    }
    if ($t600ok) {
        $srcset[] = $t600 . ' 600w';
    }
    $origW = product_img_width($p);
    if ($imgWebp !== '' && $origW > 0) {
        $srcset[] = $imgWebp . ' ' . $origW . 'w';
    }
    ?>
    <picture>
      <?php if ($srcset !== []): ?><source type="image/webp" srcset="<?= e(implode(', ', $srcset)) ?>" sizes="<?= e($sizes) ?>"><?php endif; ?>
      <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
    </picture>
    <?php
}

/* W103 (6-b): setting-значение картинки — имя файла из img/uploads/ (админ-аплоад)
   ИЛИ путь от корня сайта (сид-дефолты img/editorial/…, img/products/… — файлы
   трекаются в git). Возвращают путь от корня ('' — файла нет). */
function site_image_root(string $val): string
{
    $val = trim(str_replace('\\', '/', $val));
    if ($val === '') return '';
    if (is_file(IMG_UPLOADS_DIR . '/' . $val)) return 'img/uploads/' . $val;
    if (is_file(BASE_PATH . '/' . $val)) return $val;
    return '';
}

function site_image_url(string $val): string
{
    $root = site_image_root($val);
    if ($root === '') return '';
    return '/' . implode('/', array_map('rawurlencode', explode('/', $root)));
}

/* W99-fixG (G3): hero-превью — webp шириной 480/768 в img/uploads/thumbs/
   ({имя без ext}-{W}.webp, q78). Паттерн product_img_thumb: ленивая генерация,
   tmp+rename против гонок, оригинал ≤W / сбой GD / не пишется каталог → ''
   (деградация до прежнего одного src). Оригинал 1024×1024 (90КБ webp) мобиле
   не нужен: 390/DPR1 хватает 480w.
   W103 (6-b): $file — теперь ПУТЬ ОТ КОРНЯ сайта (site_image_root): аплоады
   дают то же имя превью, что раньше; прочие пути (img/editorial/…) — «сплющенное»
   имя (img-editorial-florist-hands-480.webp), чтобы весь генерируемый кэш жил
   в gitignored img/uploads/thumbs/. */
function hero_img_size(string $file, int $targetW): string
{
    static $cache = [];
    if ($file === '' || $targetW <= 0) return '';
    $ck = $file . '@' . $targetW;
    if (isset($cache[$ck])) return $cache[$ck];

    $fail = static function () use (&$cache, $ck): string {
        $cache[$ck] = '';
        return '';
    };
    $src = BASE_PATH . '/' . $file;
    if (!is_file($src)) return $fail();
    $dim = @getimagesize($src);
    if ($dim === false) return $fail();
    [$srcW, $srcH, $type] = [(int)$dim[0], (int)$dim[1], (int)$dim[2]];
    /* Компактный оригинал — превью не даёт экономии, не апскейлим */
    if ($srcW <= $targetW) return $fail();

    /* W103 (6-b): база имени — как раньше для аплоадов ({name}-{W}.webp),
       «сплющенный» путь для сид-файлов вне img/uploads (кэш — в одном каталоге) */
    $isUploads = str_starts_with($file, 'img/uploads/');
    $base = $isUploads
        ? (preg_replace('/\.[^.]+$/', '', substr($file, 12)) ?? substr($file, 12))
        : str_replace('/', '-', (string)(preg_replace('/\.[^.]+$/', '', $file) ?? $file));
    $thumbsDir = IMG_UPLOADS_DIR . '/thumbs';
    $dst = $thumbsDir . '/' . $base . '-' . $targetW . '.webp';
    $url = '/img/uploads/thumbs/' . rawurlencode($base . '-' . $targetW . '.webp');
    if (is_file($dst)) return static_img_v($url);

    if (!is_dir($thumbsDir) && !@mkdir($thumbsDir, 0755, true)) return $fail();
    $srcIm = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => false,
    };
    if ($srcIm === false) return $fail();

    $w = $targetW;
    $h = max(1, (int)round($srcH * $w / $srcW));
    $dstIm = imagecreatetruecolor($w, $h);
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($dstIm, false);
        imagesavealpha($dstIm, true);
        imagefill($dstIm, 0, 0, imagecolorallocatealpha($dstIm, 0, 0, 0, 127));
    }
    $copied = imagecopyresampled($dstIm, $srcIm, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
    imagedestroy($srcIm);
    if (!$copied) {
        imagedestroy($dstIm);
        return $fail();
    }
    $tmp = $dst . '.tmp' . getmypid();
    $written = @imagewebp($dstIm, $tmp, 78);
    imagedestroy($dstIm);
    if (!$written || !@rename($tmp, $dst)) {
        if (is_file($tmp)) @unlink($tmp);
        return $fail();
    }
    return static_img_v($url);
}

/* Критик-покупатель B1: единый фолбэк-фото (productImageFile) — карточка и страница
   товара всегда показывают одно и то же. */
$canonicalUrl = 'https://flowers.interfood-catering.ru/';
foreach ($products as &$pRow) {
    if ($pRow['is_active'] == 1) {
        $pRow['image'] = productImageFile($pRow);
    }
}
unset($pRow);

/* Единая карточка товара (5cv): используется и в каруселях, и в каталоге.
   $ctx — фичи-тумблеры витрины (badge доставки, избранное). is_hit/is_premium
   может не быть в старой БД — читаем через ?? 0.
   W99-fixG (G2): $ctx['carousel'] — копия для карусели секции: фильтрационные
   атрибуты (data-search ~1.5КБ/карточка, data-price/data-hit/data-premium/
   data-category-id) НЕ печатаем — проверено: js/catalog-filter.js и js/five.js
   матчат карточки ТОЛЬКО внутри #catalogGrid (grid.querySelectorAll), вне
   каталога data-search не читается. Остаются данные для рендера, CTA
   (data-order-cta → cart-cta.js) и сердечка (data-fav-id → nilov.js, который
   сам ставит data-fav на ближайший .product-card). */
function render_product_card(array $p, array $ctx): void
{
    $price = productPrice($p);
    $isSale = $price !== (int)($p['price'] ?? 0);
    $isHit = (int)($p['is_hit'] ?? 0) === 1;
    $isPremium = (int)($p['is_premium'] ?? 0) === 1;
    $img = product_img_url($p);
    $imgWebp = product_img_webp($p);
    $thumb = product_img_thumb($p);
    $origW = product_img_width($p);
    if ($thumb !== '' && $imgWebp !== '' && $origW > 0) {
        $t600 = preg_replace('/-400(\.webp)(?=\?|$)/', '-600$1', $thumb);
        $srcset = $thumb . ' 400w'
            . ($t600 !== null && $t600 !== $thumb && is_file(BASE_PATH . parse_url($t600, PHP_URL_PATH)) ? ', ' . $t600 . ' 600w' : '')
            . ', ' . $imgWebp . ' ' . $origW . 'w';
    } elseif ($thumb !== '') {
        $srcset = $thumb . ' 400w';
    } elseif ($imgWebp !== '') {
        $srcset = $imgWebp;
    } else {
        $srcset = '';
    }
    $isCarousel = !empty($ctx['carousel']);
    /* S4: сетка каталога 4/3/2 — sizes по фактическим слотам карточки */
    $sizes = $isCarousel
        ? '(max-width:899px) 72vw, 280px'
        : '(max-width:359px) 92vw, (max-width:767px) 46vw, (max-width:1023px) 31vw, (max-width:1279px) 23vw, 288px';
    $link = '/product/' . rawurlencode($p['slug']);
    $searchIndex = $isCarousel ? '' : mb_strtolower(trim($p['name'] . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['description'] ?? '')));
    $upsellAttr = (!$isCarousel && (int)($p['show_in_upsell'] ?? 0) === 1) ? ' data-upsell="1"' : '';
    $tagsAttr = trim((string)($p['tags'] ?? '')) !== ''
        ? ' data-tags="' . e(mb_strtolower(str_replace('ё', 'е', preg_replace('/\s+/u', ' ', trim((string)$p['tags'])) ?? ''), 'UTF-8')) . '"'
        : '';
    $img2 = trim((string)($p['image2'] ?? ''));
    $img2Url = '';
    if ($img2 !== '' && is_file(IMG_PRODUCTS_DIR . '/' . $img2)) {
        $img2Url = '/img/products/' . rawurlencode($img2);
    }
    $comp = trim((string)($p['composition'] ?? ''));
    $sizeText = trim((string)($p['size_text'] ?? ''));
    /* S5: микро-бейдж размера — ⌀ (диаметр) если size_text начинается с ⌀,
       иначе ↕ (высота). Текст без дублирования символа. */
    $sizeIsDia = mb_strpos($sizeText, '⌀') === 0;
    $sizeClean = trim((string)preg_replace('/^[⌀↕]\s*/u', '', $sizeText));
    /* S5: сплит-виджет-пилюля «[Сплит] 4 платежа по 875 ₽» (splitPaymentText —
       util.php; настройки card_split_format + split_chip_text) */
    $splitText = splitPaymentText($price);
    $splitChip = trim((string)setting('split_chip_text', 'Сплит'));
    /* S4: компактная строка доставки под ценой («Сегодня за 1–2 часа»,
       настройка card_delivery_text; пусто — не печатаем). */
    $deliveryText = trim((string)setting('card_delivery_text', 'Сегодня за 1–2 часа'));
    /* S4: теги «стойкие»/«свежая поставка» — тихие пилюли в теле карточки
       (на фото остаются только «−N%» и «Хит»). */
    $isSturdy = false; $isFresh = false;
    foreach (productTagsList($p) as $tl) {
        if (str_starts_with($tl, 'стой')) { $isSturdy = true; }
        if (str_starts_with($tl, 'свеж')) { $isFresh = true; }
    }
    /* S4: короткая подпись кнопки 1-клика («Купить в 1 клик» → «1 клик»);
       полный текст настройки — в aria-label/title. */
    $oneclickFull = trim((string)setting('card_btn_oneclick', 'Купить в 1 клик'));
    $oneclickShort = trim((string)preg_replace('/^купить\s+(в\s+)?/iu', '', $oneclickFull));
    if ($oneclickShort === '') { $oneclickShort = $oneclickFull; }
    ?>
        <article class="product-card reveal"<?= $isCarousel
            ? ''
            : ' data-category-id="' . (int)($p['category_id'] ?? 0) . '" data-price="' . (int)$price . '" data-hit="' . (int)($p['is_hit'] ?? 0) . '" data-premium="' . (int)($p['is_premium'] ?? 0) . '" data-search="' . e($searchIndex) . '"' . $upsellAttr . $tagsAttr ?>>
          <div class="product-card__media">
          <?php /* фото — 80% карточки: строго 3:4, радиус 12-16, без фона */ ?>
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>" aria-hidden="true" tabindex="-1">
              <?php if ($img !== ''): ?>
                <picture>
                  <?php if ($srcset !== ''): ?><source type="image/webp" srcset="<?= e($srcset) ?>"<?= $thumb !== '' ? ' sizes="' . e($sizes) . '"' : '' ?>><?php endif; ?>
                  <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
                </picture>
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
              <?php if ($img2Url !== ''): ?>
                <img class="product-card__img2" src="<?= e($img2Url) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
              <?php endif; ?>
            </a>
            <?php /* бейджи на фото: скидка «−N%» и «Хит» (#F5B301) слева сверху;
                   «Премиум» — редкий третий, тоже слева (лесенкой). */ ?>
            <?php if ($isSale): $offPct = (int)$p['price'] > 0 ? (int)round((1 - $price / (int)$p['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= $offPct > 0 ? '&#8722;' . (int)$offPct . '%' : e(setting('badge_sale_text', 'Скидка')) ?></span><?php endif; ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <?php if ($ctx['featFavorites']): ?><button type="button" class="product-card__fav" data-fav-id="<?= (int)$p['id'] ?>" data-fav-name="<?= e($p['name']) ?>" aria-label="В избранное: <?= e($p['name']) ?>" title="В избранное">♡</button><?php endif; ?>
          </div>
          <div class="product-card__body">
            <?php if (!empty($ctx['feature'])): ?>
            <span class="product-card__feature-label"><?= e(setting('feature_card_label', 'Выбор флориста')) ?></span>
            <?php endif; ?>
            <?php /* мета-ряд: время доставки + микро-бейдж размера (⌀/↕) —
                   того, чего нет на 5cv.ru */ ?>
            <div class="product-card__meta">
              <?php if ($deliveryText !== ''): ?><p class="product-card__delivery"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= e($deliveryText) ?></p><?php endif; ?>
              <?php if ($sizeClean !== ''): ?>
              <p class="product-card__size"><?php if ($sizeIsDia): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M6.5 17.5 17.5 6.5"/></svg><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/></svg><?php endif; ?><span class="product-card__size-text"><?= e($sizeClean) ?></span></p>
              <?php endif; ?>
            </div>
            <p class="product-card__price">
              <?php if ($isSale): ?>
                <span class="product-card__price--discount"><?= formatPrice($price) ?></span>
                <span class="product-card__price--old"><?= formatPrice((int)$p['price']) ?></span>
              <?php else: ?>
                <?= formatPrice($price) ?>
              <?php endif; ?>
            </p>
            <?php /* Сплит — виджет-пилюля: графитовый чип + «4 платежа по 875 ₽» */ ?>
            <?php if ($splitText !== ''): ?>
            <p class="product-card__split"><span class="product-card__split-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="7.5" height="14" rx="1.5"/><rect x="13.5" y="5" width="7.5" height="14" rx="1.5"/></svg><?= e($splitChip !== '' ? $splitChip : 'Сплит') ?></span><span class="product-card__split-text"><?= e($splitText) ?></span></p>
            <?php endif; ?>
            <a class="product-card__name" href="<?= e($link) ?>"><?= e($p['name']) ?></a>
            <?php if ($comp !== ''): ?><p class="product-card__comp"><?= e($comp) ?></p><?php endif; ?>
            <?php if ($isSturdy || $isFresh): ?>
            <div class="product-card__tags">
              <?php if ($isSturdy): ?><span class="product-card__tag"><?= e(setting('badge_sturdy_text', 'Стойкие')) ?></span><?php endif; ?>
              <?php if ($isFresh): ?><span class="product-card__tag"><?= e(setting('badge_fresh_text', 'Свежая поставка')) ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ((int)($p['is_urgent'] ?? 0) === 1): ?>
            <p class="product-card__urgent-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>Соберём за 1–2 часа и привезём сегодня — количество ограничено</p>
            <?php endif; ?>
            <?php /* кнопки: компактная «В корзину» + иконка «1 клик» */ ?>
            <div class="product-card__actions">
              <button type="button" class="product-card__cta" data-order-cta
                data-product-id="<?= (int)$p['id'] ?>"
                data-product-name="<?= e($p['name']) ?>"
                data-product-price-raw="<?= $price ?>"
                data-product-image="<?= e($img) ?>"
                aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="Добавить в корзину"><?= e(setting('card_btn_cart', 'В корзину')) ?></button>
              <button type="button" class="product-card__oneclick" data-oneclick
                data-product-id="<?= (int)$p['id'] ?>"
                data-product-name="<?= e($p['name']) ?>"
                data-product-price-raw="<?= $price ?>"
                data-product-image="<?= e($img) ?>"
                aria-label="<?= e($oneclickFull) ?>: <?= e($p['name']) ?>" title="<?= e($oneclickFull) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg><span><?= e(mb_strimwidth($oneclickShort, 0, 12, '…')) ?></span></button>
            </div>
          </div>
        </article>
    <?php
}


function render_fc_eyebrow(string $eyebrow): void
{
    if ($eyebrow === '') return;
    [$ebCaps, $ebItalic, $ebTail] = array_pad(explode('|', $eyebrow, 3), 3, '');
    ?>
    <p class="fc-row__eyebrow"><?= e($ebCaps) ?><?= $ebItalic !== '' ? ' <em>' . e($ebItalic) . '</em>' : '' ?><?= $ebTail !== '' ? ' <span class="fc-row__eyebrow-tail">' . e($ebTail) . '</span>' : '' ?></p>
    <?php
}

/* Секция-карусель 5cv: заголовок + подпись + «Смотреть все» + стрелки + лента карточек.
   W96-fix1 (F7): $chip — «Смотреть все» у Хитов/Премиума/До N применяет чип каталога
   (js/five.js по data-chip), у категорийных секций — вкладку (data-tab, как раньше).
   W97-fixB3b (B3b-1f): $catSlug — у категорийных каруселей «Смотреть все» ведёт на
   посадочную /category/{slug} (SEO: у категории появился собственный URL),
   data-tab больше не печатается. */
function render_fc_row(string $title, string $sub, array $items, array $ctx, string $tabId = '', string $chip = '', string $catSlug = '', string $eyebrow = '', string $numeral = '', string $variant = '', string $bg = ''): void
{
    global $fcProdSeq;
    if ($items === []) return;
    $fcProdSeq++;
    /* W99-fixG (G2): копии карточек для карусели — без фильтрационных атрибутов
       (render_product_card по ctx['carousel']) */
    $ctx['carousel'] = true;
    /* W97-fixB2 (B2-5а): чередование фонов товарных секций — каждая вторая tint
       (стили придёт волной CSS; здесь только классы).
       W106-C1 (P0-5 «фон-ритм»): явный $bg («soft» — тонкий tint) бьёт
       автоматику — ритм фонов теперь назначается по драматургии секции. */
    $tint = ($bg !== '') ? ' fc-section--' . $bg : (($fcProdSeq % 2 === 0) ? ' fc-section--tint' : '');
    /* W104-γ (C2-D2 P1.1): вариация лэйаутов — 'split' (первая карточка —
       широкая editorial-фича) / 'compact' (мини-рельс дополнений);
       стили five.css γ-5, классы нейтральны для js/five.js */
    $rowClass = 'fc-row' . ($variant !== '' ? ' fc-row--' . e($variant) : '');
    $carouselClass = 'fc-carousel' . ($variant === 'compact' ? ' fc-carousel--compact' : '');
    ?>
    <section class="fc-section<?= $tint ?>"><div class="wrap">
      <div class="<?= $rowClass ?>"><div class="fc-row__head">
        <?php /* W103 (F1): обёртка heading — eyebrow над H2 не ломает flex-строку
               шапки секции (заголовок+подпись группируются в один блок).
               W104: data-numeral — oversize-индекс секции за заголовком (::before,
               five.css). */ ?>
        <div class="fc-row__heading"<?= $numeral !== '' ? ' data-numeral="' . e($numeral) . '"' : '' ?>>
        <?php render_fc_eyebrow($eyebrow); ?>
        <h2 class="fc-row__title"><?= e($title) ?></h2>
        <?php if ($sub !== ''): ?><p class="fc-row__sub"><?= e($sub) ?></p><?php endif; ?>
        </div>
        <?php /* W99-fixG (G11): «Смотреть все» ×9 одинаковых имён — aria-label с
               заголовком секции (визуальный текст не меняется) */ ?>
        <?php if ($catSlug !== ''): ?>
        <a class="fc-row__link" href="/category/<?= e($catSlug) ?>" aria-label="Смотреть все: <?= e($title) ?>">Смотреть все</a>
        <?php else: ?>
        <a class="fc-row__link" href="#catalog"<?= $tabId !== '' ? ' data-tab="' . e($tabId) . '"' : '' ?><?= $chip !== '' ? ' data-chip="' . e($chip) . '"' : '' ?> aria-label="Смотреть все: <?= e($title) ?>">Смотреть все</a>
        <?php endif; ?>
        <div class="fc-row__arrows">
          <?php /* W97-fixB2 (B2-4): стрелки называют свою секцию — пары «Назад»/«Вперёд»
                 были одинаковыми у всех каруселей; классы/структуру не трогаем (js/five.js вешает по классам) */ ?>
          <button class="fc-row__arrow" type="button" aria-label="<?= e($title) ?>: назад"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
          <button class="fc-row__arrow fc-row__arrow--next" type="button" aria-label="<?= e($title) ?>: вперёд"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
        </div>
      </div>
      <?php /* W97-fixB2 (B2-4): карусель — именованный клавиатурно-доступный region
             (безымянные скролл-области молчали для скринридера) */ ?>
      <div class="<?= $carouselClass ?>" role="region" aria-label="<?= e($title) ?>" tabindex="0">
        <?php /* W104-γ (split): первая карточка ряда — фича (метка + крупные
               стили five.css γ-5); остальное — стандартный рельс */
        foreach ($items as $itemIdx => $item) {
            if ($variant === 'split' && $itemIdx === 0) {
                $ctx['feature'] = true;
                render_product_card($item, $ctx);
                unset($ctx['feature']);
            } else {
                render_product_card($item, $ctx);
            }
        } ?>
      </div></div>
    </div></section>
    <?php
    /* W103 (F1): editorial-вставки вызываю явно из тела страницы (новые позиции
    ритма: №1 — между «До N ₽» и «Дополните», №2 — после каталога) — автозамена
    «после 3-й/6-й секции» удалена: последовательность секций изменилась. */
}

/* W97-fixB2 (B2-5б) → W99-fixG (G17) → W103 (F1): editorial-пауза — PULL-ЦЕТАТА
   (Playfair italic, amber-линия, выравнивание влево). Тексты уникальные — из
   setting('editorial_text_N'); trim()==='' (пустое значение в БД) скрывает блок.
   Врезка рендерится не более одного раза (guard по номеру): №1 и №2 — из тела
   страницы (позиции ритма W103), №3 — перед FAQ. */
function render_fc_editorial(int $n): void
{
    global $fcEditorialDone;
    if (($fcEditorialDone[$n] ?? false)) return;
    $fcEditorialDone[$n] = true;
    $defaults = [
        1 => 'Каждое утро начинается с поставки: цветы не ждут склада — они ждут получателя',
        2 => 'Не нашли нужный букет? Соберём авторский — под ваш повод, палитру и бюджет',
        3 => 'Курьер выезжает после того, как вы одобрили фото готового букета',
    ];
    $editorialText = trim(setting('editorial_text' . ($n === 1 ? '' : '_' . $n), $defaults[$n] ?? ''));
    if ($editorialText === '') return;
    ?>
    <section class="fc-editorial" aria-label="О мастерской"><div class="wrap"><p class="reveal"><?= e($editorialText) ?></p></div></section>
    <?php
}

$cardCtx = ['featDeliveryBadge' => $featDeliveryBadge, 'featFavorites' => $featFavorites];

/* W106 (B2, арт-директор P0-2 «монотонность рядов»): Хиты — EDITORIAL-КОЛЛАЖ
   вместо карусели (2 крупные фича-карточки + 3 компактных мини, grid со
   span'ами — стили five.css .fc-collage). Карточки — тот же компонент
   render_product_card (контракт CTA/сердечка цел), копии без фильтрационных
   атрибутов (паттерн G2). «Смотреть все» — прежний data-chip="hit" (js/five.js
   применяет чип каталога — поведение дублирующего ряда сохранено). */
function render_fc_collage(string $title, string $sub, array $items, array $ctx, string $eyebrow, string $numeral): void
{
    global $fcProdSeq;
    if ($items === []) return;
    $fcProdSeq++;
    $ctx['carousel'] = true; /* копии без data-search/цены — фильтры живут в #catalogGrid */
    $featCount = min(2, count($items));
    $miniItems = array_slice($items, 2, 3);
    ?>
    <section class="fc-section fc-hits"><div class="wrap">
      <div class="fc-row__head">
        <div class="fc-row__heading" data-numeral="<?= e($numeral) ?>">
        <?php render_fc_eyebrow($eyebrow); ?>
        <h2 class="fc-row__title"><?= e($title) ?></h2>
        <?php if ($sub !== ''): ?><p class="fc-row__sub"><?= e($sub) ?></p><?php endif; ?>
        </div>
        <a class="fc-row__link" href="#catalog" data-chip="hit" aria-label="Смотреть все: <?= e($title) ?>">Смотреть все</a>
      </div>
      <div class="fc-collage">
        <?php foreach (array_slice($items, 0, $featCount) as $clIdx => $clItem): ?>
        <?php if ($clIdx === 0) { $ctx['feature'] = true; } /* редакционная метка «Выбор флориста» — только лид */ ?>
        <div class="fc-collage__cell fc-collage__feature<?= $clIdx === 0 ? ' fc-collage__feature--lead' : '' ?>">
          <?php render_product_card($clItem, $ctx); ?>
        </div>
        <?php if ($clIdx === 0) { unset($ctx['feature']); } ?>
        <?php endforeach; ?>
        <?php foreach ($miniItems as $cmItem): ?>
        <div class="fc-collage__cell fc-collage__mini">
          <?php render_product_card($cmItem, $ctx); ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div></section>
    <?php
}

/* W97-fixB2 (B2-5): счётчик товарных секций — общий для каруселей и каталога:
   им чередуем фон (каждая вторая — tint). Топ-левел index.php = global scope,
   render_fc_row() читает его через global. W99-fixG (G17): guard-массив —
   по номеру врезки. W103: премиум-разворот тоже инкрементит счётчик (тело ниже). */
$fcProdSeq = 0;
$fcEditorialDone = [];

/* W104-α (M): единая система оглавления — сквозная нумерация контентных
   секций главной (01 хиты → 02/03 категорийные → 04 премиум → 05 «До N ₽» →
   06 «Дополните» → 07 каталог → 08 поводы → 09 FAQ). Авто-последовательность
   самовосстанавливается при выключении секций тумблерами — ручные настройки
   section_numeral_* (ломали порядок) упразднены. */
$fcNumeralSeq = 0;
function fc_next_numeral(): string
{
    global $fcNumeralSeq;
    $fcNumeralSeq++;
    return sprintf('%02d', $fcNumeralSeq);
}

/* ---- Карусели (5cv): хиты / по категориям / премиум / до N ₽ ---- */

/* Хиты: is_hit=1; если ни одного — первые 8 активных по sort,id */
$hitProducts = array_values(array_filter($products, static fn (array $p): bool => (int)($p['is_hit'] ?? 0) === 1));
if ($hitProducts === []) {
    $hitProducts = array_slice($products, 0, 8);
}

/* W106 (B2, арт-директор P0-2 «монотонность»): категорийные карусели и ряд
   «До N ₽» УБРАНЫ с главной (6 однотипных рядов подряд → коллаж хитов +
   премиум-разворот до каталога). Категории доступны: вкладки каталога
   (#catalogTabs), посадочные /category/{slug} (футер/«Смотреть все»),
   чипы цен — те же фильтры low/mid/high. Тумблер feature_section_budget
   остаётся в админке (сид) — ряд больше не рендерится. */

/* Премиум: is_premium=1; если пусто — топ-3 самых дорогих (по цене после скидки) */
$premiumProducts = array_values(array_filter($products, static fn (array $p): bool => (int)($p['is_premium'] ?? 0) === 1));
if ($premiumProducts === []) {
    $byPrice = $products;
    usort($byPrice, static fn (array $a, array $b): int => productPrice($b) <=> productPrice($a));
    $premiumProducts = array_slice($byPrice, 0, 3);
}
/* W103 (F1): минимум цены премиум-линии — «цена-от» гигантским кеглем в развороте */
$premiumMinPrice = 0;
foreach ($premiumProducts as $pmRow) {
    $pmPrice = productPrice($pmRow);
    if ($premiumMinPrice === 0 || $pmPrice < $premiumMinPrice) {
        $premiumMinPrice = $pmPrice;
    }
}

/* «Дополните букет»: show_in_upsell=1 (колонка может отсутствовать в старой БД → пропуск секции) */
$addonProducts = [];
if ($featSectionAddons) {
    try {
        $addonProducts = $pdo->query('SELECT p.*, c.name AS category_name FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1 AND p.show_in_upsell = 1 ORDER BY p.sort, p.id')->fetchAll();
        foreach ($addonProducts as &$apRow) {
            $apRow['image'] = productImageFile($apRow);
        }
        unset($apRow);
    } catch (Throwable $e) {
        $addonProducts = [];
    }
}

/* ---- Поводы (5cv): активные occasions + первое фото из product_ids ---- */
$occTiles = [];
try {
    $occRows = $pdo->query('SELECT slug, title, product_ids FROM occasions WHERE active = 1 ORDER BY sort, id')->fetchAll();
} catch (Throwable $e) {
    $occRows = [];
}
$productsById = [];
foreach ($products as $p) {
    $productsById[(int)$p['id']] = $p;
}
foreach ($occRows as $o) {
    $photo = '';
    $photoAlt = '';
    /* Разбираем CSV product_ids, берём первый существующий активный товар с картинкой */
    foreach (array_filter(array_map('intval', array_map('trim', explode(',', (string)$o['product_ids'])))) as $pid) {
        if (isset($productsById[$pid]) && product_img_url($productsById[$pid]) !== '') {
            $photo = product_img_url($productsById[$pid]);
            /* W104-γ (C2-D2): осмысленный alt плитки — имя товара, чьё фото
               на плитке (критик: 8 img с пустым alt) */
            $photoAlt = (string)$productsById[$pid]['name'];
            break;
        }
    }
    $occTiles[] = [
        'slug' => (string)$o['slug'],
        /* Подпись плитки: без хвоста « в Санкт-Петербурге» (он уже в заголовке секции) */
        'title' => (string)preg_replace('/\s+в Санкт-Петербурге$/u', '', (string)$o['title']),
        'photo' => $photo,
        'photo_alt' => $photoAlt,
    ];
}
if (!$featOccasions || $occTiles === []) {
    $occTiles = []; /* пустая таблица поводов — секцию не выводим */
}

/* ---- Магазины: stores_N_title/text, N=1..3 (пустые title пропускаем) ---- */
$storesList = [];
for ($i = 1; $i <= 3; $i++) {
    $stTitle = trim(setting('stores_' . $i . '_title', ''));
    if ($stTitle === '') continue;
    $storesList[] = ['title' => $stTitle, 'text' => setting('stores_' . $i . '_text', '')];
}
$featStores = setting('feature_stores', '1') === '1' && $storesList !== [];

/* ---- Журнал: journal_N_title/text/link/image (нет непустых title — не выводим) ---- */
$journalList = [];
for ($i = 1; $i <= 3; $i++) {
    $jTitle = trim(setting('journal_' . $i . '_title', ''));
    if ($jTitle === '') continue;
    $journalList[] = [
        'title' => $jTitle,
        'text' => setting('journal_' . $i . '_text', ''),
        'link' => trim(setting('journal_' . $i . '_link', '')),
        'image' => trim(setting('journal_' . $i . '_image', '')),
    ];
}
if (!$featJournal) {
    $journalList = [];
}

/* ---- SEO-текст: sanitize_rich_text разрешает только <a>, абзацы — через \n\n ---- */
/* W104-ζ (C3-T3 P1.5): фото-абзац переформулирован — раньше повторял дословно
   ответ FAQ a3 («курьер фотографирует… вы видите то же, что получит адресат»);
   SEO-версия короче и без дубля, обещание то же. */
$seoTextDefault = "Доставка цветов по Санкт-Петербургу. Работаем по районам Санкт-Петербурга: в пределах КАД привозим букет за 1–2 часа, в пригороды — Пушкин, Павловск, Гатчина, Всеволожск — в согласованный интервал. Оформите заказ до 20:00, и цветы будут у получателя сегодня же.\n\nСвежесть — главное. Цветы приезжают к нам каждое утро, а не лежат на складе: букет собираем непосредственно перед отправкой. Если какой-то цветок выглядит не идеально, заменим его до доставки.\n\nКаждый заказ сопровождаем фото: вы видите букет до того, как его вручат.\n\nСпособ оплаты выберете при оформлении: наличными или картой курьеру при получении. Поводы бывают разные: букет маме на день рождения, извиниться, поздравить коллегу или сказать «люблю» без повода — подскажем состав под бюджет и характер события. А если сомневаетесь — просто позвоните, соберём букет вместе по телефону.";
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e(setting('seo_title', setting('shop_name', 'Nilov Flowers') . ' — доставка цветов по Санкт-Петербургу')) ?></title>
<meta name="description" content="<?= e(setting('seo_description', 'Доставка букетов по Санкт-Петербургу в день заказа. Свежий срез каждое утро, фото букета до отправки. Заказы до 20:00 — доставим сегодня.')) ?>">
<meta property="og:title" content="<?= e(setting('seo_title', setting('shop_name', 'Nilov Flowers') . ' — доставка цветов по Санкт-Петербургу')) ?>">
<meta property="og:description" content="Букеты с доставкой в день заказа по Санкт-Петербургу. Фото букета до отправки, свежий срез каждое утро.">
<meta property="og:url" content="https://flowers.interfood-catering.ru/">
<?php /* W96-fix3a (T5d): $pageDescription → twitter:description в partials/head.php
   (парно к og:description; значение — редактируемый из админки seo_description) */ ?>
<?php $pageDescription = setting('seo_description', 'Доставка букетов по Санкт-Петербургу в день заказа. Свежий срез каждое утро, фото букета до отправки. Заказы до 20:00 — доставим сегодня.'); ?>
<?php /* W103 (6-b): hero-картинка — нормализация и расчёты ЗАРАНЕЕ (нужны и
   og:image ниже, и preload, и разметке в теле): setting('hero_image') — имя
   из img/uploads/ (админ-аплоад) ИЛИ путь от корня сайта (сид-дефолт
   img/editorial/florist-hands.jpg — файл в git). $heroRoot '' = файла нет
   → fallback-градиент. Слот .fc-hero__main (css/five.css): моб ≤899px — 100vw,
   десктоп 2fr/1fr от wrap 1140 → ~640–720px CSS → sizes "(max-width:899px) 100vw,
   640px" (DPR1-десктоп берёт 768w, ретина — 1024w; моб 390/DPR1 — 480w). */
$heroImg = (string)setting('hero_image');
$heroRoot = site_image_root($heroImg);
$heroUrl = site_image_url($heroImg);
$heroWebp = $heroRoot !== '' ? (preg_replace('/\.(jpe?g|png)$/i', '.webp', $heroRoot) ?? '') : '';
$heroWebpUrl = ($heroWebp !== $heroRoot && $heroRoot !== '' && is_file(BASE_PATH . '/' . $heroWebp))
    ? '/' . implode('/', array_map('rawurlencode', explode('/', $heroWebp))) : '';
$heroWebpOk = $heroWebpUrl !== '';
/* Layout-критик W35: width/height на <img> — браузер резервирует box до загрузки */
$heroDim = $heroRoot !== '' ? (@getimagesize(BASE_PATH . '/' . $heroRoot) ?: null) : null;
$heroThumb480 = $heroRoot !== '' ? hero_img_size($heroRoot, 480) : '';
$heroThumb768 = $heroRoot !== '' ? hero_img_size($heroRoot, 768) : '';
/* W104-ζ (C3-D3): +1024w — слот .fc-hero__main на XL-экранах (wrap-ultra 1700+:
   2fr/1fr → ~1003px) при DPR1 брал 768w и апскейлил; sizes ниже честно
   разделяет десктоп (764px) и XL (1004px). Файл — тот же ленивый GD-кэш. */
$heroThumb1024 = $heroRoot !== '' ? hero_img_size($heroRoot, 1024) : '';
$heroSrcset = [];
if ($heroThumb480 !== '') { $heroSrcset[] = $heroThumb480 . ' 480w'; }
if ($heroThumb768 !== '') { $heroSrcset[] = $heroThumb768 . ' 768w'; }
if ($heroThumb1024 !== '') { $heroSrcset[] = $heroThumb1024 . ' 1024w'; }
if ($heroWebpOk && $heroDim !== null) { $heroSrcset[] = $heroWebpUrl . ' ' . (int)$heroDim[0] . 'w'; }
$heroSrcsetStr = implode(', ', $heroSrcset);
/* S4 (v2026.4): промо-баннер — полноширинная компактная карточка (100vw),
   слоты 480/768/1024 покрывают все вьюпорты. */
$heroSizes = '100vw';
?>
<?php /* W97-fixB2 (B2-7): og:image:width/height — соцсети резервируют превью без
       повторной загрузки; @-guard: файла нет — размеры не печатаем */ ?>
<?php if ($heroRoot !== ''): ?>
<meta property="og:image" content="https://flowers.interfood-catering.ru<?= e($heroUrl) ?>">
<?php if ($heroDim !== false && $heroDim !== null): ?>
<meta property="og:image:width" content="<?= (int)$heroDim[0] ?>">
<meta property="og:image:height" content="<?= (int)$heroDim[1] ?>">
<?php endif; ?>
<?php endif; ?>
<?php /* LCP-preload: hero.webp если существует (фолбэк — оригинал).
   W99-fixG (G3): imagesrcset/imagessizes дублируют srcset/sizes <source> —
   предзагрузка попадает в ТОГО ЖЕ кандидата, что выберет разметка (десктоп:
   DPR1 → 768w, ретина → 1024w); href-фолбэк для браузеров без imagesrcset —
   полноформатный webp (как раньше). W103 (6-b): пути — от корня сайта. */ ?>
<?php if ($heroRoot !== ''): ?>
<?php
$__heroPreHref = $heroWebpOk ? $heroWebpUrl : $heroUrl;
echo '<link rel="preload" as="image" href="' . e($__heroPreHref) . '" fetchpriority="high"'
    . ($heroSrcsetStr !== '' ? ' imagesrcset="' . e($heroSrcsetStr) . '" imagesizes="' . e($heroSizes) . '"' : '')
    . '>' . "\n";
?>
<?php endif; ?>
<?php /* W104-α (A): preload обоих Playfair-подмножеств кириллицы — H1 hero
   (roman) и его акцент-слово <em> (НАСТОЯЩИЙ italic теперь в /fonts):
   LCP-текст не должен ждать свапа с Georgia-фолбэка. */ ?>
<link rel="preload" href="/fonts/PlayfairDisplay-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/PlayfairDisplay-Italic-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* JSON-LD Florist — canonical 2026 (hanafloristpos.com/schema-guide, thestacc.com/local-business-schema) */ ?>
<?php
/* W99-fixG (G5): openingHoursSpecification — из setting('shop_hours'), если там
   есть диапазон ЧЧ:ММ-ЧЧ:ММ (в любом месте строки: «Ежедневно 9:00-21:00»,
   «09:00–21:00», вынос «:»-разделителя и тире любого вида). Не распарсилось
   (ключ пуст — дефолт владельцем не заполняется) — блока НЕТ вовсе: честное
   отсутствие вместо выдуманных 09:00-21:00. */
$__openSpec = [];
if (preg_match('/(\d{1,2})[:.](\d{2})\s*[-–—.]\s*(\d{1,2})[:.](\d{2})/u', (string)setting('shop_hours', ''), $__hm)) {
    $__oh = max(0, min(23, (int)$__hm[1]));
    $__om = max(0, min(59, (int)$__hm[2]));
    $__ch = max(0, min(23, (int)$__hm[3]));
    $__cm = max(0, min(59, (int)$__hm[4]));
    if ($__oh < $__ch || ($__oh === $__ch && $__om < $__cm)) {
        $__openSpec = [[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'opens' => sprintf('%02d:%02d', $__oh, $__om),
            'closes' => sprintf('%02d:%02d', $__ch, $__cm),
        ]];
    }
}
/* W99-fixG (G8): sameAs — только непустые и включённые соц-профили (набор ключей
   как в футере: yandex_reviews_id / shop_vk / shop_instagram / shop_max_link /
   shop_telegram; для коротких значений без http — канонический префикс сети).
   Пусто — ключа sameAs нет вовсе. */
$__sameAs = [];
if (setting('yandex_reviews_id') !== '') {
    $__sameAs[] = 'https://yandex.ru/maps/org/' . setting('yandex_reviews_id');
}
$__socialLink = static function (string $val, string $prefix): string {
    $val = trim($val);
    return $val === '' ? '' : (preg_match('#^https?://#i', $val) ? $val : $prefix . ltrim($val, '/@'));
};
if (setting('vk_enabled', '1') === '1') {
    $__sa = $__socialLink(setting('shop_vk', ''), 'https://vk.com/');
    if ($__sa !== '') $__sameAs[] = $__sa;
}
if (setting('ig_enabled', '1') === '1') {
    $__sa = $__socialLink(setting('shop_instagram', ''), 'https://instagram.com/');
    if ($__sa !== '') $__sameAs[] = $__sa;
}
if (setting('max_enabled', '1') === '1') {
    $__sa = $__socialLink(setting('shop_max_link', ''), 'https://max.ru/');
    if ($__sa !== '') $__sameAs[] = $__sa;
}
if (setting('tg_enabled', '1') === '1') {
    $__sa = $__socialLink(setting('shop_telegram', ''), 'https://t.me/');
    if ($__sa !== '') $__sameAs[] = $__sa;
}
$__sameAs = array_values(array_unique($__sameAs));
/* W103 (критик-9 P0): открывающий тег отсутствовал — сырой JSON рендерился
   видимым текстом над шапкой (закрывающий </script> был, открывающего нет) */
?><script type="application/ld+json"><?php
echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Florist',
    'name' => setting('shop_name', 'Nilov Flowers'),
    'url' => 'https://flowers.interfood-catering.ru/',
    'telephone' => setting('shop_phone', ''),
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => setting('shop_address', 'Петроградская сторона'),
        'addressLocality' => 'Санкт-Петербург',
        'addressCountry' => 'RU',
    ],
    'addressString' => setting('shop_address', ''),
    'priceRange' => '₽₽',
    /* W106-D3fix: захардкоженный фейковый адрес+geo удалены — реальные придут
       с настройками владельца (shop_address); точность важнее полноты разметки */
    'image' => $heroUrl !== '' ? 'https://flowers.interfood-catering.ru' . $heroUrl : '',
] + ($__openSpec !== [] ? ['openingHoursSpecification' => $__openSpec] : [])
  + ($__sameAs !== [] ? ['sameAs' => $__sameAs] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php /* W97-fixB3b (B3b-5а): WebSite + SearchAction — sitelinks searchbox Google:
   запрос уходит на /?q={search_term_string} (тот же ?q=, что живой поиск five.js
   и нативный submit формы шапки; парность источников = без расхождений) */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => setting('shop_name', 'Nilov Flowers'),
    'url' => 'https://flowers.interfood-catering.ru/',
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => [
            '@type' => 'EntryPoint',
            'urlTemplate' => 'https://flowers.interfood-catering.ru/?q={search_term_string}',
        ],
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<?php /* W97-fixB3a (B3a-5, a11y): skip-link — ПЕРВЫЙ элемент в DOM: первое нажатие Tab
   попадает в него. .skip-link position:fixed;top:-48px (css/style.css) — вне
   потока. Флаг $skipLinkRendered — header.php не дублирует ссылку
   (на остальных витринных страницах её печатает сам header.php). */ ?>
<?php $skipLinkRendered = true; ?>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<?php /* W106-C1 (дизайн-дир P0-2 «чёрный сервисный бар съедает первый экран»):
   ГЕО-ТОПБАР УДАЛЁН — вопрос «Санкт-Петербург — ваш город?» больше не
   встречает покупателя чёрной полосой над шапкой. Город тихо живёт в
   шапке рядом с контаками и в футере (кнопка-дропдаун .fc-city →
   js/five.js cityMenu(): выбор «Санкт-Петербург» пишет тот же маркер
   localStorage 'fc-city-ok', ссылка «Выбрать другой» ведёт на #contacts —
   прежняя логика выбора города сохранена без вопроса при первом визите). */ ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
<?php /* S4 (v2026.4): КОМПАКТНЫЙ ПРОМО-БАННЕР — замена литературного hero-бенто
   (972px на мобильном). Высота ≤220px на мобильном / ≤300px на десктопе;
   ЧИПСЫ-категории и СЕТКА БУКЕТОВ начинаются сразу под ним — первый экран
   продаёт товар. Контент — прежние настройки hero_* (eyebrow/H1/подзаголовок/
   CTA/фото) + живой рейтинг; карточка доставки hero ушла в топ-полоску шапки
   (инфо-бар), настройки hero_delivery_* остаются в БД/админке. */ ?>
  <section class="fc-promo" aria-label="Актуальное предложение">
    <div class="wrap">
      <div class="fc-promo__card<?= $heroRoot === '' ? ' fc-promo__card--fallback' : '' ?>">
        <?php if ($heroRoot !== ''): ?>
        <picture>
          <?php if ($heroWebpOk && $heroSrcsetStr !== ''): ?><source type="image/webp" srcset="<?= e($heroSrcsetStr) ?>" sizes="100vw"><?php elseif ($heroWebpOk): ?><source type="image/webp" srcset="<?= e($heroWebpUrl) ?>"><?php endif; ?>
          <img class="fc-promo__img" src="<?= e($heroUrl) ?>" alt="<?= e(setting('hero_image_alt', 'Свежий букет из сезонных цветов — витрина магазина')) ?>" fetchpriority="high"<?= $heroDim ? ' width="' . (int)$heroDim[0] . '" height="' . (int)$heroDim[1] . '"' : '' ?>>
        </picture>
        <?php endif; ?>
        <div class="fc-promo__content">
        <?php if ($heroTextEnabled): ?>
        <?php
        $heroEyebrowVal = trim((string)setting('hero_eyebrow', 'Санкт-Петербург · собираем под ваш заказ'));
        $heroEyebrowSegs = $heroEyebrowVal !== ''
            ? array_values(array_filter(array_map('trim', explode('·', $heroEyebrowVal)), static fn (string $s): bool => $s !== ''))
            : [];
        ?>
        <?php if ($heroEyebrowSegs !== []): ?><p class="fc-promo__eyebrow"><?= implode('', array_map(static fn (int $i, string $s): string => '<span class="fc-promo__eyebrow-seg">' . e(rtrim($s) . ($i < count($heroEyebrowSegs) - 1 ? ' ·' : '')) . '</span>', array_keys($heroEyebrowSegs), $heroEyebrowSegs)) ?></p><?php endif; ?>
        <h1 class="fc-promo__title"><?= e($heroH1) ?></h1>
        <?php if ($heroSubtitle !== ''): ?><p class="fc-promo__sub"><?= e($heroSubtitle) ?></p><?php endif; ?>
        <?php endif; ?>
        <?php if ($heroBtn || $heroGhost || $heroRating !== null): ?>
        <div class="fc-promo__actions">
        <?php if ($heroBtn): ?>
        <a class="fc-btn fc-promo__cta" href="<?= e($heroBtnLink) ?>">
          <?= e($heroBtnText) ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($heroGhost): ?>
        <a class="fc-promo__ghost" href="<?= e($heroGhostLink) ?>"><?= e($heroGhostText) ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        <?php endif; ?>
        <?php if ($heroRating !== null): ?>
        <a class="fc-promo__rating" href="#reviews">
          <span class="fc-promo__rating-star" aria-hidden="true">★</span>
          <span class="fc-promo__rating-num"><?= e(str_replace('.', ',', (string)round($heroRating['avg'], 1))) ?></span>
          <span class="fc-promo__rating-note">по отзывам покупателей</span>
        </a>
        <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>
        <?php /* NILOV_CONFIG — общий конфиг JS (порог бесплатной доставки и др.).
               S5: + живой таймер доставки (deliveryNowMinutes/Text/Tomorrow/Short —
               настройки delivery_now_*; рендерит js/five.js deliveryNow()). */ ?>
        <script>window.NILOV_CONFIG = {
          deadlineHour: <?= (int)(setting('order_deadline_hour', '20')) ?>,
          deadlineMinute: <?= (int)(setting('order_deadline_minute', '0')) ?>,
          openHour: <?= (int)(preg_match('/(\d{1,2})\s*:/', (string)setting('shop_hours', ''), $m) ? max(0, min(23, (int)$m[1])) : 9) ?>,
          tz: <?= json_encode(setting('shop_timezone', 'Europe/Moscow')) ?>,
          nightText: <?= json_encode(setting('countdown_night_text', 'Примем заказ сейчас — доставим с 9:00 утра'), JSON_UNESCAPED_UNICODE) ?>,
          countdownText: <?= json_encode(setting('countdown_text', 'Заказ до {D} — доставим сегодня'), JSON_UNESCAPED_UNICODE) ?>,
          closedText: <?= json_encode(setting('countdown_closed_text', 'Приём заказов на сегодня закрыт — доставим завтра с 9:00'), JSON_UNESCAPED_UNICODE) ?>,
          freeDeliveryThreshold: <?= (int) setting('free_delivery_threshold', '0') ?>,
          deliveryNowMinutes: <?= (int) setting('delivery_now_minutes', '90') ?>,
          deliveryNowText: <?= json_encode(setting('delivery_now_text', 'Ближайшая доставка по СПб: сегодня к {time}'), JSON_UNESCAPED_UNICODE) ?>,
          deliveryNowTomorrow: <?= json_encode(setting('delivery_now_tomorrow', 'Ближайшая доставка по СПб: завтра к {time}'), JSON_UNESCAPED_UNICODE) ?>,
          deliveryNowShort: <?= json_encode(setting('delivery_now_short', 'Доставим сегодня к {time}'), JSON_UNESCAPED_UNICODE) ?>
        };</script>
      </div>
    </div>
  </section>

  <?php /* ЧИПЫ ЦЕН (5cv → W104): пастельные плитки-навигация с счётчиками.
       Хиты / До N / N–M / От M / Премиум — фильтруют каталог (js/five.js +
       catalog-filter.js по data-chip; контракт классов не менялся).
       Счётчики считаем здесь же по тем же правилам, что фильтр: low ≤ N,
       N ≤ mid ≤ M, high ≥ M (границы принадлежат обоим диапазонам).
       D-d1 (P0-1): чипы опущены на границу 2-го экрана — вход в каталог.
       G-g2 (редактор P1): «До/От» — строчными, как опции селекта «Цена:»
       (до 3 500 ₽ / от 7 000 ₽) — один регистр во всех ценовых фильтрах. */ ?>
  <?php if ($featChips): ?>
  <?php
  $cntHit = 0; $cntLow = 0; $cntMid = 0; $cntHigh = 0; $cntPremium = 0;
  foreach ($products as $pc) {
      $pprice = productPrice($pc);
      if ((int)($pc['is_hit'] ?? 0) === 1) { $cntHit++; }
      if ((int)($pc['is_premium'] ?? 0) === 1) { $cntPremium++; }
      if ($pprice <= $chipsN) { $cntLow++; }
      if ($pprice >= $chipsN && $pprice <= $chipsM) { $cntMid++; }
      if ($pprice >= $chipsM) { $cntHigh++; }
  }
  $pluralBuket = static function (int $n): string {
      $n10 = $n % 10; $n100 = $n % 100;
      if ($n10 === 1 && $n100 !== 11) return 'букет';
      if ($n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14)) return 'букета';
      return 'букетов';
  };
  ?>
  <div class="wrap">
    <div class="fc-chips" id="fcChips">
      <?php /* S5 (ЭТАП 4): порядок ленты 5cv — «Все букеты» → цены → «Премиум» →
             теги-коллекции (Пионы / Французские розы / Гортензии / В коробках /
             Подарок) → хвост «Хиты» и «от 7 000 ₽» (функциональность жива,
             JS-контракты data-chip/data-tag/data-max/data-min не менялись). */ ?>
      <button type="button" class="fc-chip fc-chip--all is-active" data-chip="all" aria-pressed="true">
        <span class="fc-chip__title"><?= e(setting('chips_all_text', 'Все букеты')) ?></span>
      </button>
      <button type="button" class="fc-chip fc-chip--low" data-chip="low" data-max="<?= $chipsN ?>" aria-pressed="false">
        <span class="fc-chip__title">до&nbsp;<?= formatSum($chipsN) ?>&nbsp;₽</span>
      </button>
      <button type="button" class="fc-chip fc-chip--mid" data-chip="mid" data-min="<?= $chipsN ?>" data-max="<?= $chipsM ?>" aria-pressed="false">
        <span class="fc-chip__title"><?= formatSum($chipsN) ?>–<?= formatSum($chipsM) ?>&nbsp;₽</span>
      </button>
      <button type="button" class="fc-chip fc-chip--premium" data-chip="premium" aria-pressed="false">
        <span class="fc-chip__title">Премиум</span>
      </button>
      <?php /* S3 (v2026.3): чипсы-теги — из настройки chips_tags (через запятую),
             матчатся по data-tags карточек (стем первых 4 букв слова / подстрока
             для многословных). Счётчик — PHP-предподсчёт по тем же правилам, что
             js/catalog-filter.js (nf_tag_match). */ ?>
      <?php
      $chipsTags = array_values(array_filter(array_map('trim', explode(',', setting('chips_tags', ''))), fn($t) => $t !== ''));
      /* S5: матчинг тегов — ВСЕ слова чипа должны найтись в тегах карточки
             (по стему первых 4 букв, как однословный). «В коробках» матчит
             «в шляпных коробках», «Подарок» — «подарок девушке».
             ИДЕНТИЧНО js/catalog-filter.js nfTagMatch (контракт!). */
      $nfTagMatch = static function (string $chip, array $p): bool {
          $chipN = mb_strtolower(preg_replace('/\s+/u', ' ', trim($chip)) ?? '', 'UTF-8');
          $chipN = str_replace('ё', 'е', $chipN);
          if ($chipN === '') { return false; }
          $tagsN = str_replace('ё', 'е', mb_strtolower(preg_replace('/\s+/u', ' ', trim((string)($p['tags'] ?? ''))) ?? '', 'UTF-8'));
          if ($tagsN === '') { return false; }
          $chipWords = preg_split('/[\s,]+/u', $chipN) ?: [];
          $tagWords = preg_split('/[\s,]+/u', $tagsN) ?: [];
          foreach ($chipWords as $cw) {
              if ($cw === '') { continue; }
              $stem = mb_substr($cw, 0, 4, 'UTF-8');
              $found = false;
              foreach ($tagWords as $tw) {
                  if ($tw !== '' && mb_strpos($tw, $stem, 0, 'UTF-8') === 0) { $found = true; break; }
              }
              if (!$found) { return false; }
          }
          return $chipWords !== [];
      };
      foreach ($chipsTags as $chipTag):
          $cntTag = 0;
          foreach ($products as $pc) {
              if ($nfTagMatch($chipTag, $pc)) { $cntTag++; }
          }
          if ($cntTag === 0) { continue; } /* чип без товаров — не печатаем */
      ?>
      <button type="button" class="fc-chip fc-chip--tag" data-chip="tag-<?= e(mb_strtolower(str_replace('ё', 'е', preg_replace('/\s+/u', ' ', trim($chipTag)) ?? ''), 'UTF-8')) ?>" data-tag="<?= e($chipTag) ?>" aria-pressed="false">
        <span class="fc-chip__title"><?= e($chipTag) ?></span>
      </button>
      <?php endforeach; ?>
      <?php /* S5: хвост ленты — «Хиты» и «от 7 000 ₽» (функциональные фильтры,
             вынесены после коллекций-тегов по эталону 5cv) */ ?>
      <button type="button" class="fc-chip fc-chip--hit" data-chip="hit" aria-pressed="false">
        <span class="fc-chip__title">Хиты</span>
      </button>
      <button type="button" class="fc-chip fc-chip--high" data-chip="high" data-min="<?= $chipsM ?>" aria-pressed="false">
        <span class="fc-chip__title">от&nbsp;<?= formatSum($chipsM) ?>&nbsp;₽</span>
      </button>
      <span class="fc-chips__count" aria-live="polite" style="flex:none;align-self:center;white-space:nowrap;font-size:.85rem;font-weight:600;color:var(--ink-muted)"></span>
    </div>
  </div>
  <?php endif; ?>

  <?php /* W96 (5cv): trust-strip убран — роль играют чипы и карточка доставки в hero.
     W103 (F1): marquee ВЕРНУЛСЯ (см. блок выше) — ритм-разделитель под hero. */ ?>

  <?php /* W97-fixB2 (B2-5а): каталог — тоже товарная секция.
     W106-C1 (P0-5 «фон-ритм середины»): каталогу — КРЕМОВЫЙ фон (surface-warm):
     после двух тёмных фулл-блидов (манифест + премиум) белый грид каталога
     сливался с белыми секциями ниже — теперь ритм: dark → крем-каталог →
     soft «Дополните» → белый отзывы → soft поводы → тёплая форма. */ ?>
  <?php /* S5: ЖИВОЙ ТАЙМЕР ДОСТАВКИ (Conversion Booster) — 🟢 «Ближайшая
       доставка по СПб: сегодня к 15:30». Время считает js/five.js
       (СПб + delivery_now_minutes, округление к 15 мин); без JS — скрыт.
       На мобиле живёт в одной строке с меткой «КАТАЛОГ» (ноль доп. высоты —
       сетка букетов остаётся в первом экране), на десктопе — под H2. */ ?>
  <?php $featDeliveryNow = setting('feature_delivery_now', '1') === '1'; ?>
  <?php $fcProdSeq++; ?>
  <section class="fc-section fc-section--tint" id="catalog">
    <div class="wrap">
      <?php /* W104: единый компонент шапки товарной секции — eyebrow + H2 +
             oversize-нумерал (data-numeral), «Смотреть все» здесь не нужен —
             это сам каталог. */ ?>
      <div class="fc-row__head">
        <div class="fc-row__heading" data-numeral="<?= e(fc_next_numeral()) ?>">
        <?php render_fc_eyebrow(setting('catalog_eyebrow', 'Весь|ассортимент')); ?>
        <h2 class="fc-row__title"><?= e(setting('catalog_title', 'Каталог')) ?></h2>
        <?php $catalogSub = setting('catalog_subtitle', 'Выбирайте букет — соберём и привезём сегодня'); /* W104-λ (C4-T4): было «…в день заказа» — дублировало формулу hero/SEO */ ?>
        <?php if ($catalogSub !== ''): ?><p class="fc-row__sub"><?= e($catalogSub) ?></p><?php endif; ?>
        <?php if ($featDeliveryNow): ?>
        <p class="fc-delivery-now" id="fcDeliveryNow" hidden>
          <span class="fc-delivery-now__dot" aria-hidden="true"></span>
          <span class="fc-delivery-now__full"></span>
          <span class="fc-delivery-now__short"></span>
        </p>
        <?php endif; ?>
        </div>
      </div>
      <?php /* W105-8fix1 (критик-UX 8-b P1#2): индикатор активного поиска — пилюля
             «Поиск: «запрос» ✕» между шапкой каталога и вкладками. Состояние
             ведёт js/catalog-filter.js (apply()); клик — снять запрос и
             пересчитать сетку. Без JS скрыт (hidden) — поиск сам JS-овский. */ ?>
      <button type="button" class="catalog-search-chip" id="catalogSearchChip" hidden>
        <span class="catalog-search-chip__label" id="catalogSearchChipLabel">Поиск</span>
        <span class="catalog-search-chip__x" aria-hidden="true">&#10005;</span>
      </button>
      <div class="catalog-tabs" id="catalogTabs" role="group" aria-label="Фильтр каталога по категориям">
        <button type="button" class="catalog-tabs__tab is-active" aria-pressed="true" data-category-id="all">Все</button>
        <?php foreach ($categories as $c): ?>
          <button type="button" class="catalog-tabs__tab" aria-pressed="false" data-category-id="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <?php /* W96-fix2 (F9): фильтры каталога — ОДИН аккуратный ряд
             (цена · избранное · район): flex + space-between, на мобиле wrap.
             Было два ряда (цена отдельно, избранное+район ниже) с разным
             выравниванием. ID/классы элементов не менялись — catalog-filter.js
             и nilov.js работают как раньше. margin-left:auto у zone-check
             сохранён (правый край при любом наборе включённых фильтров). */ ?>
      <?php if ($featPriceFilter || $featFavorites || $featZoneCheck): ?>
      <div class="catalog-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:10px 18px;margin:0 0 18px;flex-wrap:wrap">
        <?php if ($featPriceFilter):
            $pfLow = (int)setting('price_filter_low', '2500');
            $pfHigh = (int)setting('price_filter_high', '4000');
        ?>
        <span style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
          <label for="priceFilter" style="font-size:.85rem;font-weight:600;color:var(--ink-soft)">Цена:</label>
          <select id="priceFilter" class="pill">
            <option value="all" selected>Любая</option>
            <option value="low" data-max="<?= $pfLow ?>">до <?= formatSum($pfLow) ?> ₽</option>
            <option value="mid" data-min="<?= $pfLow ?>" data-max="<?= $pfHigh ?>"><?= formatSum($pfLow) ?>–<?= formatSum($pfHigh) ?> ₽</option>
            <option value="high" data-min="<?= $pfHigh ?>">от <?= formatSum($pfHigh) ?> ₽</option>
          </select>
          <span id="priceFilterCount" style="font-size:.85rem;color:var(--ink-soft)" aria-live="polite"></span>
        </span>
        <?php endif; ?>
        <?php if ($featFavorites): ?><button type="button" id="favToggle" class="fav-toggle" aria-pressed="false">♡ Избранное</button><?php endif; ?>
        <?php /* Проверка зоны доставки (критерий 13, Семицветик-паттерн): тариф района до чекаута.
               Данные зон инлайн (HTML-атрибут) — JS-мэтч по вводу покупателя. Отключаем (критерий 16). */ ?>
        <?php if ($featZoneCheck): ?>
        <span class="zone-check" style="display:inline-flex;align-items:center;gap:6px;margin-left:auto">
          <label for="zoneCheckInput" style="font-size:.85rem;font-weight:600;color:var(--ink-soft)">Район:</label>
          <input type="search" id="zoneCheckInput" placeholder="<?= e(setting('zone_check_placeholder', 'Например: Центральный')) ?>" aria-label="Узнать стоимость доставки в ваш район"
                 data-fallback="<?= e(setting('zone_check_fallback', 'Район не найден — уточним по телефону')) ?>"
                 style="width:clamp(150px,46vw,240px);min-width:0" class="pill"
                 list="zoneCheckList">
          <datalist id="zoneCheckList">
            <?php foreach ($zones as $z): ?><option value="<?= e($z['name']) ?>"></option><?php endforeach; ?>
          </datalist>
          <span id="zoneCheckResult" style="font-size:.85rem;font-weight:600;min-width:96px;white-space:nowrap;display:inline-block" aria-live="polite"></span>
        </span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="catalog__grid" id="catalogGrid">
        <?php foreach ($products as $p) { render_product_card($p, $cardCtx); } ?>
        <?php /* W105-6fix1: хвост ряда — CTA-плитка дозаполняет последний ряд
               (23 SKU → 4×5 + 3 + плитка span 2; span под остаток ставит
               js/grid-tail.js после каждого фильтра и на resize). ink-плитка
               в языке поводов/премиума; без JS — статический span 2. */ ?>
        <?php
        $tailTitle = trim((string)setting('catalog_tail_title', 'Соберём|на заказ'));
        [$tailT1, $tailT2] = array_pad(explode('|', $tailTitle, 2), 2, '');
        if ($tailT1 !== ''):
        ?>
        <a class="catalog-tail reveal" href="#order" data-grid-tail aria-label="Собрать букет на заказ">
          <span class="catalog-tail__kicker"><?= e(setting('catalog_tail_kicker', 'Не нашли нужный букет?')) ?></span>
          <span class="catalog-tail__title"><?= e($tailT1) ?><?= $tailT2 !== '' ? ' <em>' . e($tailT2) . '</em>' : '' ?></span>
          <span class="catalog-tail__text"><?= e(setting('catalog_tail_text', 'Под ваш повод, палитру и бюджет — фото готового букета пришлём перед доставкой')) ?></span>
          <span class="catalog-tail__arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>
        <?php endif; ?>
      </div>
      <?php /* Empty-state (критик P2): при 0 карточек от фильтров — подсказка + сброс. Тексты редактируются (критерий 16).
         W104-α (L2): без эмодзи и канцелярита — короткая строка-титул + подсказка-действие. */ ?>
      <div class="catalog-empty" id="catalogEmpty" hidden style="text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px;margin-top:14px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px"><?= e(setting('catalog_empty_title', 'Под эти фильтры ничего не подошло')) ?></p>
        <?php $catalogEmptyHint = trim(setting('catalog_empty_hint', 'Сбросьте цену или загляните в соседнюю категорию')); ?>
        <?php if ($catalogEmptyHint !== ''): ?><p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px"><?= e($catalogEmptyHint) ?></p><?php endif; ?>
        <button type="button" class="btn btn--outline" id="catalogEmptyReset">Сбросить фильтры</button>
      </div>
    </div>
  </section>
  <?php /* W106 (B2): editorial №2 убран (сообщение «соберём на заказ» уже несёт
     CTA-плитка хвоста каталога); на его месте — компакт-рельс дополнений
     (перенесён из зоны до каталога) и новая секция отзывов. */ ?>

  <?php /* W103 (F1): MARQUEE-лента (ink) сразу под hero — ритм-разделитель перед
     витринными секциями. Тексты — существующие marquee_1..4; каждая «половина»
     трека повторяет набор ×3 (ширины хватает на 1920px+ без шва), вторая
     половина aria-hidden — скринридер не читает дубль.
     W106-C1 (a11y P1-9): внутри первой половины повторы №2–3 тоже
     aria-hidden — скринридер слышит набор фраз ОДИН раз, визуальный
     бесшовный цикл не меняется. */ ?>
  <?php if ($featMarquee && $marqueeItems !== []): ?>
  <div class="fc-marquee">
    <div class="fc-marquee__track">
      <?php for ($mqRep = 0; $mqRep < 2; $mqRep++): ?>
      <div class="fc-marquee__half"<?= $mqRep === 1 ? ' aria-hidden="true"' : '' ?>>
        <?php for ($mqSet = 0; $mqSet < 3; $mqSet++): foreach ($marqueeItems as $mqText): ?>
        <span class="fc-marquee__item"<?= $mqRep === 0 && $mqSet > 0 ? ' aria-hidden="true"' : '' ?>><?= e($mqText) ?></span><span class="fc-marquee__sep" aria-hidden="true">&#10047;</span>
        <?php endforeach; endfor; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php /* W96-fix1 (F8): траст-ряд под hero — гарантии с галочками (гейт hero-текста,
     те же guarantee_1..3, что на странице товара; прячется вместе с hero-текстом).
     W100-fixH1 (I16): соцдоказательство — пилюля-ссылка «Отзывы на Яндекс Картах»
     в том же ряду: ТОЛЬКО при включённой настройке yandex_reviews_enabled И
     непустом yandex_reviews_id (выкл/пусто — элемента нет, ряд как был). */ ?>
  <?php $yrId = trim(setting('yandex_reviews_id', ''));
     $yrOn = setting('yandex_reviews_enabled', '0') === '1' && $yrId !== ''; ?>
  <?php if (($heroTextEnabled && $guarantees !== []) || $yrOn): ?>
  <div class="wrap">
    <ul class="fc-trust" aria-label="Наши гарантии">
      <?php foreach (array_slice($guarantees, 0, 3) as $g): ?>
      <li class="fc-trust__item"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="currentColor"/><path d="M7 12.5l3.2 3.2L17 9" stroke="#fff" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg><?= e($g) ?></li>
      <?php endforeach; ?>
      <?php if ($yrOn): ?>
      <li class="fc-trust__item"><a href="https://yandex.ru/maps/org/<?= e(rawurlencode($yrId)) ?>" target="_blank" rel="noopener" style="color:inherit;text-decoration:none">★★ Отзывы о нас на Яндекс Картах →</a></li>
      <?php endif; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php /* СЕКЦИИ-КАРУСЕЛИ (5cv → W103 → W106/B2): хиты-коллаж → manifesto → премиум-dark.
       W106 (B2, арт-директор P0-2): монотонность 6 рядов — до каталога осталось
       ДВЕ товарные секции (коллаж хитов + премиум-разворот) + фотополоса-манифест
       (сигнатурное мгновение поднято с ~4000px к началу страницы). Категорийные
       карусели и ряд «До N ₽» убраны — их дублируют вкладки каталога, чипы цен и
       посадочные /category/{slug} (футер); тумблеры feature_* остаются честными:
       feature_section_hits гасит коллаж, feature_carousels — рельс дополнений. */ ?>
  <?php if ($featSectionHits): render_fc_collage(
        setting('section_hits_title', 'Хиты продаж'),
        setting('section_hits_sub', 'Выбор, который сложно испортить'),
        array_slice($hitProducts, 0, 5), $cardCtx,
        setting('section_hits_eyebrow', 'Выбор|покупателей'),
        fc_next_numeral());
    endif; ?>

  <?php /* W103 (F1) → W106 (B2): MANIFESTO — full-bleed фотополоса сразу за
     коллажем хитов (сигнатурное мгновение — в начале пути, а не на 4000px;
     ритм: коллаж → имидж-полоса → премиум-разворот). Фото: img/editorial/
     florist-hands.jpg; пока файла нет — фолбэк gen9.jpg (пионы). */ ?>
  <?php
  $manifestoImg = '/img/editorial/florist-hands.jpg';
  if (!is_file(BASE_PATH . $manifestoImg)) {
      $manifestoImg = '/img/products/gen9.jpg'; /* TODO(W103): фолбэк до florist-hands.jpg */
  }
  /* W104-ζ (C3-D3): full-bleed-полоса грузила jpg-оригинал целиком — тот же
     webp-конвейер (480/768 ленивые GD-превью + webp-оригинал {w}w),
     sizes 100vw (полоса без полей). Файлов нет — одиночный src как раньше. */
  $manifestoRoot = ltrim($manifestoImg, '/');
  $__mfWebp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $manifestoRoot) ?? '';
  $__mfWebpOk = $__mfWebp !== $manifestoRoot && is_file(BASE_PATH . '/' . $__mfWebp);
  $__mfDim = @getimagesize(BASE_PATH . '/' . $manifestoRoot);
  $__mfSrcset = [];
  foreach ([480, 768] as $__mfW) {
      $__mfT = hero_img_size($manifestoRoot, $__mfW);
      if ($__mfT !== '') { $__mfSrcset[] = $__mfT . ' ' . $__mfW . 'w'; }
  }
  if ($__mfWebpOk && $__mfDim !== false) { $__mfSrcset[] = '/' . implode('/', array_map('rawurlencode', explode('/', $__mfWebp))) . ' ' . (int)$__mfDim[0] . 'w'; }
  $__mfSrcsetStr = implode(', ', $__mfSrcset);
  ?>
  <section class="fc-manifesto reveal" aria-label="<?= e(setting('manifesto_kicker', 'Наши принципы')) ?>">
    <?php if ($__mfSrcsetStr !== ''): ?>
    <picture>
      <source type="image/webp" srcset="<?= e($__mfSrcsetStr) ?>" sizes="100vw">
      <img class="fc-manifesto__img" src="<?= e($manifestoImg) ?>" alt="Флорист собирает букет из свежих цветов" loading="lazy" decoding="async">
    </picture>
    <?php else: ?>
    <img class="fc-manifesto__img" src="<?= e($manifestoImg) ?>" alt="Флорист собирает букет из свежих цветов" loading="lazy" decoding="async">
    <?php endif; ?>
    <div class="fc-manifesto__content">
      <p class="fc-manifesto__kicker"><?= e(setting('manifesto_kicker', 'Наши принципы')) ?></p>
      <?php /* W104-ζ (C3-T3 P1.4): типографская норма — тире не открывает строку:
         пробел ПЕРЕД «—» клеится в NBSP на рендере (любой текст владельца,
         без правки БД). Дефолт-фраза переведена на бессрочную версию
         («утром и везём…»): js/kinetic.js при сплите нормализует \s+→' '
         и СЪЕДАЕТ NBSP — тире снова открывало строку 2 (замер 1440:
         «Собираем букеты утром» / «— и везём вам сегодня»); вернуть тире
         можно правкой kinetic.js:190 (см. worklog W104-ζ). */ ?>
      <p class="fc-manifesto__text"><?= e(preg_replace('/ +—/u', "\u{00A0}—", (string)setting('manifesto_text', 'Собираем букеты утром и везём вам сегодня'))) ?></p>
    </div>
  </section>

  <?php /* W103 (F1): ПРЕМИУМ — тёмный редакционный разворот вместо карусели.
     Данные те же (is_premium=1 / топ-3 по цене), гейт feature_section_premium.
     Фон: img/editorial/petals-macro.jpg с тёмным оверлеем (нет файла — чистый ink). */ ?>
  <?php if ($featSectionPremium && $premiumProducts !== [] && $premiumMinPrice > 0): ?>
  <?php $fcProdSeq++; /* участник чередования фонов товарных секций */ ?>
  <section class="fc-premium reveal" id="premium"<?= is_file(BASE_PATH . '/img/editorial/petals-macro.jpg') ? ' style="background-image:url(\'' . e(hero_img_size('img/editorial/petals-macro.jpg', 1024) ?: '/img/editorial/petals-macro.jpg') . '\')"' : '' ?>>
    <div class="wrap">
      <div class="fc-premium__grid">
        <div class="fc-premium__cards">
          <?php foreach (array_slice($premiumProducts, 0, 3) as $prIdx => $prP): ?>
          <a class="fc-premium__card<?= $prIdx === 0 ? ' fc-premium__card--lead' : '' ?>" href="/product/<?= e(rawurlencode($prP['slug'])) ?>">
            <span class="fc-premium__photo"><?php render_premium_picture($prP, $prIdx === 0
                ? '(max-width:899px) 64vw, 400px' /* W104-ζ: lead-картинка 380px десктоп / min(64vw,300px) моб — было 400px/72vw */
                : '(max-width:899px) 64vw, 280px'); ?></span>
            <span class="fc-premium__card-body">
              <span class="fc-premium__card-name"><?= e($prP['name']) ?></span>
              <span class="fc-premium__card-price"><?= formatPrice(productPrice($prP)) ?></span>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <div class="fc-premium__info" data-numeral="<?= e(fc_next_numeral()) ?>">
          <h2 class="fc-premium__title"><?= e(setting('premium_title', 'Для особых случаев')) ?></h2>
          <p class="fc-premium__sub"><?= e(setting('premium_sub', 'Крупные композиции из гортензий, пионов и орхидей — когда впечатление важнее бюджета')) ?></p>
          <p class="fc-premium__price-row">
            <span class="fc-premium__price-label"><?= e(setting('premium_price_label', 'Букеты от')) ?></span>
            <span class="fc-premium__price"><?= formatSum($premiumMinPrice) ?><?= "\u{00A0}" /* W105-6fix2: NBSP и перед знаком валюты — ₽ в дочернем спане, текстовый проход js/glue.js его не видит (кросс-узловая граница) */ ?><span class="fc-premium__price-cur">&#8381;</span></span>
          </p>
          <a class="fc-btn fc-premium__cta" href="#catalog" data-chip="premium"><?= e(setting('premium_cta_text', 'Смотреть премиум')) ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* W106 (B2): ряд «До N ₽» убран — дублирует чип «До N ₽» (тот же фильтр
     low в каталоге); editorial №1 убран вместе с ним (позиция ритма умерла).
     До каталога — коллаж хитов → манифест → премиум: 2 товарные секции. */ ?>

  <?php /* КАТАЛОГ (5cv): вкладки-категории + чипы цен + сетка карточек. */ ?>
  <?php /* W103 (6-b, критик-5а P0 «каталожный дамп»): editorial full-bleed полоса
         ПЕРЕД каталогом — фото лепестков + ink-оверлей + Playfair-цитата.
         Полоса — самостоятельная секция ВНЕ грида: #catalogTabs/#catalogGrid
         и catalog-filter.js не затронуты (фильтры работают как раньше). */ ?>
  <?php $catalogStripText = trim((string)setting('catalog_strip_text', 'Каждый букет собираем утром — и фотографируем до отправки')); ?>
  <?php if ($catalogStripText !== ''): ?>
  <section class="fc-catalog-strip" aria-label="О сборке букетов">
    <?php if (is_file(BASE_PATH . '/img/editorial/rose-linen.jpg')): ?>
    <?php /* W104-ζ (C3-D3): full-bleed-полоса — webp-конвейер вместо jpg-оригинала
           (480/768 GD-превью из кэша hero-конвейера + webp-оригинал 1344w).
           W105-7fix1 (арт-критик 7-a P2): rose-linen.jpg 1344×768 — полоса больше
           НЕ рециклит старый hero-макро (второе применение одного кадра на
           первом экране); негативное пространство справа — под scrim-текст. */ ?>
    <?php
    $__csRoot = 'img/editorial/rose-linen.jpg';
    $__csWebp = 'img/editorial/rose-linen.webp';
    $__csSrcset = [];
    foreach ([480, 768] as $__csW) {
        $__csT = hero_img_size($__csRoot, $__csW);
        if ($__csT !== '') { $__csSrcset[] = $__csT . ' ' . $__csW . 'w'; }
    }
    if (is_file(BASE_PATH . '/' . $__csWebp)) { $__csSrcset[] = '/' . $__csWebp . ' 1344w'; }
    ?>
    <picture>
      <?php if ($__csSrcset !== []): ?><source type="image/webp" srcset="<?= e(implode(', ', $__csSrcset)) ?>" sizes="100vw"><?php endif; ?>
      <img class="fc-catalog-strip__img" src="/img/editorial/rose-linen.jpg" alt="Одна роза на льняной ткани" loading="lazy" decoding="async">
    </picture>
    <?php endif; ?>
    <div class="wrap fc-catalog-strip__inner">
      <p class="fc-catalog-strip__text reveal"><?= e($catalogStripText) ?></p>
    </div>
  </section>
  <?php endif; ?>
  <?php /* «ДОПОЛНИТЕ БУКЕТ» (5cv): сопутствующие товары show_in_upsell.
       W96-fix1 (F11): секция имеет смысл от ≥2 товаров — одиночная карточка
       в карусели выглядит пусто; жёсткий фильтр, чтобы не зависеть от сида.
       W106 (B2): перенесён ЗА каталог — до каталога не больше двух товарных
       секций; дозаказ логичен после выбора букета (и рядом с формой заказа). */ ?>
  <?php if ($featCarousels && count($addonProducts) >= 2):
      /* W106-C1 (P0-4): тихая строка открытки — переезд из hero-промо;
         владелец может задать свою подпись через section_addons_sub
         (пусто в БД → дефолт про открытку). P0-5: секция — тонкий tint. */
      $addonsSub = trim((string)setting('section_addons_sub', ''));
      if ($addonsSub === '') {
          $addonsSub = 'Открытка с вашим текстом от руки — в каждый букет бесплатно';
      }
      render_fc_row(
      setting('section_addons_title', 'Дополните букет'),
      $addonsSub, $addonProducts, $cardCtx, '', '', '',
      setting('section_addons_eyebrow', 'К букету|и без повода'),
      fc_next_numeral(),
      /* W104-γ (C2-D2): дополнения — компакт-рельс (мини-карточки 96px) */
      'compact',
      /* W106-C1 (P0-5): тонкий тинт — дыхание между крем-каталогом и белыми отзывами */
      'soft');
  endif; ?>

  <?php /* W106 (B2, задача 8): «О НАС ГОВОРЯТ» — соцдоказательство из БД (отзывы
       сидированы волной B1: getReviews/getRatingAggregate в includes/db.php).
       Агрегат — живой (число/среднее из таблицы reviews), источники — из данных;
       0 отзывов — секция не рендерится. Дизайн editorial: цитаты с тонкими
       разделителями (не карточки-коробки), Playfair-курсив, звёзды amber. */ ?>
  <?php
  $reviewsList = getReviews(null, 4);
  $reviewsAgg = getRatingAggregate();
  /* W106-C1: выборка источников (source) больше не рендерится — честная
     подпись «отзыв после доставки» вместо «Яндекс Карты/2ГИС» (флорист P1:
     внешних профилей нет, бейдж-бутафория). Запрос убран за ненадобностью. */
  $reviewsMonths = ['01'=>'января','02'=>'февраля','03'=>'марта','04'=>'апреля','05'=>'мая','06'=>'июня','07'=>'июля','08'=>'августа','09'=>'сентября','10'=>'октября','11'=>'ноября','12'=>'декабря'];
  ?>
  <?php if ($reviewsList !== [] && $reviewsAgg['count'] > 0): ?>
  <section class="fc-section fc-reviews" id="reviews">
    <div class="wrap">
      <div class="fc-row__head">
        <div class="fc-row__heading">
          <?php /* G-g2 (редактор P1): eyebrow «О нас|говорят» + H2 «О нас
                 говорят» — тавтология; eyebrow теперь называет тип контента
                 («ОТЗЫВЫ покупателей»), H2 остаётся живым агрегатом. */ ?>
          <?php render_fc_eyebrow(setting('reviews_eyebrow', 'Отзывы|покупателей')); ?>
          <h2 class="fc-row__title"><?= e(setting('reviews_title', 'Почему нам доверяют')) ?></h2>
        </div>
        <p class="fc-reviews__agg">
          <span class="fc-reviews__agg-num"><?= e(str_replace('.', ',', (string)round($reviewsAgg['avg'], 1))) ?></span>
          <?php
          /* Русская форма «отзыв/отзыва/отзывов» по числу (12 → отзывов, 23 → отзыва, 1 → отзыв) */
          $rvN = (int)$reviewsAgg['count'];
          $rvWord = ($rvN % 10 === 1 && $rvN % 100 !== 11) ? 'отзыв'
              : (($rvN % 10 >= 2 && $rvN % 10 <= 4 && ($rvN % 100 < 12 || $rvN % 100 > 14)) ? 'отзыва' : 'отзывов');
          ?>
          <?php /* W106-C1 (флорист P1 «бутафория»): строка источников
                 «— 2ГИС, Яндекс Карты» УДАЛЕНА — внешних профилей нет,
                 честная подпись — та же, что у бейджа hero. */ ?>
          <span class="fc-reviews__agg-rest">из 5 · <?= $rvN ?> <?= e($rvWord) ?> покупателей</span>
        </p>
      </div>
      <div class="fc-reviews__grid">
        <?php foreach ($reviewsList as $rvRow): ?>
        <?php
        $rvRating = max(1, min(5, (int)$rvRow['rating']));
        $rvDate = '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$rvRow['created_at'], $rvDm)) {
            $rvDate = ((int)$rvDm[3]) . ' ' . ($reviewsMonths[$rvDm[2]] ?? '');
        }
        ?>
        <figure class="fc-review reveal">
          <p class="fc-review__stars" aria-label="Оценка: <?= $rvRating ?> из 5"><?= str_repeat('<span aria-hidden="true">★</span>', $rvRating) . str_repeat('<span class="fc-review__star--off" aria-hidden="true">★</span>', 5 - $rvRating) ?></p>
          <blockquote class="fc-review__text"><?= e($rvRow['text']) ?></blockquote>
          <figcaption class="fc-review__meta">
            <span class="fc-review__author"><?= e($rvRow['author']) ?></span>
            <?php if ($rvDate !== ''): ?><time class="fc-review__date" datetime="<?= e($rvRow['created_at']) ?>"><?= e($rvDate) ?></time><?php endif; ?>
            <?php /* W106-C1 (флорист P1): источник из БД (Яндекс Карты/2ГИС) НЕ
                   показываем — внешних профилей нет, это бутафория. Тихая
                   честная подпись: «отзыв после доставки». БД не тронута —
                   маппинг только на рендере. */ ?>
            <span class="fc-review__source">отзыв после доставки</span>
          </figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* W96 (5cv): секция how-it-works убрана — шаги остаются в настройках, но не выводятся. */ ?>

  <?php /* ЦВЕТЫ ПО ПОВОДУ (5cv): плитки-чипы с фото/пастельными фонами.
     W106-C1 (P0-5): поводы — тонкий tint (после белых отзывов, перед
     тёплой формой заказа) — сетка не сливается с соседями.
     W106-E1 (корректор P0): комментарий был HTML-блоком с потерянным
     открывающим <!-- — хвост «…сетка не сливается с соседями. -->»
     рендерился видимым текстом на странице. Теперь весь PHP-комментарий. */ ?>
  <?php if ($occTiles !== []): ?>
  <section class="fc-section fc-section--soft" id="occasions">
    <div class="wrap">
      <?php /* W104: шапка поводов — единый компонент (eyebrow + нумерал за H2) */ ?>
      <div class="fc-row__head">
        <div class="fc-row__heading" data-numeral="<?= e(fc_next_numeral()) ?>">
        <?php render_fc_eyebrow(setting('occasions_eyebrow', 'Повод|найти просто')); ?>
        <h2 class="fc-row__title"><?= e(setting('occasions_title', 'Цветы по поводам')) ?></h2>
        </div>
      </div>
      <div class="fc-occasions">
        <?php /* W103 (F1): плитки — фото с duotone-эффектом (CSS) или типографические
               ink-плитки с amber-индексом (вместо пастелевых заглушек с SVG-цветком) */ ?>
        <?php foreach ($occTiles as $tIdx => $t): ?>
        <a class="fc-occasion<?= $t['photo'] !== '' ? ' fc-occasion--photo' : ' fc-occasion--pastel' ?>" href="/occasion/<?= e(rawurlencode($t['slug'])) ?>">
          <?php if ($t['photo'] !== ''): ?>
            <?php /* W96-fix3a (T6) → W97-fixB2 (B2-6): alt="" — имя плитки несёт ссылка
                   (текст дублировался для скринридера); длинное имя — в title не нужно,
                   фото внутри ссылки целиком */ ?>
            <?php /* W102 (perf): плитки грузили JPG-оригиналы (~589КБ) — включаем
                   тот же webp-конвейер, что у карточек (thumbs 400/600 + оригинал).
                   W104-ζ: + webp-оригинал {w}w — ретина-плитка (260px@3x = 780)
                   берёт полный файл вместо 600w-превью */ ?>
            <?php
            $__ocImg = (string)$t['photo'];
            /* W104-ζ: фикс пути превью — раньше в thumbs-URL попадал весь путь
               '/img/products/…' (urlencode → '%2Fimg%2F…'), is_file был всегда
               false и плитки грузили ЖЕЛЕЗНЫЙ jpg-оригинал — W102-«фикс» не работал */
            $__ocBase = rawurldecode(basename($__ocImg));
            $__ocThumb = preg_replace('/\.(jpe?g|png)$/i', '', $__ocBase);
            $__ocThumb400 = '/img/products/thumbs/' . rawurlencode($__ocThumb) . '-400.webp';
            $__ocThumb600 = '/img/products/thumbs/' . rawurlencode($__ocThumb) . '-600.webp';
            $__ocHas400 = is_file(BASE_PATH . parse_url($__ocThumb400, PHP_URL_PATH));
            $__ocHas600 = is_file(BASE_PATH . parse_url($__ocThumb600, PHP_URL_PATH));
            $__ocParts = [];
            if ($__ocHas400) { $__ocParts[] = $__ocThumb400 . ' 400w'; }
            if ($__ocHas600) { $__ocParts[] = $__ocThumb600 . ' 600w'; }
            $__ocWebp = '/img/products/' . rawurlencode(preg_replace('/\.(jpe?g|png)$/i', '.webp', $__ocBase));
            if (is_file(BASE_PATH . parse_url($__ocWebp, PHP_URL_PATH))) {
                $__ocDim = @getimagesize(BASE_PATH . parse_url($__ocWebp, PHP_URL_PATH));
                if ($__ocDim !== false) { $__ocParts[] = $__ocWebp . ' ' . (int)$__ocDim[0] . 'w'; }
            }
            ?>
            <picture>
              <?php if ($__ocParts !== []): ?>
              <source type="image/webp" srcset="<?= e(implode(', ', $__ocParts)) ?>" sizes="(max-width:899px) 44vw, 280px">
              <?php endif; ?>
              <img class="fc-occasion__img" src="<?= e($__ocImg) ?>" alt="<?= e($t['photo_alt'] !== '' ? $t['photo_alt'] : $t['title']) ?>" loading="lazy" decoding="async">
            </picture>
          <?php else: ?>
            <?php /* W103 (F1): типографическая плитка без фото — ink-фон, amber-индекс,
                   Playfair-курсив в подписи (стили five.css; старый SVG-цветок убран) */ ?>
            <span class="fc-occasion__index" aria-hidden="true"><?= sprintf('%02d', $tIdx + 1) ?></span>
          <?php endif; ?>
          <span class="fc-occasion__label"><?= e($t['title']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* ФОРМА ЗАКАЗА — partial (W106-E1, Нильсен P0 «чекаут заперт в лендинге»):
     разметка извлечена в partials/order-form.php 1:1 (id полей — контракт
     js/order-form.js — не менялись). Главная подключает partial на прежнем
     месте: поведение страницы не меняется. Тот же partial рендерит форму
     на /checkout.php. */ ?>
  <?php require __DIR__ . '/partials/order-form.php'; ?>

  <?php /* НАШИ МАГАЗИНЫ (5cv): карточки адресов. */ ?>
  <?php if ($featStores): ?>
  <section class="fc-section">
    <div class="wrap">
      <div class="fc-row__head">
        <h2 class="fc-row__title"><?= e(setting('stores_title', 'Наши магазины в Петербурге')) ?></h2>
        <?php /* W106-E1 (корректор P0): SLA-унификация — легаси-сид «готов в течение
               дня» (совпадает со старым дефолтом) гейтится как дефолт; кастом
               владельца в БД по-прежнему в силе. */ ?>
        <?php $storesSubDb = trim((string)setting('stores_sub', '')); ?>
        <?php if ($storesSubDb === '' || $storesSubDb === 'Заберите сами или закажите доставку — букет будет готов в течение дня'): ?>
        <p class="fc-row__sub">Заберите сами или закажите доставку — соберём букет за 1–2 часа</p>
        <?php else: ?>
        <p class="fc-row__sub"><?= e($storesSubDb) ?></p>
        <?php endif; ?>
      </div>
      <div class="fc-stores">
        <?php foreach ($storesList as $st): ?>
        <div class="fc-store">
          <span class="fc-store__pin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg></span>
          <h3 class="fc-store__title"><?= e($st['title']) ?></h3>
          <?php if ($st['text'] !== ''): ?><p class="fc-store__text"><?= e($st['text']) ?></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* SEO-ТЕКСТ (5cv): sanitize_rich_text разрешает только <a>; абзацы — через \n\n → <p> (стилизует .fc-seo p).
       W103 (6-b, критик-5а P0 «низ главной»): колофон-стиль — тихий caps-заголовок,
       первые два абзаца снаружи, остальной текст в <details> (в HTML остаётся весь
       текст — SEO не страдает); summary — Playfair-курсив. */ ?>
  <?php if ($featSeotext): ?>
  <section class="fc-section">
    <div class="wrap">
      <div class="fc-seo">
        <h2 class="fc-seo__title"><?= e(setting('seo_text_title', 'Доставка цветов в Санкт-Петербурге')) ?></h2>
        <?php
        /* Абзацы разделяем пустой строкой; одиночные \n внутри — <br> (nl2br).
        Разметка уже прошла sanitize_rich_text (остаются только whitelisted <a>). */
        $seoParas = array_values(array_filter(array_map('trim',
            preg_split('/\n{2,}/u', sanitize_rich_text(setting('seo_text_body', $seoTextDefault), 5000))),
            static fn (string $s): bool => $s !== ''));
        $seoLead = array_slice($seoParas, 0, 2);
        $seoRest = array_slice($seoParas, 2);
        ?>
        <?php foreach ($seoLead as $seoPara): ?>
        <p><?= nl2br($seoPara) ?></p>
        <?php endforeach; ?>
        <?php if ($seoRest !== []): ?>
        <details class="fc-seo__more">
          <summary><?= e(setting('seo_more_summary', 'О доставке цветов по Санкт-Петербургу')) ?></summary>
          <?php foreach ($seoRest as $seoPara): ?>
          <p><?= nl2br($seoPara) ?></p>
          <?php endforeach; ?>
        </details>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* W99-fixG (G17): третья editorial-врезка ПЕРЕД FAQ — каденция финала
         страницы, текст/гейт тот же паттерн (setting('editorial_text_3'),
         trim==='' скрывает; guard не даст задвоить) */ ?>
  <?php render_fc_editorial(3); ?>

  <?php /* FAQ (критерий 13, SEO FAQPage — паттерн Цветовика): реальные вопросы покупателей. Отключаем (критерий 16). */ ?>
  <?php if ($featFaq): ?>
  <section class="fc-section section--faq" id="faq">
    <?php /* W96-fix2 (F4): FAQ-колонка шире (760→880) — вопрос-ответ читается
           без «узкой газетной» колонки; длинные вопросы не переносятся в 3 строки */ ?>
    <div class="wrap" style="max-width:880px">
      <?php /* W104-α (M): FAQ — завершающий нумерал оглавления главной (09);
             обёртка fc-row__heading включает тот же oversize-индекс за H2.
             W105 (4-a): обёртка fc-row__head — тот же механизм отступа
             «H2 → контент», что у остальных секций (margin-bottom 26px);
             без неё FAQ-заголовок прилипал к первому вопросу. */ ?>
      <div class="fc-row__head">
      <div class="fc-row__heading" data-numeral="<?= e(fc_next_numeral()) ?>">
      <h2 class="section-title"><?= e(setting('faq_title', 'Частые вопросы')) ?></h2>
      </div>
      </div>
      <?php
      /* FAQ редактируется из админки (критерий 16): 4 пары вопрос-ответ.
         Непустые пары рендерятся; JSON-LD строится из тех же полей — синхрон с видимым текстом. */
      $faqItems = [];
      /* W97-fixB2 (B2-9): честные дефолты ответов о доставке/оплате (в БД ключи уже
         сидированы старым текстом — значения обновит guard-миграция другой волны;
         здесь дефолты для витрин без записей в settings). */
      $faqAnswerDefaults = [
          1 => 'Зависит от района: 300–500 ₽ по СПб, самовывоз бесплатный. Точная сумма сразу видна при оформлении заказа',
          4 => 'Наличными или картой курьеру при получении. Онлайн-оплата — сообщим, когда появится',
      ];
      for ($i = 1; $i <= 4; $i++) {
          $q = trim(setting("faq_q{$i}", ''));
          $a = trim(setting("faq_a{$i}", $faqAnswerDefaults[$i] ?? ''));
          if ($q !== '' && $a !== '') { $faqItems[] = ['q' => $q, 'a' => $a]; }
      }
      ?>
      <div class="fc-faq">
      <?php foreach ($faqItems as $f): ?>
      <details class="faq-item">
        <summary class="faq-item__q"><?= e($f['q']) ?></summary>
        <?php /* W103 (6-b, M8): обёртка для плавного раскрытия 280мс —
               grid-template-rows 0fr→1fr (стили five.css); текст целиком
               остаётся в HTML/JSON-LD как раньше */ ?>
        <div class="faq-item__a-wrap"><p class="faq-item__a"><?= e($f['a']) ?></p></div>
      </details>
      <?php endforeach; ?>
      </div>
      <?php if ($faqItems !== []): ?>
      <script type="application/ld+json">
      <?= json_encode([
          '@context' => 'https://schema.org',
          '@type' => 'FAQPage',
          'mainEntity' => array_map(static fn (array $f): array => [
              '@type' => 'Question',
              'name' => $f['q'],
              'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
          ], $faqItems),
      ], JSON_UNESCAPED_UNICODE) ?>
      </script>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php /* ЖУРНАЛ (5cv): карточки статей; по умолчанию выключен — контента нет. */ ?>
  <?php if ($journalList !== []): ?>
  <section class="fc-section fc-journal">
    <div class="wrap">
      <div class="fc-row__head">
        <h2 class="fc-row__title"><?= e(setting('journal_title', 'Журнал Nilov Flowers')) ?></h2>
        <p class="fc-row__sub"><?= e(setting('journal_sub', 'Заметки о цветах и заботе о них')) ?></p>
      </div>
      <div class="fc-journal__grid">
        <?php foreach ($journalList as $j): ?>
        <article class="fc-journal__card">
          <?php if ($j['image'] !== ''): ?><div class="fc-journal__media"><img src="/img/uploads/<?= e(rawurlencode($j['image'])) ?>" alt="<?= e($j['title']) ?>" loading="lazy" decoding="async"></div><?php endif; ?>
          <h3 class="fc-journal__title"><?= e($j['title']) ?></h3>
          <?php if ($j['text'] !== ''): ?><p class="fc-journal__text"><?= e($j['text']) ?></p><?php endif; ?>
          <?php if ($j['link'] !== ''): ?><a class="fc-journal__link" href="<?= e(safe_url($j['link'])) ?>">Читать →</a><?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* W96 (5cv): блок отзывов Яндекс Карт убран по требованию заказчика (отзывов на сайте нет). */ ?>
</main>

<?php /* W103 (F1): тэглайн футера — крупный Playfair-курсив над колонками.
   Рендерим ПЕРЕД footer.php (partial вне скоупа волны F1): ink-фон блока
   сливается с .site-footer в единую тёмную зону. */ ?>
<?php $footerTagline = trim(setting('footer_tagline', 'Свежие цветы — с утра к вашей двери')); ?>
<?php if ($footerTagline !== ''): ?>
<section class="fc-footer-tagline" aria-label="О магазине">
  <div class="wrap"><p class="fc-footer-tagline__text reveal"><?= e($footerTagline) ?></p></div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
