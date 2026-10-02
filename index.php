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
        ? ' data-tags="' . e(mb_strtolower(str_replace('ё', 'е', preg_replace('/\s+/u', ' ', trim((string)$p['tags']))) ?? ''), 'UTF-8') . '"'
        : '';
    $img2 = trim((string)($p['image2'] ?? ''));
    $img2Url = '';
    if ($img2 !== '' && is_file(IMG_PRODUCTS_DIR . '/' . $img2)) {
        $img2Url = '/img/products/' . rawurlencode($img2);
    }
    $comp = trim((string)($p['composition'] ?? ''));
    $sizeText = trim((string)($p['size_text'] ?? ''));
    /* S6: состав и размер — скрытые носители данных для Quick View
       (js/quickview.js читает их из DOM карточки; в карточке не видны). */
    $sizeIsDia = mb_strpos($sizeText, '⌀') === 0;
    $sizeClean = trim((string)preg_replace('/^[⌀↕]\s*/u', '', $sizeText));
    /* S6: сплит — тихая серая строка «Сплит: от N ₽ × 4» (splitLabel —
       util.php, настройка split_label; пилюля-виджет убрана по ТЗ) */
    $splitText = splitLabel($price);
    /* S6: рейтинг карточки — живой агрегат отзывов из БД (по товару,
       фолбэк — общий агрегат магазина), «★ 5.0 (28)» жёлтой звездой */
    global $productRatings;
    $cardRating = $productRatings[(int)$p['id']] ?? null;
    if ($cardRating === null) {
        $shopAgg = getRatingAggregate();
        if ($shopAgg['count'] > 0) {
            $cardRating = $shopAgg;
        }
    }
    /* S6: строка доставки под фото («Сегодня за 1–2 часа», настройка
       card_delivery_text; пусто — не печатаем). */
    $deliveryText = trim((string)setting('card_delivery_text', 'Сегодня за 1–2 часа'));
    /* S6: подпись ссылки 1-клика — полный текст настройки. */
    $oneclickFull = trim((string)setting('card_btn_oneclick', 'Купить в 1 клик'));
    /* S8-инцидент «серые прямоугольники» на проде: связка <picture><source
       srcset sizes> + loading="lazy" — браузер НЕ ЗАПРАШИВАЕТ файлы
       (naturalWidth=0, ноль запросов /img/products/ в network), клиенты
       видят серые плашки. Эмпирически подтверждено на проде: plain
       <img srcset sizes lazy> загружается, тот же файл внутри <picture> — нет.
       ФИКС (fail-closed): БЕЗ <picture>, webp-srcset прямо на <img>,
       loading="eager" + decoding="async" — браузер обязан запросить файл
       немедленно, никакой ленивой механики на карточках. */ ?>
        <article class="product-card"<?= $isCarousel
            ? ''
            : ' data-category-id="' . (int)($p['category_id'] ?? 0) . '" data-price="' . (int)$price . '" data-hit="' . (int)($p['is_hit'] ?? 0) . '" data-premium="' . (int)($p['is_premium'] ?? 0) . '" data-search="' . e($searchIndex) . '"' . $upsellAttr . $tagsAttr ?>>
          <div class="product-card__media">
          <?php /* фото 4:5, r12; БЕЗ <picture> и БЕЗ lazy (S8-инцидент):
                 srcset/sizes прямо на <img>, eager — файл запрашивается
                 сразу, карточка не зависит ни от какого JS */ ?>
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>" aria-hidden="true" tabindex="-1">
              <?php if ($img !== ''): ?>
                <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>"<?php if ($srcset !== ''): ?> srcset="<?= e($srcset) ?>"<?php if ($thumb !== ''): ?> sizes="<?= e($sizes) ?>"<?php endif; ?><?php endif; ?> loading="eager" decoding="async">
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--ink-muted)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
              <?php if ($img2Url !== ''): ?>
                <img class="product-card__img2" src="<?= e($img2Url) ?>" alt="" aria-hidden="true" loading="eager" decoding="async">
              <?php endif; ?>
            </a>
            <?php /* на фото ТОЛЬКО жёлтый «Хит» (#FFB800) и скидка (белая
                   плашка); «Премиум» — редкий третий, чёрный. */ ?>
            <?php if ($isSale): $offPct = (int)$p['price'] > 0 ? (int)round((1 - $price / (int)$p['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= $offPct > 0 ? '&#8722;' . (int)$offPct . '%' : e(setting('badge_sale_text', 'Скидка')) ?></span><?php endif; ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
          </div>
          <div class="product-card__body">
            <?php if (!empty($ctx['feature'])): ?>
            <span class="product-card__feature-label"><?= e(setting('feature_card_label', 'Выбор флориста')) ?></span>
            <?php endif; ?>
            <?php /* S7 СТРОКА 1 (ЦЕНА + СПЛИТ): крупная жирная цена чёрным
                   (22–24px, font-bold, tabular-nums), при скидке рядом —
                   зачёркнутая старая; под ней компактный серый бейдж
                   «Сплит 860 ₽ × 4» (splitLabel, настройка split_label). */ ?>
            <p class="product-card__price">
              <?php if ($isSale): ?>
                <span class="product-card__price--discount"><?= formatPrice($price) ?></span>
                <span class="product-card__price--old"><?= formatPrice((int)$p['price']) ?></span>
              <?php else: ?>
                <?= formatPrice($price) ?>
              <?php endif; ?>
            </p>
            <?php if ($splitText !== ''): ?>
            <p class="product-card__split"><span class="product-card__split-badge"><?= e($splitText) ?></span></p>
            <?php endif; ?>
            <?php /* S7 СТРОКА 2 (НАЗВАНИЕ): 14px, чёрное, medium, РОВНО 2 строки
                   фиксированной высоты (S9 ДЕФЕКТ 1) */ ?>
            <a class="product-card__name" href="<?= e($link) ?>"><?= e($p['name']) ?></a>
            <?php /* S7 СТРОКА 3 (ДОВЕРИЕ И СРОК): одна строка через точку —
                   «⚡ За 1–2 ч · ★ 5.0 (24)», серый неброский текст #767676
                   (звезда — жёлтая #FFB800, единственный акцент).
                   S9 ДЕФЕКТ 1: строка живёт СРАЗУ ПОД названием фиксированной
                   высоты — её Y одинаков во всех карточках ряда; перенос на
                   2 строки не двигает кнопки (их прижимает к низу
                   .product-card__bottom{margin-top:auto}). */ ?>
            <?php if ($deliveryText !== '' || $cardRating !== null): ?>
            <p class="product-card__meta">
              <?php if ($deliveryText !== ''): ?><span class="product-card__meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg><?= e($deliveryText) ?></span><?php endif; ?>
              <?php if ($deliveryText !== '' && $cardRating !== null): ?><span class="product-card__meta-sep" aria-hidden="true">·</span><?php endif; ?>
              <?php if ($cardRating !== null): ?><span class="product-card__meta-item product-card__meta-item--rating"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><?= e(sprintf('%.1f', (float)$cardRating['avg'])) ?>&nbsp;<span class="product-card__rating-count">(<?= (int)$cardRating['count'] ?>)</span></span><?php endif; ?>
            </p>
            <?php endif; ?>
            <?php /* скрытые данные для Quick View (не отображаются) */ ?>
            <?php if ($comp !== ''): ?><p class="product-card__comp" hidden><?= e($comp) ?></p><?php endif; ?>
            <?php if ($sizeClean !== ''): ?>
            <p class="product-card__size" hidden><?php if ($sizeIsDia): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M6.5 17.5 17.5 6.5"/></svg><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/></svg><?php endif; ?><span class="product-card__size-text"><?= e($sizeClean) ?></span></p>
            <?php endif; ?>
            <?php /* S9 ДЕФЕКТ 1: блок кнопок прижат к низу контейнером
                   margin-top:auto;width:100% — во всех карточках ряда кнопки
                   стоят на ОДНОЙ горизонтальной линии пиксель-в-пиксель. */ ?>
            <div class="product-card__bottom">
              <?php /* S9 ДЕФЕКТ 2: ОДНА элегантная кнопка «В корзину» (40px,
                     r10, #18181B) + неброская текстовая ссылка «Купить в 1
                     клик» (12px, #71717A, hover — подчёркивание) — карточка
                     в 2 раза легче, как на 5cv.ru. */ ?>
              <div class="product-card__actions">
                <button type="button" class="product-card__cta" data-order-cta
                  data-product-id="<?= (int)$p['id'] ?>"
                  data-product-name="<?= e($p['name']) ?>"
                  data-product-price-raw="<?= $price ?>"
                  data-product-image="<?= e($img) ?>"
                  aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="Добавить в корзину"><?= e(setting('card_btn_cart', 'В корзину')) ?></button>
                <button type="button" class="btn-oneclick-link" data-oneclick
                  data-product-id="<?= (int)$p['id'] ?>"
                  data-product-name="<?= e($p['name']) ?>"
                  data-product-price-raw="<?= $price ?>"
                  data-product-image="<?= e($img) ?>"
                  aria-label="<?= e($oneclickFull) ?>: <?= e($p['name']) ?>" title="<?= e($oneclickFull) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg><span><?= e($oneclickFull) ?></span></button>
              </div>
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
               S11: oversize-нумерал (data-numeral, гигантская цифра «01» на
               фоне) УДАЛЁН — editorial-макулатура по требованию владельца. */ ?>
        <div class="fc-row__heading">
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

/* S6: карта рейтингов по товарам — живой агрегат отзывов из БД
   (render_product_card печатает «★ 5.0 (28)» жёлтой звездой;
   фолбэк для товара без отзывов — общий агрегат магазина). */
$productRatings = [];
try {
    foreach ($pdo->query('SELECT product_id, COUNT(*) AS c, ROUND(AVG(rating), 1) AS a FROM reviews GROUP BY product_id') as $rr) {
        $productRatings[(int)$rr['product_id']] = ['count' => (int)$rr['c'], 'avg' => (float)$rr['a']];
    }
} catch (Throwable $e) {
    /* старая БД без таблицы reviews — тихо деградируем к общему агрегату */
}

/* S7: ТОВАРНЫЕ ПОЛКИ (коммерческая структура 5cv.ru) — каждый товар ровно
   один раз по трём секциям каталога:
     полка 1 «Хиты продаж»        — is_hit=1 (первые 4; фолбэк при пусто —
                                    первые 4 каталога, как прежний hitProducts);
     полка 2 «Авторские и розы»   — не-хит и не-премиум букеты, розы-тег
                                    приоритетнее (до 6 позиций);
     полка 3 «Премиум и коробки»  — премиум (дороже — раньше) + остаток
                                    каталога + сладости-допы в конце.
   Сладости: тег «сладост…» (клубника в шоколаде) — подарочные допы, в конце
   третьей полки (в фильтрах участвуют наравне со всеми). */
$nfIsSweet = static function (array $p): bool {
    return mb_strpos(mb_strtolower(trim((string)($p['tags'] ?? '')), 'UTF-8'), 'сладос', 0, 'UTF-8') !== false;
};
$nfHasRosy = static function (array $p): bool {
    foreach (preg_split('/[\s,]+/u', mb_strtolower(trim((string)($p['tags'] ?? '')), 'UTF-8')) ?: [] as $tw) {
        if ($tw !== '' && mb_strpos($tw, 'роз', 0, 'UTF-8') === 0) { return true; }
    }
    return false;
};
$shelfHits = [];
$shelfAuthor = [];
$shelfPremium = [];
$shelfUsed = [];
/* полка 1: хиты (до 4); фолбэк — первые 4 активных */
$hitCandidates = array_values(array_filter($products, static fn (array $p): bool => (int)($p['is_hit'] ?? 0) === 1));
if ($hitCandidates === []) {
    $hitCandidates = array_slice($products, 0, 4);
}
foreach (array_slice($hitCandidates, 0, 4) as $p) {
    $shelfHits[] = $p;
    $shelfUsed[(int)$p['id']] = true;
}
/* полка 2: не-хит, не-премиум, не-сладости; розы-тег приоритетнее (до 6) */
$authorCandidates = [];
foreach ($products as $i => $p) {
    if (isset($shelfUsed[(int)$p['id']]) || (int)($p['is_premium'] ?? 0) === 1 || $nfIsSweet($p)) { continue; }
    $authorCandidates[] = ['i' => $i, 'rosy' => $nfHasRosy($p) ? 0 : 1, 'p' => $p];
}
usort($authorCandidates, static function (array $a, array $b): int {
    return $a['rosy'] <=> $b['rosy'] ?: $a['i'] <=> $b['i'];
});
foreach (array_slice($authorCandidates, 0, 6) as $row) {
    $shelfAuthor[] = $row['p'];
    $shelfUsed[(int)$row['p']['id']] = true;
}
/* полка 3: премиум (дороже — раньше), затем обычный остаток, сладости — в конец */
$premiumCandidates = [];
$plainCandidates = [];
$sweetCandidates = [];
foreach ($products as $p) {
    if (isset($shelfUsed[(int)$p['id']])) { continue; }
    if ((int)($p['is_premium'] ?? 0) === 1) { $premiumCandidates[] = $p; }
    elseif ($nfIsSweet($p)) { $sweetCandidates[] = $p; }
    else { $plainCandidates[] = $p; }
}
usort($premiumCandidates, static fn (array $a, array $b): int => productPrice($b) <=> productPrice($a));
$shelfPremium = array_merge($premiumCandidates, $plainCandidates, $sweetCandidates);

/* S8: ЕДИНАЯ ВИТРИНА — один плоский массив вместо трёх полок: хиты →
   авторские/розы → премиум/остаток/сладости (коммерческий ритм без
   заголовков-разделителей; каждый товар ровно один раз). */
$gridAll = array_merge($shelfHits, $shelfAuthor, $shelfPremium);

/* S9: матчинг тегов — ВСЕ слова чипа должны найтись в тегах карточки
   (по стему первых 4 букв, как однословный). «В коробках» матчит
   «в шляпных коробках», «Подарки» — «подарок девушке».
   ИДЕНТИЧНО js/catalog-filter.js nfTagMatch (контракт!).
   Определён ДО плашек бюджета: счётчики «Монобукеты»/«Хиты и подарки»
   и чипы ленты используют один и тот же матчер. */
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

/* счётчики плашек бюджета: правила ИДЕНТИЧНЫ JS-фильтру catalog-filter.js
   chipMatch() — цифра на плашке обязана совпадать с результатом клика:
   low ≤ N (data-max, включительно); от N — price ≥ N (data-min=N−1,
   т.к. JS трактует data-min ИСКЛЮЧИТЕЛЬНО — «от 3 500» включает 3 500);
   монобукеты/подарки — тег-матч по стему (настройки-теги плашек);
   «Хиты и подарки» = is_hit ИЛИ тег «Подарки». */
$tileMonoTag = trim((string)setting('budget_tile_mono_tag', 'Монобукеты'));
$tileGiftsTag = trim((string)setting('budget_tile_gifts_tag', 'Подарки'));
$cntHit = 0; $cntLow = 0; $cntFrom = 0; $cntMono = 0; $cntGift = 0;
foreach ($products as $pc) {
    $pprice = productPrice($pc);
    if ((int)($pc['is_hit'] ?? 0) === 1) { $cntHit++; }
    if ($pprice <= $chipsN) { $cntLow++; }
    if ($pprice >= $chipsN) { $cntFrom++; }
    if ($tileMonoTag !== '' && $nfTagMatch($tileMonoTag, $pc)) { $cntMono++; }
    if ((int)($pc['is_hit'] ?? 0) === 1 || ($tileGiftsTag !== '' && $nfTagMatch($tileGiftsTag, $pc))) { $cntGift++; }
}
$pluralBuket = static function (int $n): string {
    $n10 = $n % 10; $n100 = $n % 100;
    if ($n10 === 1 && $n100 !== 11) return 'букет';
    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14)) return 'букета';
    return 'букетов';
};

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
        <div class="fc-row__heading">
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

/* W104-α (M): СКВОЗНАЯ НУМЕРАЦИЯ СЕКЦИЙ УПРАЗДНЕНА (S11): oversize-цифры
   «01/02/…» на фоне — editorial-мусор, владелец приказал удалить полностью
   (гигантская «01» висела в блоке FAQ поверх текста). */

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

/* ---- S10 ДЕФЕКТ 2: ПОВОДЫ-КАРТОЧКИ (белые карточки с эмодзи + фильтр каталога) ----
   Серые градиентные плиты заменены тремя элегантными белыми карточками.
   Тексты/эмодзи/теги — настройки occ_card_N_* (админка). Подборка живая:
     (1) теги карточки (occ_card_N_tags, матчатся $nfTagMatch по стему);
     (2) ∪ product_ids повода (occ_card_N_slug — страница повода из админки);
     (3) пусто → премиум-букеты; (4) пусто → самые дорогие букеты (топ-4):
   карточка ВСЕГДА ведёт к непустому набору — пустых кликов не бывает.
   Клик = чип data-chip="ids" (js/catalog-filter.js) + скролл к #catalog
   (js/five.js — повод-карточки входят в общий массив чипов). */
$occRows = [];
try {
    $occRows = $pdo->query('SELECT slug, product_ids FROM occasions WHERE active = 1')->fetchAll();
} catch (Throwable $e) {
    $occRows = [];
}
$occSlugIds = [];
foreach ($occRows as $o) {
    $occSlugIds[(string)$o['slug']] = array_values(array_filter(array_map('intval', array_map('trim', explode(',', (string)$o['product_ids'])))));
}
/* премиум-фолбэк (3) и топ-4 по цене (4) — для карточек без живых тегов/поводов */
$occPremiumIds = [];
foreach ($gridAll as $p) {
    if ((int)($p['is_premium'] ?? 0) === 1) { $occPremiumIds[] = (int)$p['id']; }
}
$occTopPrice = $gridAll;
usort($occTopPrice, static fn (array $a, array $b): int => productPrice($b) <=> productPrice($a));
$occTopIds = array_slice(array_map(static fn (array $p): int => (int)$p['id'], $occTopPrice), 0, 4);
$gridIdSet = [];
foreach ($gridAll as $p) { $gridIdSet[(int)$p['id']] = true; }

$occCards = [];
if ($featOccasions) {
    $occDefaults = [
        1 => ['🎂', 'День рождения', 'Яркие и праздничные букеты', 'день рождения, подарок, праздник', 'buket-na-den-rozhdeniya'],
        2 => ['❤️', 'Свидание и любовь', 'Пионы, розы и романтика', 'розы, пионы, подарок девушке', 'buket-dlya-lyubimoj'],
        3 => ['🥂', 'Юбилей и торжество', 'Пышные авторские корзины', 'в коробках, юбилей, корзины', 'buket-na-godovshinu'],
    ];
    /* S12-раунд2 (Инквизитор Прет. #8): дефолтные иконки поводов — inline SVG
       lucide (cake / heart / party-popper), а не эмодзи. Если владелец задал
       СВОЁ значение в настройке occ_card_N_emoji — рисуем его (данные БД
       святее дефолта шаблона). SVG — доверенная разметка шаблона (не e()). */
    $occIcons = [
        1 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1"/><path d="M2 21h20"/><path d="M7 8v3"/><path d="M12 8v3"/><path d="M17 8v3"/><path d="M7 4h.01"/><path d="M12 4h.01"/><path d="M17 4h.01"/></svg>',
        2 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
        3 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c-.1.86.57 1.63 1.45 1.63h.38c.86 0 1.6.6 1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11v0c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98v0C9.52 4.9 9 5.52 9 6.23V7"/><path d="m11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/></svg>',
    ];
    for ($i = 1; $i <= 3; $i++) {
        [$ocEmojiD, $ocTitleD, $ocSubD, $ocTagsD, $ocSlugD] = $occDefaults[$i];
        $ocEmoji = trim((string)setting("occ_card_{$i}_emoji", $ocEmojiD));
        $ocTitle = trim((string)setting("occ_card_{$i}_title", $ocTitleD));
        if ($ocTitle === '') { continue; }
        $ocSub = trim((string)setting("occ_card_{$i}_sub", $ocSubD));
        $ocTags = trim((string)setting("occ_card_{$i}_tags", $ocTagsD));
        $ocSlug = trim((string)setting("occ_card_{$i}_slug", $ocSlugD));
        /* (1) теги: список альтернатив через запятую — матчится ЛЮБАЯ */
        $ids = [];
        if ($ocTags !== '') {
            foreach (array_filter(array_map('trim', explode(',', $ocTags))) as $alt) {
                foreach ($gridAll as $p) {
                    if ($nfTagMatch($alt, $p)) { $ids[(int)$p['id']] = true; }
                }
            }
        }
        /* (2) ∪ product_ids повода (только id, которые есть в витрине) */
        if ($ocSlug !== '' && isset($occSlugIds[$ocSlug])) {
            foreach ($occSlugIds[$ocSlug] as $pid) {
                if (isset($gridIdSet[$pid])) { $ids[$pid] = true; }
            }
        }
        /* (3) премиум → (4) топ-4 по цене: карточка всегда непустая */
        if ($ids === []) { $ids = array_fill_keys($occPremiumIds, true); }
        if ($ids === []) { $ids = array_fill_keys($occTopIds, true); }
        $occCards[] = [
            'emoji' => $ocEmoji,
            'custom' => ($ocEmoji !== '' && $ocEmoji !== $ocEmojiD),
            'icon' => $occIcons[$i] ?? '',
            'title' => $ocTitle,
            'sub' => $ocSub,
            'ids' => array_keys($ids),
        ];
    }
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
<link rel="preload" href="/fonts/Inter-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
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
<?php /* S7→S12-W2 (коммерческая структура 5cv.ru): литературный промо-баннер
   убран — первый экран витрины = H1-герой (seo_h1 из БД, Претензия 1:
   видимый, Playfair) над 4 плашками бюджета → липкая лента чипсов →
   товарная сетка. Прежний sr-only-Н1 демотирован (тэглайн внизу —
   курсивный serif). */ ?>
  <?php /* NILOV_CONFIG — общий конфиг JS (читают cart-ui.js/nilov.js/order-form.js:
         дедлайн заказа, час открытия, часовой пояс, порог бесплатной доставки). */ ?>
  <script>window.NILOV_CONFIG = {
    deadlineHour: <?= (int)(setting('order_deadline_hour', '20')) ?>,
    deadlineMinute: <?= (int)(setting('order_deadline_minute', '0')) ?>,
    openHour: <?= (int)(preg_match('/(\d{1,2})\s*:/', (string)setting('shop_hours', ''), $m) ? max(0, min(23, (int)$m[1])) : 9) ?>,
    tz: <?= json_encode(setting('shop_timezone', 'Europe/Moscow')) ?>,
    nightText: <?= json_encode(setting('countdown_night_text', 'Примем заказ сейчас — доставим с 9:00 утра'), JSON_UNESCAPED_UNICODE) ?>,
    countdownText: <?= json_encode(setting('countdown_text', 'Заказ до {D} — доставим сегодня'), JSON_UNESCAPED_UNICODE) ?>,
    closedText: <?= json_encode(setting('countdown_closed_text', 'Приём заказов на сегодня закрыт — доставим завтра с 9:00'), JSON_UNESCAPED_UNICODE) ?>,
    freeDeliveryThreshold: <?= (int) setting('free_delivery_threshold', '0') ?>
  };</script>

  <?php /* ===== S9→S12-W2: 4 ПЛАШКИ НАВИГАЦИИ + H1-ГЕРОЙ (всегда все четыре) =====
         H1 (seo_h1 → hero_title из БД, «Сказать без слов») — видимый герой
         первого экрана над сеткой (Претензия 1). Плашки 2×2 мобайл / 4 в ряд
         десктоп — белые карточки с иконками lucide-SVG (Претензия 3);
         счётчик — пилюля #F4F4F5. Клик фильтрует общую сетку каталога
         (js/five.js: бюджет-чипы входят в общий массив .fc-chip →
         is-active + apply() + мягкий скролл к #catalog). Границы —
         настройки chips_price_low/high; теги плашек 3–4 — настройки
         budget_tile_mono_tag/budget_tile_gifts_tag (матч по стему,
         PHP $nfTagMatch = JS nfTagMatch). Плашки НЕ прячутся при 0 товаров. */ ?>
  <section class="fc-budget" aria-label="Каталог букетов">
    <div class="wrap">
      <h1 class="fc-hero-title"><?= e($heroH1) ?></h1>
      <div class="fc-budget__grid">
      <button type="button" class="fc-chip fc-budget__card" data-chip="low" data-max="<?= $chipsN ?>" aria-pressed="false">
        <span class="fc-budget__label"><span class="fc-budget__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"/><path d="M12 12v6"/><path d="M12 3v2"/><path d="m19 12-2-.5"/><path d="m5 12 2-.5"/><path d="m19 16-2-1"/><path d="m5 16 2-1"/><path d="m19 8-2 1"/><path d="m5 8 2 1"/></svg></span>До&nbsp;<?= formatSum($chipsN) ?>&nbsp;₽</span>
        <span class="fc-budget__count"><?= $cntLow ?>&nbsp;<?= e($pluralBuket($cntLow)) ?></span>
      </button>
      <button type="button" class="fc-chip fc-budget__card" data-chip="high" data-min="<?= max(0, $chipsN - 1) ?>" aria-pressed="false">
        <span class="fc-budget__label"><span class="fc-budget__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7a5 5 0 1 1-4.9 6.07A1 1 0 0 0 6.12 13H5a3 3 0 0 1-1.53-5.6 1 1 0 0 0 .36-1.15A5 5 0 0 1 12 5.6a5 5 0 0 1 8.17 1.65 1 1 0 0 0 .36 1.15A3 3 0 0 1 19 13h-1.12a1 1 0 0 0-.98.8A5 5 0 0 1 12 7z"/><path d="M12 22v-8"/></svg></span>От&nbsp;<?= formatSum($chipsN) ?>&nbsp;₽</span>
        <span class="fc-budget__count"><?= $cntFrom ?>&nbsp;<?= e($pluralBuket($cntFrom)) ?></span>
      </button>
      <button type="button" class="fc-chip fc-budget__card" data-chip="mono" data-tag="<?= e($tileMonoTag) ?>" aria-pressed="false">
        <span class="fc-budget__label"><span class="fc-budget__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.735H5.81a1 1 0 0 1-.957-.735L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z"/><path d="M5 21h14"/></svg></span><?= e(setting('budget_tile_mono_label', 'Монобукеты')) ?></span>
        <span class="fc-budget__count"><?= $cntMono ?>&nbsp;<?= e($pluralBuket($cntMono)) ?></span>
      </button>
      <button type="button" class="fc-chip fc-budget__card" data-chip="hitgift" data-tag="<?= e($tileGiftsTag) ?>" aria-pressed="false">
        <span class="fc-budget__label"><span class="fc-budget__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg></span><?= e(setting('budget_tile_gifts_label', 'Хиты и подарки')) ?></span>
        <span class="fc-budget__count"><?= $cntGift ?>&nbsp;<?= e($pluralBuket($cntGift)) ?></span>
      </button>
      </div>
    </div>
  </section>

  <?php /* ===== ЛЕНТА ЧИПСОВ (липкая) =====
         «Все» → теги-коллекции (Розы/Пионы/Гортензии/В коробках/
         Подарки — настройка chips_tags). Ценовые фильтры живут в плашках
         бюджета выше (4-я плашка — «Хиты»); порядок и матчинг — прежние
         (PHP $nfTagMatch = JS nfTagMatch, контракт .fc-chip[data-chip]/[data-tag] цел). */ ?>
  <?php /* S11 ДЕФЕКТ 1 (sticky): лента чипсов + каталог — в одном .fc-catalog-zone
         контейнере. Раньше .fc-chips-bar (position:sticky; top:105px) был
         прямым потомком <main> и залипал НАД секциями мастерской/поводов/FAQ,
         паря поверх текста при скролле ниже каталога. Теперь sticky-контекст
         = только зона каталога: доехали до конца каталога — лента уплыла. */ ?>
  <?php if ($featChips): ?>
  <?php
  $chipsTags = array_values(array_filter(array_map('trim', explode(',', setting('chips_tags', ''))), fn($t) => $t !== ''));
  /* S9: $nfTagMatch определён выше (до плашек бюджета) — здесь только
         использование: тег-чипы ленты матчатся тем же матчером, что и
         счётчики плашек (контракт PHP = JS nfTagMatch). */ ?>
  <div class="fc-catalog-zone">
  <div class="fc-chips-bar">
    <div class="wrap">
      <div class="fc-chips" id="fcChips">
        <button type="button" class="fc-chip fc-chip--all is-active" data-chip="all" aria-pressed="true">
          <span class="fc-chip__title"><?= e(setting('chips_all_text', 'Все')) ?></span>
        </button>
        <?php foreach ($chipsTags as $chipTag):
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
        <span class="fc-chips__count" aria-live="polite" style="flex:none;align-self:center;white-space:nowrap;font-size:.85rem;font-weight:600;color:var(--ink-muted)"></span>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="fc-catalog-zone">
  <?php endif; ?>

  <?php /* W96 (5cv): trust-strip убран — роль играют чипы и карточка доставки в hero.
     W103 (F1): marquee ВЕРНУЛСЯ (см. блок выше) — ритм-разделитель под hero. */ ?>

  <?php /* ===== S8: ЕДИНАЯ ВИТРИНА 5CV — ОДНА МОНОЛИТНАЯ СЕТКА =====
         (ликвидация «пустых полок»: деление на 3 тематические секции убрано —
         все букеты из базы выводятся подряд в ровном ритме: хиты →
         авторские/розы → премиум/остаток/сладости; каждый товар 1 раз.
         Фильтры (плашки бюджета, чипы, поиск) скрывают карточки той же
         механикой catalog-filter.js apply().) */ ?>
  <section class="fc-section fc-catalog" id="catalog">
    <div class="wrap">
      <?php /* индикатор активного поиска — пилюля «Поиск: «запрос» ✕»
             (ведение js/catalog-filter.js; клик снимает запрос) */ ?>
      <button type="button" class="catalog-search-chip" id="catalogSearchChip" hidden>
        <span class="catalog-search-chip__label" id="catalogSearchChipLabel">Поиск</span>
        <span class="catalog-search-chip__x" aria-hidden="true">&#10005;</span>
      </button>
      <div id="catalogGrid">
        <div class="catalog__grid">
          <?php foreach ($gridAll as $p) { render_product_card($p, $cardCtx); } ?>
        </div>
      </div>
      <?php /* Empty-state: при 0 карточек от всех фильтров — подсказка + сброс. */ ?>
      <div class="catalog-empty" id="catalogEmpty" hidden style="text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px;margin-top:14px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px"><?= e(setting('catalog_empty_title', 'Под эти фильтры ничего не подошло')) ?></p>
        <?php $catalogEmptyHint = trim(setting('catalog_empty_hint', 'Сбросьте цену или загляните в соседнюю категорию')); ?>
        <?php if ($catalogEmptyHint !== ''): ?><p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px"><?= e($catalogEmptyHint) ?></p><?php endif; ?>
        <button type="button" class="btn btn--outline" id="catalogEmptyReset">Сбросить фильтры</button>
      </div>
    </div>
  </section>
  </div><?php /* /.fc-catalog-zone — конец sticky-контекста ленты чипсов (S11) */ ?>

  <?php /* ===== S7: МАСТЕРСКАЯ В СПБ + ОТЗЫВЫ (блок доверия под каталогом) =====
         Слева — мастерская: адрес (shop_address), часы (workshop_hours),
         плашка «★ 5.0 · Более 400 отзывов на Яндекс Картах» (workshop_rating,
         ссылка на карты при yandex_reviews_id). Справа — 3 живых отзыва из БД
         (getReviews(null,3)) со звёздами. */ ?>
  <?php
  $wsReviews = getReviews(null, 3);
  $wsAgg = getRatingAggregate();
  $wsTitle = trim((string)setting('workshop_title', 'Наша мастерская в Санкт-Петербурге'));
  $wsAddress = trim((string)setting('shop_address', 'г. Санкт-Петербург, Петроградская сторона'));
  $wsHours = trim((string)setting('workshop_hours', ''));
  if ($wsHours === '') {
      $wsHours = trim((string)setting('shop_hours', ''));
  }
  if ($wsHours === '') {
      $wsHours = 'Ежедневно с 09:00 до 21:00';
  }
  $wsRating = trim((string)setting('workshop_rating', ''));
  if ($wsRating === '') {
      $wsRating = $wsAgg['count'] > 0
          ? sprintf('%.1f', (float)$wsAgg['avg']) . ' · Более 400 отзывов на Яндекс Картах'
          : '5.0 · Более 400 отзывов на Яндекс Картах';
  }
  $wsYrId = trim(setting('yandex_reviews_id', ''));
  $wsYrOn = setting('yandex_reviews_enabled', '0') === '1' && $wsYrId !== '';
  $wsMonths = ['01'=>'января','02'=>'февраля','03'=>'марта','04'=>'апреля','05'=>'мая','06'=>'июня','07'=>'июля','08'=>'августа','09'=>'сентября','10'=>'октября','11'=>'ноября','12'=>'декабря'];
  ?>
  <?php if ($wsReviews !== []): ?>
  <section class="fc-section fc-section--soft fc-workshop" id="reviews">
    <div class="wrap">
      <div class="fc-workshop__grid">
        <div class="fc-workshop__info">
          <h2 class="fc-workshop__title"><?= e($wsTitle) ?></h2>
          <?php if ($wsYrOn): ?><a class="fc-workshop__badge" href="https://yandex.ru/maps/org/<?= e(rawurlencode($wsYrId)) ?>" target="_blank" rel="noopener"><?php else: ?><p class="fc-workshop__badge"><?php endif; ?>
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
            <span><?= e($wsRating) ?></span>
          <?php if ($wsYrOn): ?></a><?php else: ?></p><?php endif; ?>
          <?php if ($wsAddress !== ''): ?>
          <p class="fc-workshop__row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>
            <span><?= e($wsAddress) ?></span>
          </p>
          <?php endif; ?>
          <?php if ($wsHours !== ''): ?>
          <p class="fc-workshop__row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <span><?= e($wsHours) ?></span>
          </p>
          <?php endif; ?>
          <p class="fc-workshop__note"><?= e(setting('workshop_note', 'Собираем букеты утром и везём в день заказа — загляните за свежими цветами или закажите доставку.')) ?></p>
        </div>
        <div class="fc-workshop__reviews">
          <?php foreach ($wsReviews as $rvRow): ?>
          <?php
          $rvRating = max(1, min(5, (int)$rvRow['rating']));
          $rvDate = '';
          if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$rvRow['created_at'], $rvDm)) {
              $rvDate = ((int)$rvDm[3]) . ' ' . ($wsMonths[$rvDm[2]] ?? '');
          }
          ?>
          <figure class="fc-workshop__review">
            <p class="fc-workshop__stars" aria-label="Оценка: <?= $rvRating ?> из 5"><?= str_repeat('<span aria-hidden="true">★</span>', $rvRating) . str_repeat('<span class="fc-workshop__star--off" aria-hidden="true">★</span>', 5 - $rvRating) ?></p>
            <blockquote class="fc-workshop__text"><?= e($rvRow['text']) ?></blockquote>
            <figcaption class="fc-workshop__meta">
              <span class="fc-workshop__author"><?= e($rvRow['author']) ?></span>
              <?php if ($rvDate !== ''): ?><time class="fc-workshop__date" datetime="<?= e($rvRow['created_at']) ?>"><?= e($rvDate) ?></time><?php endif; ?>
            </figcaption>
          </figure>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* W96 (5cv): секция how-it-works убрана — шаги остаются в настройках, но не выводятся. */ ?>

  <?php /* S10 ДЕФЕКТ 2: ПОВОДЫ — три белые карточки с эмодзи (вместо серых
         градиентных плит). Клик — фильтр каталога (чип data-chip="ids",
         js/five.js скроллит к #catalog), подпись — живой счётчик букетов. */ ?>
  <?php if ($occCards !== []): ?>
  <?php /* S12 (Task ID 1, СТРОИТЕЛЬ): поводы — БЕЛАЯ секция. Соседняя
         мастерская (1308) — мягкая #F9FAFB; две soft-секции подряд
         (мастерская → поводы) сливались в сплошную серую массу без
         границы — белый фон поводов восстанавливает ритм
         белый/мягкий/белый до SEO-блока (1398). */ ?>
  <section class="fc-section" id="occasions">
    <div class="wrap">
      <div class="fc-row__head">
        <div class="fc-row__heading">
        <h2 class="fc-row__title"><?= e(setting('occasions_title', 'Цветы по поводам')) ?></h2>
        <?php $occSub2 = trim((string)setting('occasions_sub', '')); ?>
        <?php if ($occSub2 !== ''): ?><p class="fc-row__sub"><?= e($occSub2) ?></p><?php endif; ?>
        </div>
      </div>
      <div class="fc-occ-grid">
        <?php foreach ($occCards as $c): ?>
        <button type="button" class="fc-chip fc-occ-card" data-chip="ids" data-ids="<?= e(implode(',', $c['ids'])) ?>" aria-pressed="false">
          <span class="fc-occ-card__emoji" aria-hidden="true"><?= $c['custom'] ? e($c['emoji']) : $c['icon'] /* S12-раунд2: SVG lucide дефолтом, эмодзи владельца — если задан */ ?></span>
          <span class="fc-occ-card__text">
            <span class="fc-occ-card__title"><?= e($c['title']) ?></span>
            <span class="fc-occ-card__sub"><?= e($c['sub']) ?></span>
          </span>
          <span class="fc-occ-card__count"><?= count($c['ids']) ?>&nbsp;<?= e($pluralBuket(count($c['ids']))) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* S10 ДЕФЕКТ 1: форма заказа УБРАНА с главной — простыня из ~20 полей
         съедала 60% высоты витрины. Чекут живёт ТОЛЬКО на /checkout.php:
         кнопка «Оформить заказ» в корзине (js/cart-ui.js goToOrderSection)
         автоматически ведёт туда, когда на странице нет #order/#orderForm.
         Тот же partial рендерит /checkout.php (со сводкой корзины). */ ?>


  <?php /* SEO-ТЕКСТ (5cv): sanitize_rich_text разрешает только <a>; абзацы — через \n\n → <p> (стилизует .fc-seo p).
       W103 (6-b, критик-5а P0 «низ главной»): колофон-стиль — тихий caps-заголовок,
       первые два абзаца снаружи, остальной текст в <details> (в HTML остаётся весь
       текст — SEO не страдает); summary — Playfair-курсив. */ ?>
  <?php /* S12-W2 (Претензия 5): SEO-секция — мягкая (var(--surface-soft)
         #F4F4F5): смежные soft-зоны SEO→FAQ разделяет hairline
         border-block у .fc-section--soft, а не пустая белая дыра. */ ?>
  <?php if ($featSeotext): ?>
  <section class="fc-section fc-section--soft">
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

  <?php /* S7: editorial-врезка убрана (строгий ритейл-финал: FAQ сразу
         за SEO-текстом); функция и настройка editorial_text_3 остаются. */ ?>

  <?php /* FAQ (критерий 13, SEO FAQPage — паттерн Цветовика): реальные вопросы покупателей. Отключаем (критерий 16). */ ?>
  <?php if ($featFaq): ?>
  <section class="fc-section fc-section--soft section--faq" id="faq">
    <?php /* W96-fix2 (F4): FAQ-колонка шире (760→880) — вопрос-ответ читается
           без «узкой газетной» колонки; длинные вопросы не переносятся в 3 строки */ ?>
    <div class="wrap" style="max-width:880px">
      <?php /* W104-α (M): FAQ — завершающий нумерал оглавления главной (09);
             обёртка fc-row__heading включает тот же oversize-индекс за H2.
             W105 (4-a): обёртка fc-row__head — тот же механизм отступа
             «H2 → контент», что у остальных секций (margin-bottom 26px);
             без неё FAQ-заголовок прилипал к первому вопросу. */ ?>
      <div class="fc-row__head">
      <div class="fc-row__heading">
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
