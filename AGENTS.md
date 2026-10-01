# AGENTS.md — конспект для агентов по nilov-flowers-site

**Что это:** цветочный магазин «Nilov Flowers» (СПб). Сессия **S2 завершена**: Next.js 16 витрина + Kanban-админка на живой БД (Prisma/SQLite) в этом же репо. Прод (SpaceWeb, Apache) продолжает обслуживать PHP+v2026 — S2 в деплой-rsync исключён, готов к переезду владельца на Node-хостинг (README-S2.md).

## Окружение
- **Порт 3000** — Next.js 16 (Turbopack, App Router): `bun run dev` в фоне. Логи: `dev.log`.
- **БД:** `db/flowers.db` (SQLite, `DATABASE_URL` в .env; `NF_SESSION_SECRET` там же). Схема: `prisma/schema.prisma` (Product/Order/OrderItem/DeliveryZone/Upsell/Setting/PromoCode).
- **Seed:** `bun scripts/seed.ts` — 24 букета (20 премиум из v2026/content.json + 4 классика из PHP-seed), 14 зон СПб, 5 апселлов, настройки, промокоды. ВНИМАНИЕ: seed стирает заказы; демо-заказы создавать через API с маппингом slug→id (id после re-seed сдвигаются!).
- **Фото:** `img/products/` (webp+jpg+thumbs), раздаются в dev через симлинк `public/img → ../img`. Загрузки админки: `img/uploads/`.
- **Панель флориста:** `http://localhost:3000/#admin`, пароль — настройка `admin_password` (по умолчанию `nilov2026`), сессия — httpOnly-кука (HMAC от пароля + NF_SESSION_SECRET).

## Архитектура S2
- `src/app/page.tsx` — витрина SSR из БД; клиентский стор — `src/components/store/store-app.tsx` (zustand-корзина, живой рефреш по `nf:data-updated`/focus).
- API: `/api/public-data`, `/api/orders` (цены пересчитываются на сервере, промо, бесплатная доставка от `free_delivery_from`), `/api/promo`, `/api/admin/*` (login/session/orders/products/zones/settings/upsells/upload/img-list). Rate-limit: `src/lib/rate-limit.ts` (orders 8/мин, promo 20/мин, login 10/мин).
- Админка: Kanban 6 статусов (dnd-kit, PATCH статуса), таблица, записка А5 (`#print-note` + @media print: раскладка цветов, СЮРПРИЗ, крупное пожелание), WhatsApp-шаблоны по статусам, тумблер `inStock` = мгновенное скрытие с витрины, инлайн-цены, no-code настройки с live-обновлением витрины.
- Дизайн: молочный #FAF9F6 / хвойный #143C2B / амбер #F5B301 / пудра #F4DEE3; шрифты локальные (src/fonts: Golos/Montserrat/Playfair). Мобайл: 2 колонки, липкая панель, 16px полей.

## Правила (обязательны)
1. Перед работой читать **worklog.md** (история S2: раунды критики, фиксы). После — append-секция (Task ID, Agent, Work Log, Stage Summary).
2. Пуш только в main, **никогда force-push**. Перед пушем: `git diff` ревью + секреты не коммитить (.env, db/*.db, db/*.txt gitignored) + `bun run lint` + `bunx tsc --noEmit` (src) + живой прогон agent-browser.
3. Менять Next.js-файлы можно свободно: деплой-workflow исключает их из rsync на прод (см. EXC в .github/workflows/deploy.yml) — прод = PHP+v2026 как прежде.
4. Коммит-префикс `S2`. Процессные файлы (worklog/AGENTS/CRITIQUE_*/DIFF_LOG_*/README-S2) на прод не идут.
5. Критики — свежие слепые (не читают worklog до вердикта), отдельные browser-сессии `--session cN`, после себя `close`. Итоги трёх раундов: CRITIQUE_ROUND_1/2/3.md + DIFF_LOG_1/2.md.
6. Двойной parse JSON-полей запрещён: API `/api/admin/*` уже отдаёт распарсенные объекты, `safeJson` идемпотентен — не парсить повторно.
7. Верификация = живой прогон (agent-browser): клики, консоль, `scrollWidth`, битые img. «Сделанный» фикс без проверки = не сделанный.

## Текущее состояние (01.10.2026, после S2)
Три раунда слепой критики пройдены (15 критиков): финал 9/9/9/9/8.5, все вердикты «готово к сдаче». Консоль чистая, 0 битых фото, API 7–12 мс. Хвосты (владелец): пересъёмка каталога, избранное, реальные вторые ракурсы, ОГРНИП, прод-замер RSS после build.
