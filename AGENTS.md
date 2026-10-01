# AGENTS.md — конспект для агентов по nilov-flowers-site

**Что это:** цветочный магазин «Nilov Flowers» (СПб). Прод (SpaceWeb, Apache, flowers.interfood-catering.ru) = **PHP 8 + SQLite + vanilla JS** — ЭТО РАБОЧИЙ СТЕК. Сессии S1 (v2026 статика) и S2 (Next.js) — исторические артефакты в этом же репо, в деплой-rsync НЕ едут (EXC в .github/workflows/deploy.yml).

## Сессия S3 (01.10.2026, текущая) — сделано
- **Коммерческий слой 5cv-класса** (деплой 2013d10 + фикс f774ca5, прод верифицирован):
  палитра молоко #FAF9F6 / изумруд #143C2B / янтарь #F5B301 / пудра #F4DEE3 / графит #18181B;
  инфо-бар над шапкой; WA/TG в шапке; районы в городе; сумма корзины (#cartSum);
  чипсы: «Все»+цены+ТЕГИ (настройка chips_tags; матч по data-tags, стем 4 буквы; PHP и JS матчеры идентичны);
  карточка: 2-й ракурс hover (image2, is_file-guard), состав/размер, «Сплит: от N ₽/мес» (split_divider, по умолч. 4),
  бейджи Стойкие/Свежая поставка (по тегам «стой…»/«свеж…»), кнопки «В корзину»+«Купить в 1 клик» (js/oneclick.js);
  корзина: прогресс до бесплатной доставки (free_delivery_threshold=5000), бесплатные допы открытка(текст)+Chrysal (orders.extras JSON);
  чекаут: «Себе»/«Сюрприз другому»; 2-часовые слоты; мобильная панель Каталог/Поиск/Корзина+сумма/WA (mnav, 58px);
  зоны: time («60–90 мин»), пригороды Кудрово/Мурино/Всеволожск/Гатчина.
- **Статусы-этапы**: new → photo («Согласование фото») → florist («Флорист собирает») → courier («У курьера») → done («Доставлен»); миграция confirmed→photo, in_progress→florist; словарь в util.php (statuses/orderTransitions/kanbanStages).
- **Админка**: канбан admin/index.php?view=kanban (DnD POST status, валидация переходов на сервере);
  страница заказа: «Печать записки А5» (@media print .print-note: состав/адрес/интервал/КРУПНАЯ открытка/СЮРПРИЗ) + WA-кнопка (шаблоны wa_template_*);
  товары: тумблер «В наличии/Закончился» (.stock-switch), инлайн-цена (details.price-quick, action=quick_price),
  поля теги/состав/размеры; настройки: секция **«Продажи»** (s-commerce, 35+ ключей).
- **Минификация CSS**: `python3 scripts/minify-css.py` — запускать после правок css/*.css (head.php отдаёт .min только если он свежее исходника).

## Сессия S5 (01.10.2026, бутик-тюнинг) — сделано
- **Дизайн-система S5** (деплой 7b427b9, прод верифицирован): молоко #FAF9F6 (body),
  белые карточки + бордер rgba(0,0,0,.05) + тени --shadow-card (idle/hover),
  хвойный эспрессо #12281D (--pine), янтарь #E09F3E (--amber, «Хит» с белым текстом),
  графит #141416/#6E6E77, --surface-soft #F1EFEA, radius 16, tabular-nums + -.02em.
  Idle-тень карточек вернулась (motion-w104 §4e); motion-lite — по-прежнему flat.
- **Живой таймер доставки** (Conversion Booster): #fcDeliveryNow в шапке каталога
  (мобайл — в строке с меткой «КАТАЛОГ», ноль высоты), js/five.js deliveryNow():
  СПб (NILOV_CONFIG.tz) + delivery_now_minutes (90), округление вверх к 15 мин,
  за deadline/полночь — «завтра к 10:00»; пульс-точка; ключи delivery_now_*.
- **Карточка**: зум .6s bezier(.16,1,.3,1), стеклянный бейдж скидки, Хит #E09F3E,
  поп сердечка, микро-бейдж ⌀/↕ (size_text), сплит-ПИЛЮЛЯ [Сплит]«4 платежа по N ₽»
  (splitPaymentText() в util.php: card_split_format {div}/{per} + склонение;
  PDP остался на splitLabel). Копия карточки в category.php синхронизирована.
- **Чипсы**: порядок 5cv (Все букеты → цены → Премиум → теги → Хиты/от 7000);
  guard-миграция chips_tags «Пионы,Французские розы,Гортензии,В коробках,Подарок»;
  матчер ALL-WORDS по стему 4 букв (PHP $nfTagMatch = JS nfTagMatch) — «В коробках»
  матчит «в шляпных коробках»; чип без товаров не печатается (прод-каталог сам решает).
- **Quick View** (js/quickview.js + NF_QUICKVIEW из footer.php, гл.+/category/):
  клик по фото → bottom-sheet(мобайл)/двухколонник(десктоп): слайдер image+image2,
  состав поштучно (split «·»), открытка + Кризал 0 ₽ + сладости-чекбоксы (товары
  с тегом «сладости» — cart.add), кнопки кликают живые CTA/[data-oneclick] карточки;
  extras → nf_cart_extras + change/input-синхронизация с drawer (cart-ui — владелец).
  Геометрия: grid-template-rows minmax(0,1fr) — кнопки достижимы при скролле инфо.
- Тумблеры: feature_delivery_now / feature_quickview; 14 новых ключей в db.php S5
  + allowlist/поля admin/settings.php (секция «Продажи»).

## Сессия S6 (01.10.2026, стерильный 5cv) — сделано
- **Дизайн-система S6** (деплой e0ec10c, прод верифицирован): КРИСТАЛЬНО
  белый #FFFFFF, подложки #F7F7F8, уголь #1A1A1A, серый #767676,
  контуры #EFEFEF/#E0E0E0, БЕЗ теней/стекла/зерна/зума/лифтов.
  Шрифт — Inter (variable 400–800, /fonts/Inter-*.woff2) на ВСЁМ сайте:
  Playfair/Golos не подключаются, локальные ре-декларации secondary.css/
  product-extras.css переопределены на var(--font-ui), курсивы убраны.
- **Шапка**: топбар #F7F7F8 (fc-city «Санкт-Петербург» + topbar_delivery_text
  «Доставка от 1 часа» | телефон + зелёная WA-кнопка) вместо инфо-бара;
  основной ряд — лого/поиск «Розы, пионы, букет маме…»/корзина;
  под шапкой .fc-usps — 4 бейджа доверия (trust_badge_1..4, мобайл-скролл,
  рейтинг — ссылка на Яндекс Карты при yandex_reviews_id).
- **Карточка 6 строк 5cv** (index + category копия): фото 4:5 r12 →
  «Хит» #FFB800 (чёрный текст)/белая скидка → часы+card_delivery_text →
  «★ 5.0 (N)» (живой рейтинг reviews по товару, фолбэк getRatingAggregate,
  sprintf('%.1f')) → название 15–16px/500 → цена 20–22px/800 + старая →
  серый «Сплит: от N ₽ × 4» (splitLabel) → чёрная «В корзину» + ссылка
  «Купить в 1 клик». Сердце/⌀↕/состав/пилюли убраны (comp/size — hidden
  носители для js/quickview.js). Таймер доставки УДАЛЁН (five.js deliveryNow
  + #fcDeliveryNow + NILOV_CONFIG-ключи).
- **Чипы**: Все букеты → До N → N–M → От N → Премиум → теги
  (chips_tags «Розы,Пионы,Гортензии,В коробках»); чип «Хиты» убран;
  ≤899px вкладки .catalog-tabs скрыты (дублируют чипы) — цена первой
  карточки в фолде.
- **ВНИМАНИЕ легаси-ловушка**: style.css:178 .product-card__cta —
  absolute 44×44 opacity:0 (W65-кружок). five.css нейтрализует
  (position:static;opacity:1;transform:none;width:100%) — НЕ удалять
  этот reset. Аналогично mnav-тень из style.css гасится box-shadow:none.
- **«Кофе» отключён**: db.php S6 one-shot (guard s6_kofe_disabled,
  slug=kofe AND name='Кофе'); повторное включение владельцем — свято.
- Новые ключи: topbar_delivery_text, trust_badge_1..4 (+ allowlist +
  поля админки); guard-UPDATE search_placeholder/chips_tags.

## Сессия S7 (01.10.2026, коммерческая структура 5cv) — сделано
- **CSS-КОНФЛИКТЫ ЗАКРЫТЫ** (следующий деплой): head.php подключает ТОЛЬКО
  five.css + fonts.css — style.css/nilov.css/motion-w104.css НЕ едут в
  браузер. Нужные легаси-правила (cart-panel/cart-upsell drawer, product-page/
  product-gallery PDP, lightbox, no-scroll, is-hidden, order-form__error,
  nf-fly-chip — 123 шт.) портированы в five.css секцией «S7. LEGACY-COMPAT»
  (селекторы «idden]» style.css починены). footer.php: lenis/kinetic/petals
  отключены (reveal.js остался — инертный контракт).
- **Главная = полки 5cv**: шапка одной строкой (лого/поиск/город/телефон/
  корзина, линия #EBEBEB) → 4 бейджа доверия → 4 ПЛАШКИ БЮДЖЕТА (.fc-budget,
  .fc-chip data-chip=low/mid/high/premium, клик = фильтр + скролл к #catalog)
  → липкая лента чипов (.fc-chips-bar sticky; Все→Хиты→теги) → три ПОЛКИ
  (.fc-shelf: «🔥 Хиты продаж» 4 хита / «🌸 Авторские букеты и розы» 6
  не-премиум / «✨ Премиум композиции и коробки» премиум+остаток+сладости;
  каждый товар 1 раз; пустые полки скрывает apply()) → мастерская + 3 отзыва
  (.fc-workshop) → поводы/форма/SEO/FAQ. Hero/marquee/коллаж/manifesto/
  премиум-dark/strip/допы/editorial/stores УДАЛЕНЫ (H1 → .sr-only).
- **Карточка (index + category копия)**: фото 4:5 r16 контур #F0F0F0 →
  ЦЕНА 22-24px/800 + старая + бейдж «Сплит N ₽ × 4» (.product-card__split-badge)
  → название 15px/2 строки → «⚡ За 1–2 ч · ★ 5.0 (N)» #767676 → «В корзину»
  #1A1A1A r12 h44 + «Купить в 1 клик». quickview.js читает новые классы
  (split-badge/meta-item, старые — фолбэком).
- **JS**: catalog-filter.js — guard только по #catalogGrid (вкладок нет),
  скрытие пустых .fc-shelf; five.js — бюджет-чипы в общем массиве чипов +
  скролл после клика по плашке.
- **S7-миграция db.php**: +shelf_*_title, workshop_hours/rating/note;
  guard-UPDATE: chips_all_text→«Все», chips_tags+«Подарки», split_label→
  «Сплит {price} ₽ × {div}», card_delivery_text→«За 1–2 ч».
- **ВНИМАНИЕ локальная проверка**: headless-браузер песочницы ресайзит
  lazy-картинки под sizes (naturalWidth=размеру sizes) — серые прямоугольники
  на локальных скринах = артефакт эмуляции, НЕ баг кода (байты сервера
  идентичны, статическая карточная разметка красится, прод красит).
- Форма заказа: fieldset/payment min-width:0 (было только в style.css —
  без него mobile scrollWidth 458).

## Сессия S8 (02.10.2026, инцидент «серые прямоугольники» на проде) — сделано
- **КОРНЕВАЯ ПРИЧИНА** (подтверждена на живом проде): связка
  `<picture><source srcset sizes>` + `loading="lazy"` — браузер НЕ
  запрашивал файлы (naturalWidth=0, ноль запросов /img/products/ в network
  при visible/внутри вьюпорта). Plain `<img srcset sizes lazy>` — грузился,
  тот же файл внутри `<picture>` — нет (бисекция вживую на проде).
  VLM-предупреждение о серых карточках в S7 было ПРАВОЙ, «артефакт
  эмуляции» — ошибочная отбивка.
- **ФИКС (fail-closed)**: во всех карточках (index/category/occasion/
  product-related) БЕЗ `<picture>` — `<img src srcset sizes
  loading="eager" decoding="async">`; PDP-галерея LCP — eager без picture
  (второй слайд image2 — прежний JS-контракт data-gallery-srcset без
  изменений). Класс `reveal` снят с карточек (сетка не зависит от
  reveal.js/IO). five.css: `.product-card__img` — жёсткая видимость
  (!important block/100%/cover/opacity:1/visibility), media r12,
  убраны clip-path правила и `.catalog__grid .reveal` каскад.
- **ЕДИНАЯ ВИТРИНА 5cv**: 3 полки (fc-shelf) → ОДНА монолитная
  `.catalog__grid` ($gridAll = hits → author → premium/остаток/сладости);
  сетка: 2 кол gap 12px (мобайл) / 3 кол 16px (768) / 4 кол 20px (1024),
  5-кол правило убрано; 4-я плашка бюджета — «Хиты» (была «Премиум»);
  чип «Хиты» из ленты убран (Все → теги). Плашки/чипы фильтруют общую
  сетку прежним catalog-filter.js apply() (полки в нём — no-op).
- **Карточка S8**: фото 4:5 r12 #F0F0F0 контур; цена 20/22px; «В корзину»
  42px r10; «В 1 клик» — полноценная белая кнопка с контуром (r10,
  38px мобайл) на всю ширину.
- **Верификация (fail-closed, до пуша)**: `scripts/check-card-media.py`
  (пиксель-контроль .card-media: avg≈#F0F0F0/F7F7F8 или std<6 = FAIL) —
  12/12 мобайл + 8/8 десктоп ok; VLM ×3 (мобайл-фулл/десктоп-фулл/
  мобайл-топ): фото букетов реальные, 2/4 колонки, плашки 2×2, брака нет;
  интерактив: плашки/чипы/сброс/поиск; category 5/5 + PDP 3/3 img;
  h-scroll нет (390px).

## Окружение локальной разработки
- **PHP:** `/home/z/tools/php` (8.4.12 static, GD+SQLite; источник — static-php.dev). Сервер: `PHP_CLI_SERVER_WORKERS=8 /home/z/tools/php -S 127.0.0.1:8090 router.php` в корне репо. БД `db/flowers.db` создатся автоматически (seed).
- **Не трогать** порт 3000 (песочница Next.js my-project) и repo `newsite` (кейтеринг, отдельный проект).
- Секреты: db/*.db, db/*.txt, .env — gitignored; пуш только в main, **никогда force-push**; перед пушем `php -l` (изменённое) + `node --check` (js) + `python3 scripts/minify-css.py` + live-прогон agent-browser.

## Правила (обязательны)
1. Перед работой читать **worklog.md** (S1-S3) + этот файл. После — append-секция (Task ID, Agent, Work Log, Stage Summary).
2. Все тексты витрины — из настроек БД (settings), ничего не хардкодить; новые ключи — INSERT OR IGNORE в db.php + allowlist admin/settings.php + (если чекбокс) список $cb.
3. Миграции — идемпотентные guard-паттерны (см. S3-блок в db.php); прод-БД живая — UPDATE только по точному старому значению/пустому полю.
4. JS-контракты: #cartToggle/#cartCount/#cartSum, #fcSearch, data-order-cta, [data-oneclick], .fc-chip[data-chip]/[data-tag], .mnav__search/#mnavCartBtn.
5. Верификация = живой прогон: локально 8090, после деплоя — https://flowers.interfood-catering.ru/ (curl + agent-browser, десктоп и 390px).

## Хвосты (владелец)
- Проставить теги каталогу (админка → Товары) — появятся чипсы «Пионы»/«Гортензии»/«Французские розы».
- WA/TG кнопки в шапке появятся после заполнения shop_whatsapp/shop_telegram (Настройки → Контакты).
- Вторые ракурсы (image2): файлы волны B1 уже на проде; загружать через админку.
