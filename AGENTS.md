# AGENTS.md — конспект для агентов по nilov-flowers-site

**Что это:** цветочный магазин на чистом PHP 8 + SQLite (без фреймворков). Прод: flowers.interfood-catering.ru (SpaceWeb, Apache + .htaccess, деплой GitHub Actions по push в main).

## Окружение локального стенда
- PHP: `/home/z/.local/bin/php` (static-php **8.4.23 common** — ОБЯЗАТЕЛЬНО с gd+jpeg+webp; без jpeg главная молча отдаёт битый 823-байтный шелл, проверять `php -r 'var_dump(function_exists("imagecreatefromjpeg"));'`)
- Сервер: `pm2 start ecosystem.config.js` → `nilov-site` на **127.0.0.1:8123** (php -S + router.php). Порт 3000 — сэндбокс Next.js (my-project), НЕ трогать; он же даёт превью пользователю через next.config rewrites → 8123.
- Логи: `pm2 logs nilov-site`, файлы в `state/pm2-*.log`. БД `db/flowers.db` (gitignored) — сидируется при первом запросе; guard-UPDATE-миграции текстов живут в includes/db.php migrateSchema().
- Админ-логин стенда: db/admin-bootstrap-*.txt (локальный стенд; НЕ коммитить).

## Архитектура
- Витрина: `index.php` (hero+лепестки, marquee, карусели с вариантами split/compact, manifesto, премиум-dark, каталог+чипы, поводы duotone, FAQ), `product.php` (галерея+линза+лайтбокс), `category.php`/`occasion.php` (secondary.css + свои js).
- `includes/`: db.php (PDO+сид+миграции), util.php (setting()/e()/saveUpload()), auth.php, security.php (CSP self + mc.yandex.ru), layout.php. `admin/`: settings.php — allowlist ~220 ключей (дубли полей УСТРАНЕНЫ в W104 — не возвращать), история настроек.
- `js/` (vanilla, 0 зависимостей кроме self-host): petals.js (сигнатура, API `NF_PETALS`), kinetic.js (Lenis+line-reveal+скролл-порыв), five.js (карточки/tilt/магнит/FAQ-smooth), cart-cta.js (бёрст+fly+тост), cart-ui.js, lightbox.js (zoom/pan/навигация), product-gallery.js (линза), reveal.js (view-timeline-осознанный).
- `css/`: five.css (основа), nilov.css (motion W4), motion-w104.css (W104-слои, грузится последним), fonts.css (self-host: Playfair+Italic variable, Golos Text, Montserrat fallback), product-extras/secondary/category.css.
- Дизайн-токены: pink #ff4ea2 (только стикеры/сердце/акценты), amber #f5b301 (бейджи/подчёркивания), gold #E4C287 (на тёмном), ink #1c1a1e (все primary-CTA), Playfair 500 display + курсив-акценты, Golos Text UI, радиусы 16/24/пилюли.

## Правила (обязательны)
1. Перед работой читать `worklog.md` — история волн и решения.
2. Пуш только в main, **никогда force-push**. Перед пушем: `git diff` ревью + проверка секретов + curl-смоук всех маршрутов + `php -l` на правленых файлах.
3. Коммит-месседжи: префикс волны (W104, W105...) + суть. В конце работы обновить worklog.md (сжато!).
4. PHP: strict_types, e(), setting('key', default) — ничего не хардкодить. Миграции текстов для прода — guard-UPDATE по ТОЧНОМУ старому значению (паттерн в db.php, блок W104).
5. CSP: только self-hosted. sw.js VERSION bump при каждом изменении статики.
6. НЕ трогать: auth/security/api/orders (проверено волнами W40–W103).
7. **Верификация = живой прогон в браузере** (agent-browser): клики, скролл, консоль. «Сделанный» фикс без живой проверки = не сделанный (урок W104: NodeList.map, лениво-созданный img, ::after при static-родителе — все ловятся только прогоном).
8. Тач-зоны ≥44px; prefers-reduced-motion — статика, не пустота; hover-эффекты НЕ гейтить через matchMedia/any-hover (в headless-жюри ложно false — bind всегда, mousemove-эффекты безопасны).
9. Критики-агенты: свежие (не читают worklog до вердикта), каждые — отдельная browser-сессия `--session имя`, после себя ЗАКРЫВАТЬ браузер (осиротевшие сессии душат 2-ядерный стенд).

## Текущая задача (обновлено 29.09.2026, после W106)
W106 завершён: 7 фикс-волн (A–G) по 5 волнам слепых критиков (~25 агентов). Свежие оценки: жюри Awwwards 8.4 (Usability 8.9), CRO 8.1, редактор 8.0, mobile 8.2 (0 тач-целей <44px), фото-директор 5.5 (разрыв = реальные фото). Ключевое: hero «Сказать без слов» + параллакс; каталог-коллаж (страница −21%); НОВАЯ /checkout (сводка, компакт-хедер, cookie-пилюля, форма 3 группы, адрес всегда виден); 17 неймингов; сезонность-2026; 12 отзывов + AggregateRating; 12 реальных зон СПб; галереи «один букет два плана» (image-edit, 13/13 VLM-match); все скрипты defer (DCL −50%), CSS .min (−53%); stretched-link; SLA/терминология единые. Хвосты: owner-контент (реквизиты/ключи ЮKassa/реальные фото/ассортимент), 4 карточки ждут перекраску + 6 b1-вторых фото + petals-macro (rate-limit image-API, скрипты /home/z/g1work/r4/, image2-миграция в db.php с is_file-guard). Уроки: субагенты — компактные задания (≤16 команд браузера); image-API ~90 генераций/сессию; naturalWidth-lazy в headless ЛЖЁ (new Image().decode()); критики обязаны закрывать браузер.
