# AGENTS.md — конспект для агентов по nilov-flowers-site

**Что это:** цветочный магазин «Nilov Flowers» (СПб). Сессия S1: **пивот на v2026/** — статичная витрина + content.json + admin.html. Бывший PHP 8 + SQLite (старый код в корне, не используется в сэндбоксе). Прод: flowers.interfood-catering.ru (SpaceWeb, Apache, деплой GH Actions по push в main).

## Окружение локального стенда
- **PHP недоступен** в этом сэндбоксе (нет root, apt locked). Сервер — **Bun** на порту **8123** (`server.js`).
- Запуск: `pm2 start ecosystem.config.js` → app `nilov-site` (`bun run server.js`, cwd репо). Логи: `pm2 logs nilov-site` (state/pm2-*.log).
- **Порт 3000** — сэндбокс my-project (Next.js 16), НЕ трогать код; `next.config.ts` rewrites (beforeFiles) → 8123 даёт превью пользователю.
- **Bun:** `/usr/local/bin/bun`. **pm2:** `/home/z/.npm-global/bin/pm2`. **agent-browser:** `/usr/local/bin/agent-browser` (критики ОБЯЗАНЫ `agent-browser close` после — 2-ядерный стенд).
- BД: нет (content.json — единый источник). `db/admin-token.txt` (gitignored) — fallback token.

## Архитектура (v2026)
- **server.js** (Bun.serve, :8123): `/` → v2026/index.html; `/content.json` + `/v2026/content.json` → v2026/content.json (live на витрине); `/v2026/*` статика; `/img/*` → img/; `/api/content` GET/POST (X-Admin-Token = content.json admin.password); `/api/upload` (formData `file` → img/uploads/); `/api/img-list`; `/api/health`. safePath: strip leading `/`, reject `..`.
- **v2026/index.html** (≈3850 строк): статичная витрина, inline CSS+JS, CDN-шрифты (Playfair/Golos/JetBrains via jsdelivr), fetch `/content.json` → рендер. Тёмная bloom-тема: ink #0D120E, ink-2 #1A211C, ink-3 #283228, moss #2E3F31, bone #F4EEE2, bone-mute #8A8478, bloom #E8607A, bloom-deep #D14B66 (CTA-bg), gold #C8A24A. Радиусы 20/12/99. Easings cubic-bezier(.16,1,.3,1)/(.76,0,.24,1)/(.34,1.56,.64,1).
- **v2026/motion.js** (315 строк, vanilla, defer): Tier-A motion — hero pin+parallax (0.3:0.6:1.0, IO-gated rAF), char-stagger reveal, atelier progress ring, fly-to-cart (WAAPI), cardTilt (lerp+spring, pointer:fine), section stagger reveals (IO, 70ms), marquee velocity-mod. RM-safe (статика). Слушает `nf:ready` + fallback setTimeout 3.5s.
- **v2026/admin.html** (≈2318 строк, vanilla): login (admin/nilov2026) → 10 sidebar groups × 28 секций = 100% content.json; 14 field types (text/textarea/richtext/number/range/color/toggle/select/multi-chips/chips/image/repeater/group/key-value/code); schema-inference; live-preview iframe (viewport toggle 390/768/1280); autosave 1.5s + «Опубликовать» (POST /api/content); image upload+library; ⌘K; FIELD_LABELS (human Russian). Тёмная тема = витрина (ink/bone/bloom/gold).
- **v2026/content.json** (29 ключей, 50KB): meta(+legal/seo), theme(tokens), contacts, nav, ticker, hero(+stats), marquee, chapters, atelier(+founder), occasions, catalog, categories, occ, constructor, delivery, subscription, reviews(+trust), journal, faq, guarantees, cta, footer, promos, orders, users, poll, admin, products[20], updatedAt.

## Правила (обязательны)
1. Перед работой читать **worklog.md** — история волн и решения.
2. Пуш только в main, **никогда force-push**. Перед пушем: `git diff` ревью + проверка секретов (db/*.txt gitignored) + `bun -e 'JSON.parse(require("fs").readFileSync("v2026/content.json","utf8"))'` (valid JSON) + `node --check v2026/motion.js` + smoke-тест маршрутов (curl) + `agent-browser` живой прогон. Проверить что пушится (содержимое index.html/content.json).
3. Коммит-месседжи: префикс `S1`. В конце работы обновить worklog.md (сжато!).
4. Ничего не хардкодить — всё из content.json. Шрифты/CDN: jsdelivr только @fontsource.
5. CSP/безопасность: admin token = content.json admin.password; не коммитить db/admin-token.txt.
6. НЕ трогать: my-project (порт 3000) — только next.config.ts rewrites.
7. **Верификация = живой прогон** (agent-browser): клики, скролл, консоль. «Сделанный» фикс без живой проверки = не сделанный.
8. Тач-зоны ≥44px **на самом элементе** (min-height/min-width, не ::after); prefers-reduced-motion → статика (финальное состояние видно, не пустота); hover биндить всегда (headless `any-hover` ложно false — bind всегда, mousemove-эффекты безопасны).
9. Критики-агенты: **свежие, слепые** (не читают worklog до вердикта), каждые — отдельная browser-сессия `--session имя`, после себя `agent-browser close`. Каждая новая волна не знает о предыдущих.

## Текущая задача (обновлено 30.09.2026, после S1)
S1 завершён: 4 волны слепых критиков (8 ролей) + 5 фикс-волн. Финальные оценки: Color 8.5, Copy 8, Mobile 7.5, Motion 7.5, A11y 6-8 (headless-артефакты на focus-restore), Brand 6.5, CRO 4-7 (checkout работает; "freeze" = деградация сессии критика), Design 5. **Хвосты:** Design (нужен SOTD-level signature wow — кинетическая типографика, ботанические текстуры, narrative scroll — многодневная craft-работа); Brand (реальный ОГРНИП — owner-контент). См. worklog.md «Хвосты» + «Чек-лист владельца».
