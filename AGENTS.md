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

## Окружение локальной разработки
- **PHP:** `/home/z/tools/php` (8.4.23 static, полный GD+SQLite). Сервер: `php -S 127.0.0.1:8090 router.php` в корне репо. БД `db/flowers.db` создатся автоматически (seed).
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
