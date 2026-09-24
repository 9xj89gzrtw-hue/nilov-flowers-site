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

## Текущая задача (сентябрь 2026)
Редизайн под дизайн-язык 5cv.ru (заказчику нравится): бенто-hero с фото + жёлтая промо-карточка, чипы цен, карточки с бейджами «Хит»/«Премиум», плитки «Цветы по поводу», магазины, журнал, FAQ. **Отзывов нет** (явное требование). Всё — управляемо из админки. Дизайн-токены 5cv: pink #ff4ea2, amber #f5b301, ink #1c1a1e, muted #6e6a72, surface #fff/#f6f6f8/#fbf5f8, line #e7e5ea, Montserrat, radius 16px.
