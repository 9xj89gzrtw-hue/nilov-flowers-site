<?php
/* Главная витрина (редизайн 5cv, W96): город-бар, hero-бенто, чипы цен,
   карусели секций, каталог+вкладки, поводы, форма заказа, магазины, SEO-текст,
   FAQ, журнал. Бегущая строка / how-it-works / отзывы — убраны (логика 5cv). */
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
$heroH1 = setting('seo_h1', setting('hero_title', 'Доставка цветов в Санкт-Петербурге'));
$heroBtnText = trim(setting('hero_button_text', 'Выбрать букет'));
$heroBtnLink = trim(setting('hero_button_link', '#catalog'));
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
   может не быть в старой БД — читаем через ?? 0. */
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
        $srcset = $thumb . ' 400w, ' . $imgWebp . ' ' . $origW . 'w';
    } elseif ($thumb !== '') {
        $srcset = $thumb . ' 400w';
    } elseif ($imgWebp !== '') {
        $srcset = $imgWebp;
    } else {
        $srcset = '';
    }
    $sizes = '(max-width:899px) 45vw, (min-width:900px) 300px, 280px';
    $link = '/product/' . rawurlencode($p['slug']);
    /* W96-fix1 (F3): поисковый индекс карточки — имя + категория + описание
       (нижний регистр; js/five.js матчит по стемму запроса как подстроке) */
    $searchIndex = mb_strtolower(trim($p['name'] . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['description'] ?? '')));
    ?>
        <article class="product-card reveal" data-category-id="<?= (int)($p['category_id'] ?? 0) ?>" data-price="<?= (int)$price ?>" data-hit="<?= (int)($p['is_hit'] ?? 0) ?>" data-premium="<?= (int)($p['is_premium'] ?? 0) ?>" data-search="<?= e($searchIndex) ?>">
          <div class="product-card__media">
            <a class="product-card__media-link" href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>">
              <?php if ($img !== ''): ?>
                <picture>
                  <?php if ($srcset !== ''): ?><source type="image/webp" srcset="<?= e($srcset) ?>"<?= $thumb !== '' ? ' sizes="' . e($sizes) . '"' : '' ?>><?php endif; ?>
                  <img class="product-card__img" src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
                </picture>
              <?php else: ?>
                <svg viewBox="0 0 80 94" style="width:30%;margin:auto;color:var(--blue)" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="40" cy="30" r="11"/><circle cx="26" cy="38" r="8"/><circle cx="54" cy="38" r="8"/><path d="M40 41v20M40 61c-8 6-14 14-16 25M40 61c8 6 14 14 16 25"/></svg>
              <?php endif; ?>
            </a>
            <?php if ($isSale): $offPct = (int)$p['price'] > 0 ? (int)round((1 - $price / (int)$p['price']) * 100) : 0; ?><span class="product-card__badge product-card__badge--sale"><?= e(setting('badge_sale_text', 'Акционная цена')) ?><?php if ($offPct > 0): ?> −<?= $offPct ?>%<?php endif; ?></span><?php endif; ?>
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
   (js/five.js по data-chip), у категорийных секций — вкладку (data-tab, как раньше). */
function render_fc_row(string $title, string $sub, array $items, array $ctx, string $tabId = '', string $chip = ''): void
{
    if ($items === []) return;
    ?>
    <section class="fc-section"><div class="wrap">
      <div class="fc-row"><div class="fc-row__head">
        <h2 class="fc-row__title"><?= e($title) ?></h2>
        <?php if ($sub !== ''): ?><p class="fc-row__sub"><?= e($sub) ?></p><?php endif; ?>
        <a class="fc-row__link" href="#catalog"<?= $tabId !== '' ? ' data-tab="' . e($tabId) . '"' : '' ?><?= $chip !== '' ? ' data-chip="' . e($chip) . '"' : '' ?>>Смотреть все</a>
        <div class="fc-row__arrows">
          <button class="fc-row__arrow" type="button" aria-label="Назад"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
          <button class="fc-row__arrow fc-row__arrow--next" type="button" aria-label="Вперёд"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
        </div>
      </div>
      <div class="fc-carousel">
        <?php foreach ($items as $item) { render_product_card($item, $ctx); } ?>
      </div></div>
    </div></section>
    <?php
}

$cardCtx = ['featDeliveryBadge' => $featDeliveryBadge, 'featFavorites' => $featFavorites];

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
        $categoryRows[] = ['id' => $cid, 'name' => (string)$c['name'], 'items' => array_slice($productsByCat[$cid], 0, 12)];
    }
}

/* Премиум: is_premium=1; если пусто — топ-3 самых дорогих (по цене после скидки) */
$premiumProducts = array_values(array_filter($products, static fn (array $p): bool => (int)($p['is_premium'] ?? 0) === 1));
if ($premiumProducts === []) {
    $byPrice = $products;
    usort($byPrice, static fn (array $a, array $b): int => productPrice($b) <=> productPrice($a));
    $premiumProducts = array_slice($byPrice, 0, 3);
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
$seoTextDefault = "Доставка цветов по Санкт-Петербургу — в день заказа. Работаем по всем районам города: в пределах КАД привозим букет за 1–2 часа, в пригороды — Пушкин, Павловск, Гатчина, Всеволожск — в согласованный интервал. Оформите заказ до 20:00, и цветы будут у получателя сегодня же.\n\nСвежесть — главное. Цветы приходят к нам с утренней поставки, а не лежат на складе: букет собираем непосредственно перед отправкой. Перед выездом курьера пришлём фото готовой композиции — вы увидите именно то, что получит адресат. Если какой-то цветок выглядит не идеально, заменим его до доставки.\n\nСпособ оплаты выберете при оформлении: наличными курьеру при получении или онлайн — если доступен в заказе. Поводы бывают разные: букет маме на день рождения, извиниться, поздравить коллегу или сказать «люблю» без повода — подскажем состав под бюджет и характер события. А если сомневаетесь — просто позвоните, соберём букет вместе по телефону.";
?><!DOCTYPE html>
<html lang="ru">
<head>
<title><?= e(setting('seo_title', 'Доставка цветов в СПб — ' . setting('shop_name', 'Nilov Flowers') . ' | Свежие букеты с доставкой сегодня')) ?></title>
<meta name="description" content="<?= e(setting('seo_description', 'Доставка букетов по Санкт-Петербургу в день заказа. Свежие цветы с утренней поставки, фото перед отправкой. Заказы до 20:00 — доставим сегодня.')) ?>">
<meta property="og:title" content="<?= e(setting('seo_title', 'Доставка цветов в СПб — ' . setting('shop_name', 'Nilov Flowers') . ' | Свежие букеты с доставкой сегодня')) ?>">
<meta property="og:description" content="Букеты с доставкой в день заказа по Санкт-Петербургу. Фото перед отправкой, свежие цветы с утренней поставки.">
<meta property="og:url" content="https://flowers.interfood-catering.ru/">
<?= setting('hero_image') !== '' ? '<meta property="og:image" content="https://flowers.interfood-catering.ru/img/uploads/' . e(rawurlencode(setting('hero_image'))) . '">' : '' ?>
<?php /* W96-fix3a (T5d): $pageDescription → twitter:description в partials/head.php
   (парно к og:description; значение — редактируемый из админки seo_description) */ ?>
<?php $pageDescription = setting('seo_description', 'Доставка букетов по Санкт-Петербургу в день заказа. Свежие цветы с утренней поставки, фото перед отправкой. Заказы до 20:00 — доставим сегодня.'); ?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php /* JSON-LD Florist — canonical 2026 (hanafloristpos.com/schema-guide, thestacc.com/local-business-schema) */ ?>
<?php
/* LCP-preload: hero.webp если существует (фолбэк — jpg) */
$__heroPre = setting('hero_image');
if ($__heroPre !== '') {
    $__heroWebp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $__heroPre);
    $__heroPreHref = ($__heroWebp !== $__heroPre && is_file(IMG_UPLOADS_DIR . '/' . $__heroWebp))
        ? '/img/uploads/' . rawurlencode($__heroWebp)
        : '/img/uploads/' . rawurlencode($__heroPre);
    echo '<link rel="preload" as="image" href="' . e($__heroPreHref) . '" fetchpriority="high">' . "\n";
}
?>
<script type="application/ld+json">
<?= json_encode([
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
    'openingHoursSpecification' => [[
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        'opens' => '09:00',
        'closes' => '21:00',
    ]],
    'image' => setting('hero_image', '') !== '' ? 'https://flowers.interfood-catering.ru/img/uploads/' . rawurlencode(setting('hero_image')) : '',
] + (setting('yandex_reviews_id') !== '' ? ['sameAs' => ['https://yandex.ru/maps/org/' . setting('yandex_reviews_id')]] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<?php /* Город-бар (5cv): подтверждение города, JS five.js прячет по localStorage 'fc-city-ok' */ ?>
<?php if ($featCitybar): ?>
<div class="fc-citybar" id="fcCitybar">
  <div class="wrap fc-citybar__inner">
    <span class="fc-citybar__text"><?= e(setting('citybar_text', 'Ваш город — Санкт-Петербург?')) ?></span>
    <button type="button" class="fc-citybar__yes" id="fcCityYes">Да, верно</button>
    <a class="fc-citybar__no" href="#contacts">Выбрать другой</a>
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
      /* Hero в WebP если есть (LCP-критично: 163KB webp vs 509KB jpg), фолбэк jpg */
      $heroImg = setting('hero_image');
      $heroWebp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $heroImg);
      $heroWebpOk = $heroWebp !== $heroImg && is_file(IMG_UPLOADS_DIR . '/' . $heroWebp);
      /* Layout-критик W35: width/height на <img> — браузер резервирует box до загрузки */
      $heroDim = $heroImg !== '' ? (@getimagesize(IMG_UPLOADS_DIR . '/' . $heroImg) ?: null) : null;
      ?>
      <div class="fc-hero__main<?= $heroImg === '' ? ' fc-hero__main--fallback' : '' ?>">
        <?php if ($heroImg !== ''): ?>
        <picture>
          <?php if ($heroWebpOk): ?><source type="image/webp" srcset="/img/uploads/<?= e(rawurlencode($heroWebp)) ?>"><?php endif; ?>
          <img class="fc-hero__img" src="/img/uploads/<?= e($heroImg) ?>" alt="<?= e($heroH1) ?>" fetchpriority="high"<?= $heroDim ? ' width="' . (int)$heroDim[0] . '" height="' . (int)$heroDim[1] . '"' : '' ?>>
        </picture>
        <?php endif; ?>
        <div class="fc-hero__content">
        <?php if ($heroTextEnabled): ?>
        <p class="fc-hero__eyebrow"><?= e(setting('hero_eyebrow', 'Санкт-Петербург · доставка в день заказа')) ?></p>
        <h1 class="fc-hero__title"><?= e($heroH1) ?></h1>
        <p class="fc-hero__sub"><?= e(setting('hero_subtitle', 'Соберём и доставим букет в течение дня — к празднику или просто так')) ?></p>
        <?php endif; ?>
        <?php if ($heroBtn): ?>
        <a class="fc-hero__cta" href="<?= e($heroBtnLink) ?>">
          <?= e($heroBtnText) ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <?php endif; ?>
        <?php /* Таймер «до 20:00» — не зависит от hero-текста (юр-независимый элемент). Отключаем (критерий 16). */ ?>
        <?php if ($featCountdown): ?><p class="hero__deadline" style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:8px 16px;border-radius:999px;background:rgba(255,255,255,.75);backdrop-filter:blur(6px);border:1px solid var(--line);font-size:.9rem;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums;max-width:100%;min-height:38px"><?= e(str_replace(['{T}', '{D}'], ['00 ч 00 мин', setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT)], setting('countdown_text', 'Успейте заказать сегодня — осталось {T} до {D}'))) /* W80 (obvious-витрина NEW-1): пре-JS paint без токенов */ ?></p><?php endif; ?>
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
        <div class="fc-hero__promo">
          <span class="fc-hero__promo-eyebrow"><?= e(setting('hero_promo_badge', 'Всегда')) ?></span>
          <h2 class="fc-hero__promo-title"><?= e(setting('hero_promo_title', 'Открытка в подарок')) ?></h2>
          <p class="fc-hero__promo-text"><?= e(setting('hero_promo_text', 'Напишем ваш текст от руки и вложим в букет — бесплатно, в каждом заказе')) ?></p>
          <a class="fc-hero__promo-btn" href="<?= e(setting('hero_promo_link', '#order')) ?>"><?= e(setting('hero_promo_btn_text', 'Оформить заказ')) ?></a>
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

  <?php /* W96-fix1 (F8): траст-ряд под hero — гарантии с галочками (гейт hero-текста,
     те же guarantee_1..3, что на странице товара; прячется вместе с hero-текстом) */ ?>
  <?php if ($heroTextEnabled && $guarantees !== []): ?>
  <div class="wrap">
    <ul class="fc-trust" aria-label="Наши гарантии">
      <?php foreach (array_slice($guarantees, 0, 3) as $g): ?>
      <li class="fc-trust__item"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="currentColor"/><path d="M7 12.5l3.2 3.2L17 9" stroke="#fff" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg><?= e($g) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <!-- ЧИПЫ ЦЕН (5cv): Хиты / До N / N–M / От M / Премиум — фильтруют каталог (js/five.js) -->
  <?php if ($featChips): ?>
  <div class="wrap">
    <div class="fc-chips" id="fcChips">
      <button type="button" class="fc-chip" data-chip="hit" aria-pressed="false">Хиты</button>
      <button type="button" class="fc-chip" data-chip="low" data-max="<?= $chipsN ?>" aria-pressed="false">До <?= number_format($chipsN, 0, ',', ' ') ?> ₽</button>
      <button type="button" class="fc-chip" data-chip="mid" data-min="<?= $chipsN ?>" data-max="<?= $chipsM ?>" aria-pressed="false"><?= number_format($chipsN, 0, ',', ' ') ?>–<?= number_format($chipsM, 0, ',', ' ') ?> ₽</button>
      <button type="button" class="fc-chip" data-chip="high" data-min="<?= $chipsM ?>" aria-pressed="false">От <?= number_format($chipsM, 0, ',', ' ') ?> ₽</button>
      <button type="button" class="fc-chip" data-chip="premium" aria-pressed="false">Премиум</button>
      <span class="fc-chips__count" aria-live="polite" style="flex:none;align-self:center;white-space:nowrap;font-size:.85rem;font-weight:600;color:var(--ink-muted)"></span>
    </div>
  </div>
  <?php endif; ?>

  <?php /* W96 (5cv): marquee и trust-strip убраны — их роль играют чипы и карточка доставки в hero. */ ?>

  <!-- СЕКЦИИ-КАРУСЕЛИ (5cv): хиты → категории → премиум → до N ₽ -->
  <?php if ($featCarousels): ?>
    <?php if ($featSectionHits): render_fc_row(
        setting('section_hits_title', 'Хиты продаж'),
        setting('section_hits_sub', 'Букеты, которые выбирают чаще всего'),
        $hitProducts, $cardCtx, '', 'hit');
    endif; ?>
    <?php foreach ($categoryRows as $cr): render_fc_row(
        $cr['name'], '', $cr['items'], $cardCtx, (string)$cr['id']);
    endforeach; ?>
    <?php if ($featSectionPremium): render_fc_row(
        setting('section_premium_title', 'Премиум — для особого случая'),
        setting('section_premium_sub', ''), $premiumProducts, $cardCtx, '', 'premium');
    endif; ?>
    <?php if ($featSectionBudget && $budgetProducts !== []): render_fc_row(
        sprintf(setting('section_budget_title', 'До %s ₽'), number_format($chipsN, 0, ',', ' ')),
        setting('section_budget_sub', ''), $budgetProducts, $cardCtx, '', 'low');
    endif; ?>
  <?php endif; ?>

  <!-- «ДОПОЛНИТЕ БУКЕТ» (5cv): сопутствующие товары show_in_upsell.
       W96-fix1 (F11): секция имеет смысл от ≥2 товаров — одиночная карточка
       в карусели выглядит пусто; жёсткий фильтр, чтобы не зависеть от сида -->
  <?php if (count($addonProducts) >= 2): render_fc_row(
      setting('section_addons_title', 'Дополните букет 🎈'),
      setting('section_addons_sub', ''), $addonProducts, $cardCtx);
  endif; ?>

  <!-- КАТАЛОГ -->
  <section class="fc-section" id="catalog">
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
            <option value="low" data-max="<?= $pfLow ?>">до <?= number_format($pfLow, 0, '', ' ') ?> ₽</option>
            <option value="mid" data-min="<?= $pfLow ?>" data-max="<?= $pfHigh ?>"><?= number_format($pfLow, 0, '', ' ') ?>–<?= number_format($pfHigh, 0, '', ' ') ?> ₽</option>
            <option value="high" data-min="<?= $pfHigh ?>">от <?= number_format($pfHigh, 0, '', ' ') ?> ₽</option>
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
                 data-fallback="<?= e(setting('zone_check_fallback', 'не нашли — уточним по телефону')) ?>"
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

  <?php /* W96 (5cv): секция how-it-works убрана — шаги остаются в настройках, но не выводятся. */ ?>

  <!-- ЦВЕТЫ ПО ПОВОДУ (5cv): плитки-чипы с фото/пастельными фонами -->
  <?php if ($occTiles !== []): ?>
  <section class="fc-section" id="occasions">
    <div class="wrap">
      <div class="fc-row__head">
        <h2 class="fc-row__title"><?= e(setting('occasions_title', 'Цветы по поводу')) ?></h2>
      </div>
      <div class="fc-occasions">
        <?php foreach ($occTiles as $t): ?>
        <a class="fc-occasion<?= $t['photo'] !== '' ? ' fc-occasion--photo' : ' fc-occasion--pastel' ?>" href="/occasion/<?= e(rawurlencode($t['slug'])) ?>">
          <?php if ($t['photo'] !== ''): ?>
            <?php /* W96-fix3a (T6): осмысленный alt из заголовка повода (было alt="" —
               скринридер молчал на плитке с фото) */ ?>
            <img class="fc-occasion__img" src="<?= e($t['photo']) ?>" alt="<?= e('Букеты: ' . $t['title']) ?>" loading="lazy" decoding="async">
          <?php else: ?>
            <?php /* Пастельная плитка без фото: декоративный прозрачный цветок (размер фиксируем инлайном — CSS-агент его не стилизует) */ ?>
            <svg class="fc-occasion__flower" width="44" height="44" viewBox="0 0 32 32" style="position:absolute;left:50%;top:42%;transform:translate(-50%,-50%);color:var(--pink);opacity:.5" aria-hidden="true">
              <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor"/>
              <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(72 16 16)"/>
              <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(144 16 16)"/>
              <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(216 16 16)"/>
              <ellipse cx="16" cy="9.5" rx="4.6" ry="7.2" fill="currentColor" transform="rotate(288 16 16)"/>
            </svg>
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
          <label for="orderEmail"><?= e(setting('form_email_label', 'Email')) ?> <span id="orderEmailReq" style="color:var(--rose-cta,#AE4A71);font-weight:600" hidden>* обязательно для онлайн-оплаты</span></label>
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
                <option value="<?= (int)$z['id'] ?>" data-price="<?= (int)$z['price'] ?>" title="<?= e($zFull) ?> · <?= (int)$z['price'] ?> ₽"><?= e($zShort) ?></option>
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
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash"><span><?= e(setting('pay_cash_label', 'При получении')) ?></span></label>
          <p class="order-form__hint">Оплата проходит на защищённой странице ЮKassa. Данные карты магазину не передаются.</p>
          <?php else: ?>
          <?php /* Покупатель-критик W46: radio «При получении» уже даёт payment_method —
                    hidden-дубль с тем же именем создавал двойное значение в FormData. */ ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash" checked><span><?= e(setting('pay_cash_label', 'При получении')) ?></span></label>
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
            <p class="order-form__hint">Курьер позвонит ему, а не вам</p>
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
            <?php /* W96-fix4: min=today — не даём выбрать прошлое до отправки (сервер валидирует повторно) */ ?>
            <input type="date" id="orderDeliveryDate" name="delivery_date" min="<?= date('Y-m-d') ?>">
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
          <span>Я даю согласие на обработку персональных данных (ФИО, телефон, адрес) в целях оформления и доставки заказа, <em>включая возможную трансграничную передачу</em> (уведомление магазина через мессенджеры), на условиях <a href="/policy" target="_blank" rel="noopener">Политики конфиденциальности</a> и <a href="/offer" target="_blank" rel="noopener">Публичной оферты</a> *</span>
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

  <!-- SEO-ТЕКСТ (5cv): sanitize_rich_text разрешает только <a>; абзацы — через \n\n → <p> (стилизует .fc-seo p) -->
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
        foreach ($seoParas as $seoPara): ?>
        <p><?= nl2br($seoPara) ?></p>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

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
      for ($i = 1; $i <= 4; $i++) {
          $q = trim(setting("faq_q{$i}", ''));
          $a = trim(setting("faq_a{$i}", ''));
          if ($q !== '' && $a !== '') { $faqItems[] = ['q' => $q, 'a' => $a]; }
      }
      ?>
      <div class="fc-faq">
      <?php foreach ($faqItems as $f): ?>
      <details class="faq-item">
        <summary class="faq-item__q"><?= e($f['q']) ?></summary>
        <p class="faq-item__a"><?= e($f['a']) ?></p>
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
          <?php if ($j['link'] !== ''): ?><a class="fc-journal__link" href="<?= e($j['link']) ?>">Читать →</a><?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* W96 (5cv): блок отзывов Яндекс Карт убран по требованию заказчика (отзывов на сайте нет). */ ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
