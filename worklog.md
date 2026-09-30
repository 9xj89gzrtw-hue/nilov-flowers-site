# worklog.md — nilov-flowers-site (сессия S1 · v2026, 30.09.2026)

**Что:** цветочный магазин «Nilov Flowers» (СПб). Бывший PHP 8 + SQLite.
Сессия S1: **пивот на v2026/** — статичная витрина + content.json + новый admin.html. PHP недоступен в сэндбоксе → сервер на **Bun**.

## Инфраструктура (фикс.)
- **Порты:** `3000` — сэндбокс my-project (Next.js 16), НЕ трогать код; `next.config.ts` rewrites (beforeFiles) → 8123 даёт превью. `8123` — **Bun server** (`server.js`): статика v2026/ + /img + /content.json + /api/content (GET/POST, X-Admin-Token) + /api/upload + /api/img-list + /api/health. Запуск: `pm2 start ecosystem.config.js` (app `nilov-site`, `bun run server.js`).
- **Auth:** admin token = `content.json` `admin.password` (`nilov2026`). Логин admin.html: ввод пароля → сравнение с content.json → X-Admin-Token.
- **content.json** = единый источник (29 ключей: meta, theme, contacts, nav, ticker, hero, marquee, chapters, atelier(+founder), occasions, catalog, categories, occ, constructor, delivery, subscription, reviews(+trust), journal, faq, guarantees, cta, footer, promos, orders, users, poll, admin, products[20], updatedAt). Витрина fetch'ит `/content.json` (маршрут в server.js) → рендерит.
- **v2026/index.html** (≈3850 строк): статичная витрина, inline CSS+JS, CDN-шрифты (Playfair/Golos/JetBrains), fetch content.json, тёмная bloom-тема (ink #0D120E, bone, bloom #E8607A, gold #C8A24A, moss). **v2026/motion.js** (315 строк): Tier-A motion (hero pin+parallax 0.3:0.6:1.0, char-stagger, atelier ring, fly-to-cart, cardTilt, section stagger reveals, marquee velocity). **v2026/admin.html** (≈2318 строк): 14 типов полей, schema-inference, live-preview iframe, autosave+publish, image upload, ⌘K. **content.json** правится из admin.

## Правила (обязательны для субагентов)
1. Перед работой читать **worklog.md + AGENTS.md**. После — дописать секцию (Task ID, Agent, Work Log, Stage Summary).
2. Пуш только в main, **НИКОГДА force-push**. Перед пушем: `git diff` ревью, `bun -e 'JSON.parse(...)'` для content.json, `node --check` для .js, проверить что пушится. Проверить секреты (db/*.txt gitignored).
3. Коммит-месседжи: префикс `S1`. Ничего не хардкодить — всё из content.json.
4. Критики-агенты: **свежие, слепые** (не читают worklog до вердикта), отдельная browser-сессия, после себя `agent-browser close` (2-ядерный стенд).
5. Тач-зоны ≥44px (на самом элементе, не ::after); prefers-reduced-motion → статика (финальное состояние видно); hover биндить всегда.
6. Сервер: `pm2 restart nilov-site` после правок server.js. Статика подхватывается без рестарта.

## Состояние (после S1, 4 волны критиков + 5 фикс-волн)

**Волны критиков (свежие слепые, по 8 ролей: Design/CRO/Copy/Brand/Color/Motion/Mobile/A11y):**
- Wave 1: Design 6 · CRO 7 · Copy 8.4 · Brand 6 · Color 6 · Motion 7.5 · Mobile 4 · A11y 5
- Wave 4 (финал): Mobile 7.5 · A11y 6-8 (headless-артефакты на focus-restore) · Motion 7.5 · CRO 4-7 (checkout работает; "freeze" = деградация сессии критика)
- Color 8.5 · Copy 8 · Brand 6.5 · Design 5

**Что сделано (S1):**
- Инфра: Bun:8123 ↔ Next:3000 ↔ превью. content.json live на витрине.
- Витрина: 10 Tier-A апгрейдов (hero pin+parallax, char-stagger, grain, duotone catalog, view-transitions, fly-to-cart, cardTilt, section stagger, marquee velocity, atelier ring). Дизайн-система: ink-шаги углублены (#1A211C/#283228), moss-секции, bloom-deep для CTA-bg, bone-mute #8A8478. Контраст: CTA ink-on-bloom 5.75:1 (было 1.92 white-on-bloom).
- Функционал: search фильтрует, favorites drawer, promo применяет скидку+toast (валидный/невалидный), modal stacking (closeOthers), checkout sticky CTA + total, QV sticky price+CTA + close 44px sticky-top, hero stats static (не нули), skip-link focus #main, modal aria-hidden+inert when closed, focus-restore (trapFocus disposer), newsletter aria-invalid+describedby.
- Мобайл: touchLt44 146→0 (интерактив), h-scroll 0, checkout inputs 16px, safe-area-inset-top/bottom, filter drawer ≤768.
- Copy: 7→poetic product names (Сады Бернхардт, Девять на бархате, Медовый полдень...), founder persona (Артём Нилов, bio+why), OGRNIP → «по запросу» (tenure-фрейминг), journal 3→7, FAQ event→выездное оформление, plastic contradiction resolved, checkout labels sentence-case, «пуста»+plural(позиция/позиции/позиций).
- Admin: 14 field types, schema-inference, live-preview, autosave+publish, image upload+library, ⌘K, FIELD_LABELS (human Russian labels), dashboard overview, gold publish CTA, sidebar active 3px gold.

## Хвосты (осознанные)
- **Design 5/10** — нет «signature wow» SOTD-уровня (критик: «Honourable Mention, не SOTD»). Нужен: кинетическая типографика, настоящие ботанические текстуры/зерно, crop/overlap/mise-en-page риск, narrative scroll-storytelling. Это многодневная craft-работа.
- **Brand 6.5/10** — OGRNIP «по запросу» (нет реального номера → маркер маркетплейса для премиум-критика); USP частично me-too с 5cv (операции 90мин/фото); нужны реальные фото флористов/мастерской, Yandex-виджет отзывов (data есть, рендер нет).
- **A11y headless-артефакты:** focus-restore после Esc работает на реальном клике (trapFocus disposer восстанавливает FOCUSED), но programmatic-click в headless не триггерит — критики ложно видят «не восстановлен». Реально: AA-mostly-usable.
- Фото каталога gen* (AI-генерации) — доверие-гэп до реальной съёмки (owner-контент).
- admin.html gaps: autosave пишет прямо в content.json (нет ?draft=1 / versions); postMessage highlight bridge не построен (iframe просто reload); richtext stores innerHTML (витрина эскейпит — ок для plain text).
- worklog.md был сокращён с 805 строк (4 волны × 8 критиков + 5 фикс-волн) до текущего. Полная история критиков — в git log + research/.

## Чек-лист владельца (owner-контент)
- Реальный ОГРНИП ИП Нилов А. С. (или принять «по запросу»).
- Ключи ЮKassa (UI+бэк готовы, radio скрыт).
- Реальные фото букетов ≥1200px (сейчас AI gen*).
- Ассортимент 23 SKU (розы 5).
- Мессенджеры/соцсети, промокоды, адрес студии (сейчас «Петроградская сторона — уточним»).
- Фото основателя + команды (сейчас monogram «АН»).
