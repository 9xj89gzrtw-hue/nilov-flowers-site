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
$zones = $pdo->query('SELECT id, name, price FROM delivery_zones ORDER BY sort, id')->fetchAll();

/* Внешний вид: hero-текст можно отключить тумблером */
$heroTextEnabled = setting('hero_text_enabled', '1') === '1';
$heroH1 = setting('seo_h1', setting('hero_title', 'Доставка цветов по Санкт-Петербургу'));
$heroBtnText = trim(setting('hero_button_text', 'Выбрать букет'));
$heroBtnLink = safe_url(trim(setting('hero_button_link', '#catalog')));
$heroBtn = $heroTextEnabled && $heroBtnText !== '' && $heroBtnLink !== '';

/* Функции витрины (критерий 16): каждый блок отключаем из админки */
$featFaq = setting('feature_faq', '1') === '1';
$featCountdown = setting('feature_countdown', '1') === '1';
$featPriceFilter = setting('feature_price_filter', '1') === '1';
$featFavorites = setting('feature_favorites', '1') === '1';
$featZoneCheck = setting('feature_zone_check', '1') === '1';
$featDeliveryBadge = setting('feature_delivery_badge', '1') === '1';
/* Критик functional 6/10: gift-UX (получатель+открытка) и слоты даты — отключаемые (критерий 14) */
$featGiftFields = setting('feature_gift_fields', '1') === '1';
$featDeliverySlots = setting('feature_delivery_slots', '0') === '1';

/* Блоки 5cv: город-бар, hero-промо/доставка, чипы, карусели, поводы и т.д.
   Новые ключи читаем с дефолтами — до миграции БД вернётся дефолт. */
$featCitybar = setting('feature_citybar', '1') === '1';
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
$heroPromoOn = setting('hero_promo_enabled', '1') === '1';
$heroDeliveryOn = setting('hero_delivery_card_enabled', '1') === '1';

/* W103 (F1): marquee-лента под hero — СУЩЕСТВУЮЩИЕ ключи marquee_1..4
   (дефолты — «заводские» из settings-history.php). Пустые строки пропускаем. */
$featMarquee = setting('feature_marquee', '1') === '1';
$marqueeDefaults = [
    1 => 'Доставка по Санкт-Петербургу в день заказа',
    2 => 'Собираем и доставляем в день заказа',
    3 => 'Фото букета перед отправкой',
    4 => 'Заменяем увядшие в день доставки',
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
$guaranteeDefaults = ['Фото букета перед отправкой', 'Свежие цветы с утренней поставки', 'Заменяем увядшие в день доставки'];
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

function product_img_url(array $p): string
{
    return $p['image'] !== '' ? '/img/products/' . rawurlencode($p['image']) : '';
}

/* WebP-вариант того же фото (если сгенерирован рядом: name.jpg → name.webp).
   Идемпотентно: файла нет — вернём пустую строку и <source> не напечатаем. */
function product_img_webp(array $p): string
{
    if ($p['image'] === '') return '';
    $webp = '/img/products/' . rawurlencode(preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', $p['image']));
    return is_file(BASE_PATH . urldecode($webp)) ? $webp : '';
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
    if (is_file($dst)) return $url;

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
    return $url;
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
    $t600 = $thumb !== '' ? (string)preg_replace('/-400(\.webp)$/', '-600$1', $thumb) : '';
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
    if (is_file($dst)) return $url;

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
    return $url;
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
    /* W96-fix3a (T2): srcset карточки — webp-превью 400w + webp-оригинал {w}w.
   Слот карточки: моб — 2 колонки ≈ 45vw, десктоп — 280–300px (карусель clamp
   + сетка 3 кол.) → браузер при DPR≤2 берёт лёгкое превью, ретина-десктоп — оригинал.
   img-фолбэк (jpg) остаётся без srcset: превью только webp, браузерам без webp
   отдаётся оригинал как раньше. */
    $thumb = product_img_thumb($p);
    $origW = product_img_width($p);
    if ($thumb !== '' && $imgWebp !== '' && $origW > 0) {
        $t600 = preg_replace('/-400(\.webp)$/', '-600$1', $thumb); /* W101 (perf): 600w для DPR2-3 (файлы -600 в кэше GD) */

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
    $sizes = '(max-width:899px) 45vw, (min-width:900px) 300px';
    $link = '/product/' . rawurlencode($p['slug']);
    /* W96-fix1 (F3): поисковый индекс карточки — имя + категория + описание
       (нижний регистр; js/five.js матчит по стемму запроса как подстроке).
       W99-fixG (G2): в карусельных копиях НЕ печатаем (описания дублируются —
       36.5КБ лишнего HTML; поиск живёт только в #catalogGrid). */
    $isCarousel = !empty($ctx['carousel']);
    $searchIndex = $isCarousel ? '' : mb_strtolower(trim($p['name'] . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['description'] ?? '')));
    /* K10 (W101): data-upsell="1" (show_in_upsell) — приоритетный источник апсейла
       корзины (js/cart-ui.js): сладкие допы вместо «первых попавшихся букетов».
       Как и остальные фильтрационные атрибуты — только на каталог-карточке
       (карусельные копии без них, паттерн G2). */
    $upsellAttr = (!$isCarousel && (int)($p['show_in_upsell'] ?? 0) === 1) ? ' data-upsell="1"' : '';
    ?>
        <article class="product-card reveal"<?= $isCarousel
            ? ''
            : ' data-category-id="' . (int)($p['category_id'] ?? 0) . '" data-price="' . (int)$price . '" data-hit="' . (int)($p['is_hit'] ?? 0) . '" data-premium="' . (int)($p['is_premium'] ?? 0) . '" data-search="' . e($searchIndex) . '"' . $upsellAttr ?>>
          <div class="product-card__media">
          <?php /* W99-fixG (G11): img-ссылка дублирует title-ссылку — прячем от
             скринридера и Tab-фокуса (href сохранён: клик мышью работает) */ ?>
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>" aria-hidden="true" tabindex="-1">
              <?php if ($img !== ''): ?>
                <picture>
                  <?php if ($srcset !== ''): ?><source type="image/webp" srcset="<?= e($srcset) ?>"<?= $thumb !== '' ? ' sizes="' . e($sizes) . '"' : '' ?>><?php endif; ?>
                  <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
                </picture>
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
            </a>
            <?php if ($isSale): $offPct = (int)$p['price'] > 0 ? (int)round((1 - $price / (int)$p['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= e(setting('badge_sale_text', 'Скидка')) ?><?php if ($offPct > 0): ?> <?= $offPct ?>%<?php endif; ?></span><?php endif; ?>
            <?php if ((int)($p['is_urgent'] ?? 0) === 1): ?><span class="product-card__badge product-card__badge--urgent"><?= e(setting('badge_urgent_text', 'Успеть сегодня')) ?></span><?php endif; ?>
            <?php /* Бейджи 5cv: «Хит» (amber) и «Премиум» (ink) — по флагам товара */ ?>
            <?php if ($isHit): ?><span class="product-card__badge product-card__badge--hit"><?= e(setting('badge_hit_text', 'Хит')) ?></span><?php endif; ?>
            <?php if ($isPremium): ?><span class="product-card__badge product-card__badge--premium"><?= e(setting('badge_premium_text', 'Премиум')) ?></span><?php endif; ?>
            <?php /* Конкурентный бейдж (критерий 13, EXPRESS-паттерн): тариф зоны владельца. Текст редактируется (критерий 16). */ ?>
            <?php if ($ctx['featDeliveryBadge']): ?><span class="product-card__badge product-card__badge--deliv"><?= e(setting('delivery_badge_text', 'Доставка по Санкт-Петербургу')) ?></span><?php endif; ?>
            <button type="button" class="product-card__cta" data-order-cta
              data-product-id="<?= (int)$p['id'] ?>"
              data-product-name="<?= e($p['name']) ?>"
              data-product-price-raw="<?= $price ?>"
              data-product-image="<?= e($img) ?>"
              aria-label="Добавить в корзину: <?= e($p['name']) ?>" title="В корзину">+</button>
            <?php /* Избранное (критерий 13, Русский Букет-паттерн): сердечко на карточке, localStorage. Отключаем (критерий 16). */ ?>
            <?php if ($ctx['featFavorites']): ?><button type="button" class="product-card__fav" data-fav-id="<?= (int)$p['id'] ?>" data-fav-name="<?= e($p['name']) ?>" aria-label="В избранное: <?= e($p['name']) ?>" title="В избранное">♡</button><?php endif; ?>
          </div>
          <div class="product-card__body">
            <span class="product-card__cat"><?= e($p['category_name'] ?? '') ?></span>
            <a class="product-card__name" href="<?= e($link) ?>"><?= e($p['name']) ?></a>
            <p class="product-card__price">
              <?php if ($isSale): ?>
                <span class="product-card__price--old"><?= formatPrice((int)$p['price']) ?></span>
                <span class="product-card__price--discount"><?= formatPrice($price) ?></span>
              <?php else: ?>
                <?= formatPrice($price) ?>
              <?php endif; ?>
            </p>
            <?php if ((int)($p['is_urgent'] ?? 0) === 1): ?>
            <p class="product-card__urgent-note">Соберём и доставим в течение дня — количество ограничено</p>
            <?php endif; ?>
          </div>
        </article>
    <?php
}

/* Секция-карусель 5cv: заголовок + подпись + «Смотреть все» + стрелки + лента карточек.
   W96-fix1 (F7): $chip — «Смотреть все» у Хитов/Премиума/До N применяет чип каталога
   (js/five.js по data-chip), у категорийных секций — вкладку (data-tab, как раньше).
   W97-fixB3b (B3b-1f): $catSlug — у категорийных каруселей «Смотреть все» ведёт на
   посадочную /category/{slug} (SEO: у категории появился собственный URL),
   data-tab больше не печатается. */
function render_fc_row(string $title, string $sub, array $items, array $ctx, string $tabId = '', string $chip = '', string $catSlug = '', string $eyebrow = ''): void
{
    global $fcProdSeq;
    if ($items === []) return;
    $fcProdSeq++;
    /* W99-fixG (G2): копии карточек для карусели — без фильтрационных атрибутов
       (render_product_card по ctx['carousel']) */
    $ctx['carousel'] = true;
    /* W97-fixB2 (B2-5а): чередование фонов товарных секций — каждая вторая tint
       (стили придёт волной CSS; здесь только классы) */
    $tint = ($fcProdSeq % 2 === 0) ? ' fc-section--tint' : '';
    ?>
    <section class="fc-section<?= $tint ?>"><div class="wrap">
      <div class="fc-row"><div class="fc-row__head">
        <?php /* W103 (F1): обёртка heading — eyebrow над H2 не ломает flex-строку
               шапки секции (заголовок+подпись группируются в один блок) */ ?>
        <div class="fc-row__heading">
        <?php if ($eyebrow !== ''):
            /* «Капс|курсив»: до | — Montserrat caps, после — Playfair italic */
            [$ebCaps, $ebItalic] = array_pad(explode('|', $eyebrow, 2), 2, ''); ?>
        <p class="fc-row__eyebrow"><?= e($ebCaps) ?><?= $ebItalic !== '' ? ' <em>' . e($ebItalic) . '</em>' : '' ?></p>
        <?php endif; ?>
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
      <div class="fc-carousel" role="region" aria-label="<?= e($title) ?>" tabindex="0">
        <?php foreach ($items as $item) { render_product_card($item, $ctx); } ?>
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

/* W97-fixB2 (B2-5): счётчик товарных секций — общий для каруселей и каталога:
   им чередуем фон (каждая вторая — tint). Топ-левел index.php = global scope,
   render_fc_row() читает его через global. W99-fixG (G17): guard-массив —
   по номеру врезки. W103: премиум-разворот тоже инкрементит счётчик (тело ниже). */
$fcProdSeq = 0;
$fcEditorialDone = [];

/* ---- Карусели (5cv): хиты / по категориям / премиум / до N ₽ ---- */

/* Хиты: is_hit=1; если ни одного — первые 8 активных по sort,id */
$hitProducts = array_values(array_filter($products, static fn (array $p): bool => (int)($p['is_hit'] ?? 0) === 1));
if ($hitProducts === []) {
    $hitProducts = array_slice($products, 0, 8);
}

/* По категориям: секция-карусель имеет смысл от ≥2 товаров (одиночная карточка
   в ряду — «пустая» секция, ломает ритм витрины 5cv); до 12 карточек */
$productsByCat = [];
foreach ($products as $p) {
    $productsByCat[(int)($p['category_id'] ?? 0)][] = $p;
}
$categoryRows = [];
foreach ($categories as $c) {
    $cid = (int)$c['id'];
    if (isset($productsByCat[$cid]) && count($productsByCat[$cid]) >= 2) {
        /* W97-fixB3b (B3b-1f): слаг категории — на лету из name (колонки slug в БД нет) */
        $categoryRows[] = ['id' => $cid, 'name' => (string)$c['name'], 'slug' => slugify((string)$c['name']), 'items' => array_slice($productsByCat[$cid], 0, 12)];
    }
}

/* W103 (F1): карусели категорий — максимум ДВЕ после Хитов. Критик-1: 11 однотипных
   каруселей подряд = монотонный ритм; остальные категории — через вкладки каталога
   и посадочные /category/{slug} (в футере). */
$categoryRows = array_slice($categoryRows, 0, 2);

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

/* «До N ₽»: цена после скидки (productPrice) ≤ N */
$budgetProducts = array_values(array_filter($products, static fn (array $p): bool => productPrice($p) <= $chipsN));

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
    /* Разбираем CSV product_ids, берём первый существующий активный товар с картинкой */
    foreach (array_filter(array_map('intval', array_map('trim', explode(',', (string)$o['product_ids'])))) as $pid) {
        if (isset($productsById[$pid]) && product_img_url($productsById[$pid]) !== '') {
            $photo = product_img_url($productsById[$pid]);
            break;
        }
    }
    $occTiles[] = [
        'slug' => (string)$o['slug'],
        /* Подпись плитки: без хвоста « в Санкт-Петербурге» (он уже в заголовке секции) */
        'title' => (string)preg_replace('/\s+в Санкт-Петербурге$/u', '', (string)$o['title']),
        'photo' => $photo,
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
$seoTextDefault = "Доставка цветов по Санкт-Петербургу — в день заказа. Работаем по районам Санкт-Петербурга: в пределах КАД привозим букет за 1–2 часа, в пригороды — Пушкин, Павловск, Гатчина, Всеволожск — в согласованный интервал. Оформите заказ до 20:00, и цветы будут у получателя сегодня же.\n\nСвежесть — главное. Цветы приходят к нам с утренней поставки, а не лежат на складе: букет собираем непосредственно перед отправкой. Перед выездом курьера пришлём фото готовой композиции — вы увидите именно то, что получит адресат. Если какой-то цветок выглядит не идеально, заменим его до доставки.\n\nСпособ оплаты выберете при оформлении: наличными или картой курьеру при получении. Поводы бывают разные: букет маме на день рождения, извиниться, поздравить коллегу или сказать «люблю» без повода — подскажем состав под бюджет и характер события. А если сомневаетесь — просто позвоните, соберём букет вместе по телефону.";
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e(setting('seo_title', 'Доставка цветов по СПб — ' . setting('shop_name', 'Nilov Flowers'))) ?></title>
<meta name="description" content="<?= e(setting('seo_description', 'Доставка букетов по Санкт-Петербургу в день заказа. Свежие цветы с утренней поставки, фото перед отправкой. Заказы до 20:00 — доставим сегодня.')) ?>">
<meta property="og:title" content="<?= e(setting('seo_title', 'Доставка цветов по СПб — ' . setting('shop_name', 'Nilov Flowers'))) ?>">
<meta property="og:description" content="Букеты с доставкой в день заказа по Санкт-Петербургу. Фото перед отправкой, свежие цветы с утренней поставки.">
<meta property="og:url" content="https://flowers.interfood-catering.ru/">
<?php /* W96-fix3a (T5d): $pageDescription → twitter:description в partials/head.php
   (парно к og:description; значение — редактируемый из админки seo_description) */ ?>
<?php $pageDescription = setting('seo_description', 'Доставка букетов по Санкт-Петербургу в день заказа. Свежие цветы с утренней поставки, фото перед отправкой. Заказы до 20:00 — доставим сегодня.'); ?>
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
$heroSrcset = [];
if ($heroThumb480 !== '') { $heroSrcset[] = $heroThumb480 . ' 480w'; }
if ($heroThumb768 !== '') { $heroSrcset[] = $heroThumb768 . ' 768w'; }
if ($heroWebpOk && $heroDim !== null) { $heroSrcset[] = $heroWebpUrl . ' ' . (int)$heroDim[0] . 'w'; }
$heroSrcsetStr = implode(', ', $heroSrcset);
$heroSizes = '(max-width:899px) 100vw, 640px';
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
        'streetAddress' => 'Полевая Сабировская ул., 47, корп. 1',
        'addressLocality' => 'Санкт-Петербург',
        'addressCountry' => 'RU',
    ],
    'addressString' => setting('shop_address', ''),
    'priceRange' => '₽₽',
    'geo' => [
        '@type' => 'GeoCoordinates',
        'latitude' => 59.9970675,
        'longitude' => 30.2727226,
    ],
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
   попадает в него, а не в кнопки город-бара (было: город-бар → ссылка/кнопки → skip-link).
   .skip-link position:fixed;top:-48px (css/style.css) — вне потока, вынос до город-бара
   НЕ меняет визуал. Флаг $skipLinkRendered — header.php не дублирует ссылку
   (на остальных витринных страницах её печатает сам header.php). */ ?>
<?php $skipLinkRendered = true; ?>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<?php /* Город-бар (5cv): подтверждение города, JS five.js прячет по localStorage 'fc-city-ok'.
   W103 (F4, критик-3 P0-1): мобильный компакт-режим — тонкая строка
   «Санкт-Петербург ✓ · Изменить» (~36px) вместо вопроса с двумя кнопками (56px).
   Десктоп не меняется. Город для компакт-строки извлекаем из полного текста
   (убираем канонический префикс «Ваш город — » и хвостовой «?»); кастомный
   текст владельца без префикса показывается целиком — ellipsis подстрахует.
   a11y: на мобиле полный текст и подпись «Выбрать другой» остаются в дереве
   скринридера (CSS прячет их только визуально), «✓» — декоративный. */ ?>
<?php if ($featCitybar): ?>
<?php
$citybarText = (string)setting('citybar_text', 'Ваш город — Санкт-Петербург?');
$citybarCity = preg_replace('/^\s*Ваш\s+город[^А-Яа-яЁё]*\s*/u', '', $citybarText);
$citybarCity = trim((string)preg_replace('/\s*\?\s*$/u', '', (string)$citybarCity));
if ($citybarCity === '') { $citybarCity = $citybarText; }
?>
<div class="fc-citybar" id="fcCitybar">
  <div class="wrap fc-citybar__inner">
    <span class="fc-citybar__text"><?= e($citybarText) ?></span>
    <span class="fc-citybar__compact" aria-hidden="true"><?= e($citybarCity) ?></span>
    <button type="button" class="fc-citybar__yes" id="fcCityYes">Да, верно</button>
    <a class="fc-citybar__no" href="#contacts">
      <span class="fc-citybar__no-full"><?= e(setting('citybar_no_text', 'Выбрать другой')) ?></span>
      <span class="fc-citybar__no-short" aria-hidden="true">Изменить</span>
    </a>
    <button type="button" class="fc-citybar__close" id="fcCityClose" aria-label="Закрыть">&times;</button>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main" tabindex="-1">
  <!-- HERO-БЕНТО (5cv): фото-карточка с H1 + промо/доставка справа -->
  <section class="fc-hero">
    <div class="wrap fc-hero__grid">
      <?php
      /* W99-fixG (G3): переменные hero ($heroImg/$heroWebpOk/$heroDim/
         $heroSrcsetStr/$heroSizes) посчитаны выше в <head> — там же preload. */
      ?>
      <div class="fc-hero__main<?= $heroRoot === '' ? ' fc-hero__main--fallback' : '' ?>">
        <?php if ($heroRoot !== ''): ?>
        <picture>
          <?php /* W99-fixG (G3): srcset 480w/768w/1024w + sizes по слоту
                 .fc-hero__main (расчёт — в <head>, рядом с preload). GD-сбой —
                 прежний одиночный src webp. */ ?>
          <?php if ($heroWebpOk && $heroSrcsetStr !== ''): ?><source type="image/webp" srcset="<?= e($heroSrcsetStr) ?>" sizes="<?= e($heroSizes) ?>"><?php elseif ($heroWebpOk): ?><source type="image/webp" srcset="<?= e($heroWebpUrl) ?>"><?php endif; ?>
          <?php /* W97-fixB2 (B2-6): описательный alt (было alt=H1 — дублировал видимый
                 заголовок для скринридера); текст редактируется как hero_image_alt */ ?>
          <img class="fc-hero__img" src="<?= e($heroUrl) ?>" alt="<?= e(setting('hero_image_alt', 'Свежий букет из сезонных цветов — витрина магазина')) ?>" fetchpriority="high"<?= $heroDim ? ' width="' . (int)$heroDim[0] . '" height="' . (int)$heroDim[1] . '"' : '' ?>>
        </picture>
        <?php endif; ?>
        <div class="fc-hero__content">
        <?php if ($heroTextEnabled): ?>
        <p class="fc-hero__eyebrow"><?= e(setting('hero_eyebrow', 'Санкт-Петербург · доставка в день заказа')) ?></p>
        <h1 class="fc-hero__title"><?php
        /* W103 (F1): акцентное слово H1 — Playfair italic + amber-мазок (стили в five.css).
           Ищем «цветов» (без пунктуации), иначе — второе слово. NBSP-склейка «по Санкт-…»
           сохранена: \s в PCRE /u не матчит U+00A0 — склеенный токен не разваливается.
           W103 (6-b, M1): entrance — по-СЛОВНЫЙ rise из-под маски (nv-line/nv-ch —
           оживлённый W4-набор из nilov.css; слова — inline-block, --i — порядок). */
        $heroWords = preg_split('/\s+/u', trim(preg_replace('/ по /u', ' по ', ' ' . trim($heroH1) . ' ', 1))) ?: [];
        $heroAccentIdx = -1;
        foreach ($heroWords as $hwI => $hwW) {
            if (mb_strtolower(trim($hwW, '.,!?:;—«»„“')) === 'цветов') { $heroAccentIdx = (int)$hwI; break; }
        }
        if ($heroAccentIdx < 0 && count($heroWords) >= 2) { $heroAccentIdx = 1; }
        foreach ($heroWords as $hwI => $hwW) {
            if ($hwI > 0) { echo "\n"; }
            echo '<span class="nv-line"><span class="nv-ch" style="--i:' . $hwI . '">'
                . ($hwI === $heroAccentIdx
                    ? '<em class="fc-hero__accent">' . e($hwW) . '</em>'
                    : e($hwW))
                . '</span></span>';
        }
        ?></h1>
        <p class="fc-hero__sub"><?= e(setting('hero_subtitle', 'Соберём и доставим букет в течение дня — к празднику или просто так')) ?></p>
        <?php endif; ?>
        <?php if ($heroBtn || $heroGhost): ?>
        <?php /* W103 (F1): ОДНА первичная CTA (розовая пилюля) + тихий ghost-текст со
                стрелкой — было три конкурирующих кнопочных элемента (критик-1) */ ?>
        <div class="fc-hero__actions">
        <?php if ($heroBtn): ?>
        <?php /* W97-fixB2 (B2-5в): розовая версия главной CTA — модификатор + база fc-btn
                (стили придёт волной CSS; текст/ссылку не меняем) */ ?>
        <a class="fc-btn fc-hero__cta fc-hero__cta--pink" href="<?= e($heroBtnLink) ?>">
          <?= e($heroBtnText) ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($heroGhost): ?>
        <a class="fc-hero__ghost" href="<?= e($heroGhostLink) ?>"><?= e($heroGhostText) ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php /* Таймер «до 20:00» — не зависит от hero-текста (юр-независимый элемент). Отключаем (критерий 16). */ ?>
        <?php if ($featCountdown): ?><p class="hero__deadline" style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:8px 16px;border-radius:999px;background:rgba(255,255,255,.75);backdrop-filter:blur(6px);border:1px solid var(--line);font-size:.9rem;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums;max-width:100%;min-height:38px"><?= e(str_replace(['{T}', '{D}'], ['…', setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT)], setting('countdown_text', 'Успейте заказать сегодня — осталось {T} до {D}'))) /* W80 → W103 (F4, критик-3 P2): пре-JS paint без ложного «00 ч 00 мин» — нейтральное многоточие; реальное время подставляет nilov.js первым тиком */ ?></p><?php endif; ?>
        </div>
        <?php /* NILOV_CONFIG — общий конфиг JS (вне гейта таймера): порог бесплатной доставки
               должен работать и при выключенном таймере. */ ?>
        <script>window.NILOV_CONFIG = {
          deadlineHour: <?= (int)(setting('order_deadline_hour', '20')) ?>,
          deadlineMinute: <?= (int)(setting('order_deadline_minute', '0')) ?>,
          openHour: <?= (int)(preg_match('/(\d{1,2})\s*:/', (string)setting('shop_hours', ''), $m) ? max(0, min(23, (int)$m[1])) : 9) ?>,
          tz: <?= json_encode(setting('shop_timezone', 'Europe/Moscow')) ?>,
          nightText: <?= json_encode(setting('countdown_night_text', 'Ночь. Заказ примем сейчас — доставим сегодня после 9:00'), JSON_UNESCAPED_UNICODE) ?>,
          countdownText: <?= json_encode(setting('countdown_text', 'Успейте заказать сегодня — осталось {T} до {D}'), JSON_UNESCAPED_UNICODE) ?>,
          closedText: <?= json_encode(setting('countdown_closed_text', 'Приём заказов на сегодня закрыт — доставим завтра с 9:00'), JSON_UNESCAPED_UNICODE) ?>,
          freeDeliveryThreshold: <?= (int) setting('free_delivery_threshold', '0') ?>
        };</script>
      </div>
      <div class="fc-hero__side">
        <?php /* Промо-карточка (жёлтая, 5cv) — W96-fix1 (F4): честный дефолт «Открытка
           в подарок» (подписки в магазине нет, открытка — правда есть) */ ?>
        <?php if ($heroPromoOn): ?>
        <?php /* W103 (6-b): миниатюра в промо-карточке (84–96px, radius 12) —
               setting('hero_promo_image') с дефолтом gen20.jpg; пусто/файла нет —
               карточка без фото, как раньше */ ?>
        <?php $heroPromoUrl = site_image_url(setting('hero_promo_image', 'img/products/gen20.jpg')); ?>
        <div class="fc-hero__promo">
          <?php if ($heroPromoUrl !== ''): ?><img class="fc-hero__promo-img" src="<?= e($heroPromoUrl) ?>" alt="" loading="lazy" decoding="async" width="96" height="96"><?php endif; ?>
          <span class="fc-hero__promo-eyebrow"><?= e(setting('hero_promo_badge', 'Всегда бесплатно')) ?></span>
          <h2 class="fc-hero__promo-title"><?= e(setting('hero_promo_title', 'Открытка в подарок')) ?></h2>
          <p class="fc-hero__promo-text"><?= e(setting('hero_promo_text', 'Напишем ваш текст от руки и вложим в букет — бесплатно, в каждом заказе')) ?></p>
          <a class="fc-hero__promo-btn" href="<?= e(safe_url(setting('hero_promo_link', '#catalog'))) ?>"><?= e(setting('hero_promo_btn_text', 'Выбрать букет')) ?></a>
        </div>
        <?php endif; ?>
        <?php /* Карточка доставки: сроки по городу (W96-fix1/F4 — без обещания «1–2 часа») */ ?>
        <?php if ($heroDeliveryOn): ?>
        <div class="fc-hero__delivery">
          <span class="fc-hero__delivery-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 6h13v9H1zM14 9h4.5L21 12v3h-7z"/><circle cx="5.5" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/></svg></span>
          <span class="fc-hero__delivery-body">
          <span class="fc-hero__delivery-title"><?= e(setting('hero_delivery_title', 'Доставка в день заказа')) ?></span>
          <span class="fc-hero__delivery-text"><?= e(setting('hero_delivery_text', 'По Санкт-Петербургу — оформите до 20:00, привезём сегодня')) ?></span>
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php /* W103 (F1): MARQUEE-лента (ink) сразу под hero — ритм-разделитель перед
     витринными секциями. Тексты — существующие marquee_1..4; каждая «половина»
     трека повторяет набор ×3 (ширины хватает на 1920px+ без шва), вторая
     половина aria-hidden — скринридер не читает дубль. */ ?>
  <?php if ($featMarquee && $marqueeItems !== []): ?>
  <div class="fc-marquee">
    <div class="fc-marquee__track">
      <?php for ($mqRep = 0; $mqRep < 2; $mqRep++): ?>
      <div class="fc-marquee__half"<?= $mqRep === 1 ? ' aria-hidden="true"' : '' ?>>
        <?php for ($mqSet = 0; $mqSet < 3; $mqSet++): foreach ($marqueeItems as $mqText): ?>
        <span class="fc-marquee__item"><?= e($mqText) ?></span><span class="fc-marquee__sep" aria-hidden="true">&#10047;</span>
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

  <!-- ЧИПЫ ЦЕН (5cv): Хиты / До N / N–M / От M / Премиум — фильтруют каталог (js/five.js) -->
  <?php if ($featChips): ?>
  <div class="wrap">
    <div class="fc-chips" id="fcChips">
      <button type="button" class="fc-chip" data-chip="hit" aria-pressed="false">Хиты</button>
      <button type="button" class="fc-chip" data-chip="low" data-max="<?= $chipsN ?>" aria-pressed="false">До <?= formatSum($chipsN) ?> ₽</button>
      <button type="button" class="fc-chip" data-chip="mid" data-min="<?= $chipsN ?>" data-max="<?= $chipsM ?>" aria-pressed="false"><?= formatSum($chipsN) ?>–<?= formatSum($chipsM) ?> ₽</button>
      <button type="button" class="fc-chip" data-chip="high" data-min="<?= $chipsM ?>" aria-pressed="false">От <?= formatSum($chipsM) ?> ₽</button>
      <button type="button" class="fc-chip" data-chip="premium" aria-pressed="false">Премиум</button>
      <span class="fc-chips__count" aria-live="polite" style="flex:none;align-self:center;white-space:nowrap;font-size:.85rem;font-weight:600;color:var(--ink-muted)"></span>
    </div>
  </div>
  <?php endif; ?>

  <?php /* W96 (5cv): trust-strip убран — роль играют чипы и карточка доставки в hero.
     W103 (F1): marquee ВЕРНУЛСЯ (см. блок выше) — ритм-разделитель под hero. */ ?>

  <!-- СЕКЦИИ-КАРУСЕЛИ (5cv → W103): хиты → 2 категорийные → manifesto → премиум-dark → до N ₽ -->
  <?php if ($featCarousels): ?>
    <?php if ($featSectionHits): render_fc_row(
        setting('section_hits_title', 'Хиты продаж'),
        setting('section_hits_sub', 'Букеты, которые выбирают чаще всего'),
        $hitProducts, $cardCtx, '', 'hit', '', setting('section_hits_eyebrow', 'Выбор|покупателей'));
    endif; ?>
    <?php foreach ($categoryRows as $cr): render_fc_row(
        $cr['name'], '', $cr['items'], $cardCtx, '', '', $cr['slug']);
    endforeach; ?>
  <?php endif; ?>

  <?php /* W103 (F1): MANIFESTO — full-bleed фотополоса после двух категорийных
     каруселей (ритм: карусель → карусель → имидж-полоса). Фото: img/editorial/
     florist-hands.jpg; пока файла нет — фолбэк gen9.jpg (пионы) — заменить, когда
     другой агент положит руки флориста. */ ?>
  <?php
  $manifestoImg = '/img/editorial/florist-hands.jpg';
  if (!is_file(BASE_PATH . $manifestoImg)) {
      $manifestoImg = '/img/products/gen9.jpg'; /* TODO(W103): фолбэк до florist-hands.jpg */
  }
  ?>
  <section class="fc-manifesto reveal" aria-label="<?= e(setting('manifesto_kicker', 'Наши принципы')) ?>">
    <img class="fc-manifesto__img" src="<?= e($manifestoImg) ?>" alt="" loading="lazy" decoding="async">
    <div class="fc-manifesto__content">
      <p class="fc-manifesto__kicker"><?= e(setting('manifesto_kicker', 'Наши принципы')) ?></p>
      <p class="fc-manifesto__text"><?= e(setting('manifesto_text', 'Собираем букеты утром — и везём вам сегодня')) ?></p>
    </div>
  </section>

  <?php /* W103 (F1): ПРЕМИУМ — тёмный редакционный разворот вместо карусели.
     Данные те же (is_premium=1 / топ-3 по цене), гейт feature_section_premium.
     Фон: img/editorial/petals-macro.jpg с тёмным оверлеем (нет файла — чистый ink). */ ?>
  <?php if ($featSectionPremium && $premiumProducts !== [] && $premiumMinPrice > 0): ?>
  <?php $fcProdSeq++; /* участник чередования фонов товарных секций */ ?>
  <section class="fc-premium reveal" id="premium"<?= is_file(BASE_PATH . '/img/editorial/petals-macro.jpg') ? ' style="background-image:url(\'/img/editorial/petals-macro.jpg\')"' : '' ?>>
    <div class="wrap">
      <div class="fc-premium__grid">
        <div class="fc-premium__cards">
          <?php foreach (array_slice($premiumProducts, 0, 3) as $prIdx => $prP): ?>
          <a class="fc-premium__card<?= $prIdx === 0 ? ' fc-premium__card--lead' : '' ?>" href="/product/<?= e(rawurlencode($prP['slug'])) ?>">
            <span class="fc-premium__photo"><?php render_premium_picture($prP, '(max-width:899px) 72vw, (min-width:900px) 400px'); ?></span>
            <span class="fc-premium__card-body">
              <span class="fc-premium__card-name"><?= e($prP['name']) ?></span>
              <span class="fc-premium__card-price"><?= formatPrice(productPrice($prP)) ?></span>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <div class="fc-premium__info">
          <h2 class="fc-premium__title"><?= e(setting('premium_title', 'Для особых случаев')) ?></h2>
          <p class="fc-premium__sub"><?= e(setting('premium_sub', 'Крупные композиции из гортензий, пионов и орхидей — когда впечатление важнее бюджета')) ?></p>
          <p class="fc-premium__price-row">
            <span class="fc-premium__price-label"><?= e(setting('premium_price_label', 'Букеты от')) ?></span>
            <span class="fc-premium__price"><?= formatSum($premiumMinPrice) ?> <span class="fc-premium__price-cur">&#8381;</span></span>
          </p>
          <a class="fc-btn fc-premium__cta" href="#catalog" data-chip="premium"><?= e(setting('premium_cta_text', 'Смотреть премиум')) ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($featCarousels): ?>
    <?php if ($featSectionBudget && $budgetProducts !== []): render_fc_row(
        sprintf(setting('section_budget_title', 'До %s ₽'), formatSum($chipsN)),
        setting('section_budget_sub', ''), $budgetProducts, $cardCtx, '', 'low', '', setting('section_budget_eyebrow', 'Выгодно|каждый день'));
    endif; ?>
  <?php endif; ?>

  <?php /* W103 (F1): editorial №1 — pull-цитата между «До N ₽» и «Дополните» */ ?>
  <?php render_fc_editorial(1); ?>

  <!-- «ДОПОЛНИТЕ БУКЕТ» (5cv): сопутствующие товары show_in_upsell.
       W96-fix1 (F11): секция имеет смысл от ≥2 товаров — одиночная карточка
       в карусели выглядит пусто; жёсткий фильтр, чтобы не зависеть от сида -->
  <?php if (count($addonProducts) >= 2): render_fc_row(
      setting('section_addons_title', 'Дополните букет'),
      setting('section_addons_sub', ''), $addonProducts, $cardCtx);
  endif; ?>

  <!-- КАТАЛОГ -->
  <?php /* W103 (6-b, критик-5а P0 «каталожный дамп»): editorial full-bleed полоса
         ПЕРЕД каталогом — фото лепестков + ink-оверлей + Playfair-цитата.
         Полоса — самостоятельная секция ВНЕ грида: #catalogTabs/#catalogGrid
         и catalog-filter.js не затронуты (фильтры работают как раньше). */ ?>
  <?php $catalogStripText = trim((string)setting('catalog_strip_text', 'Каждый букет собираем утром — и фотографируем перед отправкой')); ?>
  <?php if ($catalogStripText !== ''): ?>
  <section class="fc-catalog-strip" aria-label="О сборке букетов">
    <?php if (is_file(BASE_PATH . '/img/editorial/petals-macro.jpg')): ?>
    <img class="fc-catalog-strip__img" src="/img/editorial/petals-macro.jpg" alt="" loading="lazy" decoding="async">
    <?php endif; ?>
    <div class="wrap fc-catalog-strip__inner">
      <p class="fc-catalog-strip__text reveal"><?= e($catalogStripText) ?></p>
    </div>
  </section>
  <?php endif; ?>
  <?php /* W97-fixB2 (B2-5а): каталог — тоже товарная секция, продолжает чередование фонов */ ?>
  <?php $fcProdSeq++; ?>
  <section class="fc-section<?= ($fcProdSeq % 2 === 0) ? ' fc-section--tint' : '' ?>" id="catalog">
    <div class="wrap">
      <div class="fc-row__head">
        <h2 class="fc-row__title"><?= e(setting('catalog_title', 'Каталог')) ?></h2>
        <p class="fc-row__sub"><?= e(setting('catalog_subtitle', 'Соберём и доставим букет в день заказа')) ?></p>
      </div>
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
      </div>
      <?php /* Empty-state (критик P2): при 0 карточек от фильтров — подсказка + сброс. Тексты редактируются (критерий 16). */ ?>
      <div class="catalog-empty" id="catalogEmpty" hidden style="text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px;margin-top:14px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px"><?= e(setting('catalog_empty_title', 'По этим фильтрам букетов не нашлось 🌷')) ?></p>
        <p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px"><?= e(setting('catalog_empty_hint', 'Попробуйте убрать фильтр цены или выбрать другую категорию')) ?></p>
        <button type="button" class="btn btn--outline" id="catalogEmptyReset">Сбросить фильтры</button>
      </div>
    </div>
  </section>
  <?php /* W103 (F1): editorial №2 — pull-цитата между каталогом и поводами
         (позиция ритма W103; guard не даст задвоить, если выше не хватило секций) */ ?>
  <?php render_fc_editorial(2); ?>

  <?php /* W96 (5cv): секция how-it-works убрана — шаги остаются в настройках, но не выводятся. */ ?>

  <!-- ЦВЕТЫ ПО ПОВОДУ (5cv): плитки-чипы с фото/пастельными фонами -->
  <?php if ($occTiles !== []): ?>
  <section class="fc-section" id="occasions">
    <div class="wrap">
      <div class="fc-row__head">
        <h2 class="fc-row__title"><?= e(setting('occasions_title', 'Цветы по поводам')) ?></h2>
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
                   тот же webp-конвейер, что у карточек (thumbs 400/600 + оригинал) */
            $__ocImg = (string)$t['photo'];
            $__ocThumb = preg_replace('/\.(jpe?g|png)$/i', '', $__ocImg);
            $__ocThumb400 = '/img/products/thumbs/' . rawurlencode($__ocThumb) . '-400.webp';
            $__ocThumb600 = '/img/products/thumbs/' . rawurlencode($__ocThumb) . '-600.webp';
            $__ocHas400 = is_file(BASE_PATH . parse_url($__ocThumb400, PHP_URL_PATH));
            $__ocHas600 = is_file(BASE_PATH . parse_url($__ocThumb600, PHP_URL_PATH));
            ?>
            <picture>
              <?php if ($__ocHas400 || $__ocHas600): ?>
              <source type="image/webp" srcset="<?= e(($__ocHas400 ? $__ocThumb400 . ' 400w' : '') . ($__ocHas400 && $__ocHas600 ? ', ' : '') . ($__ocHas600 ? $__ocThumb600 . ' 600w' : '')) ?>" sizes="(max-width:899px) 44vw, 260px">
              <?php endif; ?>
              <img class="fc-occasion__img" src="<?= e($__ocImg) ?>" alt="" loading="lazy" decoding="async">
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

  <!-- ФОРМА ЗАКАЗА (разметка не менялась — CSS перекрасит) -->
  <section class="fc-section fc-section--subtle" id="order">
    <div class="wrap">
      <h2 class="section-title"><?= e(setting('order_title', 'Оформление заказа')) ?></h2>
      <p class="order__selected" id="orderSelected"></p>
      <?php /* W98-fixE (E14): пустая корзина на #order — заглушка-подсказка. Скрыта
         по умолчанию (display:none); показывает js-волна при пустой корзине. */ ?>
      <div id="orderEmptyState" style="display:none;text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px;margin-bottom:18px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px">Корзина пока пуста — выберите букет, и форма появится здесь</p>
        <p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px">В каталоге — свежие букеты с утренней поставки и ценами на любой бюджет</p>
        <a class="btn btn--outline" href="#catalog">Перейти в каталог</a>
      </div>
      <form class="order-form" id="orderForm" novalidate>
        <div class="order-form__field">
          <label for="orderName"><?= e(setting('form_name_label', 'Ваше имя *')) ?></label>
          <input type="text" id="orderName" name="name" autocomplete="name" required minlength="2">
          <span class="order-form__error" id="orderNameError"></span>
        </div>
        <div class="order-form__field">
          <label for="orderPhone"><?= e(setting('form_phone_label', 'Телефон')) ?></label>
          <input type="tel" id="orderPhone" name="phone" autocomplete="tel" placeholder="+7 (___) ___-__-__">
          <span class="order-form__error" id="orderPhoneError"></span>
        </div>
        <div class="order-form__field">
          <label for="orderEmail"><?= e(setting('form_email_label', 'Email — для письма о заказе (необязательно)')) ?> <span id="orderEmailReq" style="color:var(--rose-cta,#AE4A71);font-weight:600" hidden>* обязательно для онлайн-оплаты</span></label>
          <input type="email" id="orderEmail" name="email" autocomplete="email" placeholder="example@mail.ru">
          <span class="order-form__hint" id="orderEmailHint" hidden>На этот адрес придёт чек об оплате</span>
          <span class="order-form__error" id="orderEmailError"></span>
        </div>
        <p class="order-form__hint" id="contactHint">Укажите телефон и/или email — как удобнее для связи</p>
        <fieldset class="order-form__nested">
          <legend><?= e(setting('fieldset_delivery_legend', 'Доставка')) ?></legend>
          <div class="order-form__field">
            <label for="orderDeliveryZone"><?= e(setting('form_delivery_zone_label', 'Как получить букет')) ?></label>
            <select id="orderDeliveryZone" name="delivery_zone">
              <?php
              /* Самовывоз — приоритетный способ: выбран по умолчанию, бесплатно.
                 Критик-мобайл (баг 4): полный адрес самовывоза — своя настройка pickup_address
                 (с фолбэком на shop_address), иначе город без улицы. */
              $pickupAddr = trim(setting('pickup_address', '')) ?: trim(setting('shop_address', ''));
              ?>
              <option value="0" data-price="0" selected><?= e(setting('pickup_option_text', 'Самовывоз · 0 ₽')) ?></option>
              <?php /* W70 (obvious-витрина NEW-1): native select режет длинные имена зон на 390
                     (366px в 220px-бокс без эллипсиса). Корень — метка: «район»→«р-н»,
                     пояснение в скобках и цена уходят в title/hint (цена есть в «К оплате»). */
                 foreach ($zones as $z):
                 $zFull = (string)$z['name'];
                     $zShort = trim(str_replace([' район ', ' район'], [' р-н ', ' р-н'], $zFull));
                     $zShort = trim((string)preg_replace('/\s*\([^)]*\)/u', '', $zShort));
                     ?>
                <?php /* W97-fixB2 (B2-1): цена зоны в тексте опции — как у «Самовывоз · 0 ₽»
                       (formatPrice — тысячи через пробел); value/data-price НЕ трогаем:
                       js/order-form.js берёт data-price, value=id зоны. Имена зон короткие,
                       W70-сокращение (р-н) сохранено. */ ?>
                <option value="<?= (int)$z['id'] ?>" data-price="<?= (int)$z['price'] ?>" title="<?= e($zFull) ?> · <?= (int)$z['price'] ?> ₽"><?= e($zShort . ' · ' . formatPrice((int)$z['price'])) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($pickupAddr !== ''): ?>
            <p class="order-form__hint" id="orderPickupAddr" style="margin-top:6px">Адрес самовывоза: <?= e($pickupAddr) ?> · букет будет готов в течение дня, предупредим по телефону</p>
            <?php endif; ?>
          </div>
          <div class="order-form__field" id="orderDeliveryAddressField" hidden>
            <label for="orderDeliveryAddress">Адрес доставки</label>
            <input type="text" id="orderDeliveryAddress" name="delivery_address" placeholder="Улица, дом, квартира" autocomplete="street-address">
            <span class="order-form__error" id="orderDeliveryAddressError"></span>
          </div>
          <p class="order-form__hint" id="orderDeliveryHint"><?= e(setting('delivery_hint_text', 'Доставим в течение дня, время согласуем по телефону')) ?></p>
        </fieldset>
        <fieldset class="order-form__payment">
          <legend><?= e(setting('fieldset_payment_legend', 'Способ оплаты')) ?></legend>
          <?php
          /* Fail-safe: «онлайн» показываем только если ЮKassa реально настроена
             (галочка + ключи). Иначе покупатель увидит несбыточное обещание. */
          $ykLive = setting('yk_enabled', '0') === '1'
              && trim(setting('yk_shop_id', '')) !== ''
              && trim(setting('yk_secret_key', '')) !== '';
          ?>
          <?php if ($ykLive): ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="online" checked><span><?= e(setting('pay_online_label', 'Картой или через СБП — сразу онлайн')) ?></span></label>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash"><span><?= e(setting('pay_cash_label', 'Наличными или картой при получении')) ?></span></label>
          <p class="order-form__hint">Оплата проходит на защищённой странице ЮKassa. Данные карты магазину не передаются.</p>
          <?php else: ?>
          <?php /* Покупатель-критик W46: radio «При получении» уже даёт payment_method —
                    hidden-дубль с тем же именем создавал двойное значение в FormData. */ ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash" checked><span><?= e(setting('pay_cash_label', 'Наличными или картой при получении')) ?></span></label>
          <p class="order-form__hint">Оплата — курьеру при получении заказа.</p>
          <?php endif; ?>
        </fieldset>
        <div class="order-form__field">
          <label for="orderComment">Комментарий</label>
          <textarea id="orderComment" name="comment" rows="3" placeholder="Цветовые пожелания, подъезд, домофон"></textarea>
        </div>
        <?php if ($featGiftFields): ?>
        <?php /* Критик functional (gift-UX): цветы дарят — кому и что написать на открытке.
                 Все поля необязательные; пустые просто не попадают в заказ. */ ?>
        <fieldset class="order-form__payment" style="margin-top:4px">
          <legend>Кому дарим (необязательно)</legend>
          <div class="order-form__field">
            <label for="orderRecipientName">Имя получателя</label>
            <input type="text" id="orderRecipientName" name="recipient_name" maxlength="120" autocomplete="off" placeholder="Например: Анна">
          </div>
          <div class="order-form__field">
            <label for="orderRecipientPhone">Телефон получателя</label>
            <input type="tel" id="orderRecipientPhone" name="recipient_phone" autocomplete="off" placeholder="+7 (___) ___-__-__">
            <span class="order-form__error" id="orderRecipientPhoneError"></span>
            <p class="order-form__hint">Курьер позвонит получателю, а не вам</p>
          </div>
          <div class="order-form__field">
            <label for="orderCardText">Текст открытки</label>
            <textarea id="orderCardText" name="card_text" rows="2" maxlength="500" placeholder="С днём рождения! ❤️ — от Евгения"></textarea>
            <p class="order-form__hint">Напишем от руки и вложим в букет · бесплатно</p>
          </div>
        </fieldset>
        <?php endif; ?>
        <?php if ($featDeliverySlots): ?>
        <?php /* Критик functional top#1: слоты даты/времени вместо «договоримся по телефону» */ ?>
        <fieldset class="order-form__payment" style="margin-top:4px">
          <legend>Когда доставить (необязательно)</legend>
          <div class="order-form__field">
            <label for="orderDeliveryDate">Дата</label>
            <?php /* W96-fix4: min=today — не даём выбрать прошлое до отправки (сервер валидирует повторно).
                   W97-fixB2 (B2-2): max=today+60 — нативный календарь не предлагает бесконечное будущее. */ ?>
            <input type="date" id="orderDeliveryDate" name="delivery_date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>">
            <span class="order-form__error" id="orderDeliveryDateError"></span>
          </div>
          <div class="order-form__field">
            <label for="orderDeliverySlot">Интервал</label>
            <select id="orderDeliverySlot" name="delivery_slot">
              <option value="">Любое время дня</option>
              <?php foreach (array_filter(array_map('trim', explode("\n", setting('delivery_slots', "Утро 9:00–14:00\nДень 14:00–18:00\nВечер 18:00–22:00")))) as $slotOption): ?>
                <option value="<?= e($slotOption) ?>"><?= e($slotOption) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </fieldset>
        <?php endif; ?>
        <?php /* Honeypot (критик security): невидимое поле — боты заполняют, люди нет */ ?>
        <div class="hp-field" aria-hidden="true" inert><label for="orderCompanyWebsite">Сайт компании</label><input type="text" id="orderCompanyWebsite" name="company_website" tabindex="-1" autocomplete="off"></div>
        <label class="order-form__checkbox">
          <input type="checkbox" id="orderPdConsent" name="pd_consent" required>
          <?php /* W98-fixE (E12): точный перечень данных, без bold на «трансграничную»,
             мессенджеры — одной фразой, точка в конце; имя ссылки = H1 политики */ ?>
          <span>Я даю согласие на обработку персональных данных (имя, телефон, email, адрес доставки, имя и телефон получателя, текст открытки, комментарий к заказу, IP-адрес) в целях оформления и доставки заказа, а также ведения истории заказов для отслеживания и сервисных уведомлений, включая передачу курьерской службе, платёжному сервису (при онлайн-оплате) и трансграничную передачу в Telegram Bot API (при включённых уведомлениях магазина), на условиях <a href="/policy" target="_blank" rel="noopener">Политики обработки персональных данных</a> и <a href="/offer" target="_blank" rel="noopener">Публичной оферты</a>.</span>
        </label>
        <span class="order-form__error" id="orderPdConsentError"></span>
        <p class="order-form__total" id="orderTotal"></p>
        <button type="submit" class="btn btn--accent order-form__submit" id="orderSubmit"
                data-pay-label="<?= e(setting('submit_button_text', 'Оплатить заказ')) ?>"
                data-nopay-label="<?= e(setting('submit_nopay_text', 'Отправить заказ')) ?>"><?= $ykLive ? e(setting('submit_button_text', 'Оплатить заказ')) : e(setting('submit_nopay_text', 'Отправить заказ')) ?></button>
        <p class="order-form__hint"><?= e(sprintf('Заказы до %s — доставим сегодня; после %s — привезём завтра с утра.', setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT), setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT))) ?></p>
        <p class="order-form__status" id="orderStatus" role="status" hidden></p>
      </form>
    </div>
  </section>

  <!-- НАШИ МАГАЗИНЫ (5cv): карточки адресов -->
  <?php if ($featStores): ?>
  <section class="fc-section">
    <div class="wrap">
      <div class="fc-row__head">
        <h2 class="fc-row__title"><?= e(setting('stores_title', 'Наши магазины в Петербурге')) ?></h2>
        <p class="fc-row__sub"><?= e(setting('stores_sub', 'Заберите сами или закажите доставку — букет будет готов в течение дня')) ?></p>
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

  <!-- SEO-ТЕКСТ (5cv): sanitize_rich_text разрешает только <a>; абзацы — через \n\n → <p> (стилизует .fc-seo p).
       W103 (6-b, критик-5а P0 «низ главной»): колофон-стиль — тихий caps-заголовок,
       первые два абзаца снаружи, остальной текст в <details> (в HTML остаётся весь
       текст — SEO не страдает); summary — Playfair-курсив. -->
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
      <h2 class="section-title"><?= e(setting('faq_title', 'Частые вопросы')) ?></h2>
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

  <!-- ЖУРНАЛ (5cv): карточки статей; по умолчанию выключен — контента нет -->
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
          <?php if ($j['image'] !== ''): ?><div class="fc-journal__media"><img src="/img/uploads/<?= e(rawurlencode($j['image'])) ?>" alt="" loading="lazy" decoding="async"></div><?php endif; ?>
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
