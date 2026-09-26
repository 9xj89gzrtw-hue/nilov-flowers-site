# AGENTS.md — конспект для агентов по nilov-flowers-site

**Что это:** цветочный магазин на чистом PHP 8 + SQLite (без фреймворков). Прод: flowers.interfood-catering.ru (SpaceWeb, Apache + .htaccess, деплой GitHub Actions по push в main).

## Окружение локального стенда
- PHP: `/home/z/.local/bin/php` (статический бинарь 8.4, pdo_sqlite/gd/mbstring/curl/openssl)
- Сервер: `pm2 start ecosystem.config.js` → `nilov-site` на **127.0.0.1:8123** (php -S + router.php). Порт 3000 занят сэндбоксом Next.js — НЕ трогать.
- Логи: `pm2 logs nilov-site`, файлы в `state/pm2-*.log`.
- БД `db/flowers.db` создаётся и сидируется автоматически при первом запросе (gitignored). Сид: 3 категории, 3 товара, settings, зоны. migrateSchema() добавляет колонки/таблицы идемпотентно.

## Архитектура
- `index.php` — витрина (hero, marquee, каталог+вкладки, how-it-works, FAQ, форма заказа). `product.php` — карточка товара (роут `/product/{slug}` через .htaccess/router.php). `occasion.php` — лендинги по поводам (`/occasion/{slug}`).
- `partials/head.php|header.php|footer.php` — общий каркас витрины.
- `includes/`: config.php (константы), db.php (PDO+сид+миграции), util.php (setting()/e()/saveUpload() и др.), auth.php (сессии, requireAdmin), security.php (CSP: `default-src 'self'`; инлайн-стили/скрипты разрешены; шрифты ТОЛЬКО self-hosted), layout.php (админ-каркас), notify.php, settings-history.php.
- `admin/` — панель (settings.php — allowlist-сохранение настроек + CSRF + чекбоксы через cb_rendered + снимки истории). Все тексты витрины редактируются через settings.
- `js/` — vanilla JS: cart.js/cart-ui.js (корзина drawer), catalog-filter.js (вкладки+цена), nilov.js (таймер/плавные фокусы), order-form.js, reveal.js, lightbox.js и т.д.
- `css/` — style.css (базовая система), nilov.css (текущий дизайн), fonts.css (self-hosted @font-face + fallback-метрики). **Новый дизайн 5cv: css/five.css + Montserrat variable.**

## Правила (обязательны)
1. Перед работой читать `worklog.md` (в корне репо) — там история волн и решений.
2. Пуш только в main, **никогда force-push**. Перед пушем — `git diff` ревью и проверка содержимого.
3. Коммит-месседжи: префикс волны (W96, W97...) + суть. В конце работы дополнить worklog.md.
4. PHP: strict_types, экранирование через e() (htmlspecialchars), настройки через setting('key', default). Ничего не хардкодить — всё в settings с дефолтами в коде.
5. CSP: без внешних шрифтов/скриптов; Я.Метрика (mc.yandex.ru) — единственное исключение.
6. Не трогать: auth/security/api/orders — работает и проверено волнами W40–W95.
7. `bun run lint` не применим (это PHP-репо); проверка = curl страниц 200 + agent-browser скриншоты.

## Текущая задача (обновлено 26.09.2026, после W103)
W103 завершён: дизайн-итерации «критики → фиксы» ×4 волны (~15 независимых критиков). Итог: финал-джадж 7.5/10 «excellent, P0 нет» (порог SOTD 8.5 недостижим в рамках брифа 5cv — осознанно). Дизайн-система: Playfair Display (--font-display, заголовки/акценты) + Montserrat (UI); pink #ff4ea2 / amber #f5b301 / ink #1c1a1e; radius 16/24. Секционная ритмика главной: hero(фото petals-macro) → marquee → карусели(≤2 подряд, eyebrow «КАПС|курсив») → manifesto → премиум-dark → editorial-полоса → каталог → поводы(duotone/ink) → FAQ(details-анимация). Motion: entrance nv-ch, stagger 60мс, drawer @starting-style, parallax view()-timeline, магнитные CTA, image-unveil. Новые файлы: css/secondary.css (категории/поводы), css/category.css + js/category.js (фильтры/сортировка), css/product-extras.css (PDP-спеки/штамп/sticky-CTA), img/editorial/ (имиджи). Все тексты новых блоков — настройки в админке (manifesto_*, premium_*, catalog_strip_text, section_*_eyebrow, footer_tagline...). Правила W103: пуш только main, никогда force; перед пушем git diff + curl-смоук всех маршрутов; sw.js VERSION bump при каждом изменении статики; hero_image — путь от корня ИЛИ имя из img/uploads (site_image_root в index.php, head.php умеет оба).
