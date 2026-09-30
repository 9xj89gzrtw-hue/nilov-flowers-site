# admin-ux-2026.md — Nilov Flowers · admin.html design spec (Task 1-c)

**Goal:** v2026/admin.html must be the best-in-class content admin of 2026 — beautiful, fast, accessible, and able to edit **100% of `content.json`** (29 top-level keys, ~1 600 lines, 50 KB). This file is the design spec the builder agent will implement.

**Sources:** 19 web searches via z-ai `web_search` (results saved in `research/admin-ux-searches/s01–s19.json`). Queries: best CMS dashboard 2026, Sanity Studio, Payload CMS, Strapi, headless content modeling, inline/live preview, beautiful admin, content interface design awards, dark theme admin, image upload UX, dense form design, sidebar navigation, TinaCMS, Storyblok, Directus, Framer/Webflow, Linear command palette, autosave/dirty state, repeater fields.

---

## 1 · Best-in-class admin references (2026)

| # | Reference | Known for | 2–3 specific UX wins to steal |
|---|---|---|---|
| 1 | **Payload CMS 3** (`payloadcms.com`) | Code-first config + React admin UI that **mirrors the schema** — no separate templating layer | • Field definitions ARE the admin UI (single source of truth, like our `content.json`). • Live preview pane rendered from the actual Next/Bun route. • Tabbed document header (Content / Preview / API / Versions) — one screen, four lenses. |
| 2 | **Sanity Studio v3** | Structured-content studio with portable text, real-time collab, “Structure” builder | • Customisable **Structure tool** — sidebar tree can mirror any content graph (we map it to our 29 keys). • “Vision” tab for live GROQ → developers get a power panel inside the editor. • field-level **presence** (avatar on the field someone else is editing). |
| 3 | **Strapi v5** | Auto-generated REST/GraphQL admin, Content-Type Builder, i18n out-of-box | • **Content Manager ↔ Content-Type Builder** split — edit instance vs edit schema (we don’t expose schema; we expose content only). • Draft/Publish toggle + auto-save per draft. • Bulk select bar appears on top when ≥1 row is checked — actions replace header. |
| 4 | **TinaCMS** | Git-backed visual editor — **edits the real rendered page**, live preview is not a separate pane, it IS the editor | • **Inline editing on the real DOM** via `postMessage` bridge to the rendered site. • Form-sidebar driven by a schema (`.tina/schema.ts`) — every field has a typed control. • Git commit message field inside the save dialog — content edit becomes a real diff. |
| 5 | **Storyblok** | Visual Editor with **postMessage bridge**, Bloks (nested components), Notion-style block editor | • Click-to-edit on the live page → highlights the editable Blok → sidebar scrolls to its form. • **Nested repeater fields** with add/move/duplicate/collapse per row. • Field plugins (Vue 3 / now also React) — extensible, every field is a component. |
| 6 | **Directus** | “Every collection is a table, every field is a column” — visual admin over SQL, no schema lock-in | • **Per-collection icon + accent color** → sidebar becomes scannable (we use this for our 10 nav groups). • UI extensions (panels / interfaces / displays / layouts) — every field can be replaced. • Saved layouts (filter+view+sort preset) per collection. |
| 7 | **Framer CMS (2026)** | AI agents on the canvas, native multi-field editing, designer-grade polish | • **Framer Agents** — type “add a Mother’s Day promo code 15% min 4000₽” and the AI fills the form. • Polished micro-interactions: sticky save bar slides up, dirty-dot on tab title. • Real-time multi-user cursors on fields. |
| 8 | **Linear** (not CMS, but the gold standard for keyboard-first admin UX) | Command palette, ⌘K, keyboard navigation everywhere, instant | • **⌘K command palette** — jump to any section / field / action. • Every list row has a hover-action set; no modals for quick edits. • Visible focus ring + full keyboard nav; `j`/`k` to move, `Enter` to edit. |

---

## 2 · UX patterns for a content-editable admin

### 2.1 Layout shell
- **Fixed left sidebar (280 px)** — collapsible to 64 px (icon rail) on ≥1280 px screens, hidden behind a hamburger on <1024 px.
- **Top bar (56 px)** — breadcrumb (Group ▸ Section ▸ Sub-section), global search ⌘K, dirty indicator, autosave clock, «Опубликовать» CTA, theme toggle (matches storefront dark).
- **Main canvas** — split 60% / 40% by default: form on the left, **live preview iframe** on the right. Draggable divider; presets: «Form only», «Split», «Preview only». Below 1024 px → tabs (Edit / Preview).
- **Sticky bottom save bar** — slides up when dirty; shows «Черновик сохранён 12:04», discard button, «Опубликовать» button (primary, gold).

### 2.2 Sidebar navigation patterns (2026)
- **Group → section → sub-section** (3 levels max). 1:1 mapping to `content.json` top-level keys, grouped for scanning (see §3.1).
- **Per-section icon + accent** (Directus pattern) — products gets a bloom-pink flower icon, theme a gold swatch, etc.
- **Count badges** next to lists (`products · 20`, `reviews · 8`).
- **Dirty dot** (amber) next to any section with unsaved changes.
- **Pinned / recent** block at the top — last 5 edited sections (localStorage).
- **Global search ⌘K** opens a command palette: type «her» → jump to hero; type «pion» → first product with «пион» in name; type «#bloom» → jump to theme.bloom token.

### 2.3 Field types (must cover 100% of content.json)
| Field type | Used for | Component |
|---|---|---|
| **text** | single-line strings (labels, names, hrefs) | `<input>` with character counter, monospace for code-y fields (font stacks, hrefs) |
| **textarea** | lead paragraphs, descriptions | auto-grow textarea, max-width 60 ch, soft-wrap |
| **richtext** | `desc` paragraphs, `faq.a` answers | minimal toolbar (bold / italic / link / H3 / list), Markdown or HTML output, **no full WYSIWYG** — keep simple |
| **number** | prices, counts, weights, k coefficients | number input with stepper, optional `min`/`step`/`suffix` («₽», «мин», «×») |
| **range** | `theme.grain` (0–1), `theme.motion` (0–1) | slider with live readout + visual preview |
| **color** | all `theme.*` colors, `blooms[].color`, `greens[].color` | native `<input type=color>` + hex text field + swatch grid from `theme` presets |
| **toggle** | `popular`, `visible`, `active`, `theme.motion` (off variant) | 44 px switch with on/off label |
| **select** | `cat`, `status`, `icon`, `preset` id | dropdown with search; options from sibling keys (e.g. `cat` options come from `categories[]`) |
| **multi-select / chips** | `products[].occ[]` | chip picker — options from `occasions[]` |
| **image** | all `img`, `img2`, `image`, `ogImage` | see §3.5 |
| **date** | `journal[].date`, `reviews.items[].d` | date picker; free-text fallback (Russian format «14 февраля 2026») |
| **repeater** | every `[]` array — products, reviews, zones, faq, journal, guarantees, promos, marquee, ticker, nav, chapters, footer.cols, subscription.plans.feats, reviews.rating.breakdown, hero.stats, atelier.facts, delivery.zones, delivery.slots, delivery.extra, poll.options, users, orders | card list with: drag-handle (reorder), duplicate, delete, collapse/expand; min/max bounds enforced |
| **group** | nested objects (`hero.card1`, `reviews.rating`, `delivery.calc`, `footer.newsletter`, `subscription.plans[]`) | indented panel with its own title; inline validation |
| **key-value** | `theme.fontDisplay`/`fontBody`/`fontMono`, `theme.presets[]` | compact label+input rows |
| **object-list / matrix** | `constructor.{shapes,sizes,blooms,greens,wraps,addons}` | specialized table view: row = item, columns = id/label/price/k/color — editable cells |
| **code/json** | (read-only for `meta`, `admin`) | syntax-highlighted readonly box |

### 2.4 Inline editing & live preview
- **Iframe preview** of `/` (the real storefront) with a cache-buster `?preview=${Date.now()}` and `?admin=1` (suppresses petals / cart UI if desired).
- **Highlight-on-hover bridge**: when admin hovers a form field bound to a visible element on the storefront, the iframe highlights that element with a gold outline (postMessage: `{type:'nf-highlight', path:'hero.titleLines[0]'}`). Clicking the iframe element scrolls the form to its field. (Storyblok/TinaCMS pattern.)
- **Refresh strategy**: on field blur → debounce 600 ms → POST draft to `/api/content?dry=1` (server returns the merged JSON without writing) → iframe reloads with `?preview=ts&draft=1`. On «Опубликовать» → real POST → iframe reloads from canonical.

### 2.5 Autosave, dirty-state, version history
- **Dirty state**: any field edit sets `dirty[path]=true`; tab title becomes `• Hero — НИЛОВ Admin`; sidebar dirty-dot amber on the section; browser `beforeunload` blocks navigation with `Confirm exit without saving?`.
- **Autosave draft** (debounce 1.5 s after last keystroke) → `POST /api/content?draft=1` with `X-Admin-Token`. Draft persisted to `db/content.draft.json` + localStorage mirror. Visible status: «Черновик сохранён 12:04:31».
- **Manual publish**: «Опубликовать» button = real `POST /api/content` (no `draft`), bumps `meta.version` patch, writes `db/content.json` atomically (write-temp + rename), snapshots previous version to `db/versions/content-2026-02-14T12-04.json` (keep last 20).
- **Version history panel** (in tab «История»): diff vs current per field, one-click «Откатить» (reverts content.json to that snapshot, new version).
- **Search**: ⌘K command palette covers sections + fields + record names; server-side `/api/content?q=...` optional.

### 2.6 Keyboard shortcuts
- `⌘K` — command palette (jump / action)
- `⌘S` — Опубликовать (or ⌘Shift+S for draft-only save)
- `⌘/` — show shortcut cheatsheet
- `g` then `s` — go to section (Linear-style sequence)
- `j` / `k` — move between repeater rows
- `e` — edit focused row inline
- `Esc` — close panel / cancel edit
- `?` — help overlay
- `⌘\` — toggle sidebar

### 2.7 Bulk actions
- On list views (products, orders, reviews): checkbox column → when ≥1 selected, top bar becomes a contextual action bar: «Опубликовать видимость», «Скрыть», «Изменить категорию», «Дублировать», «Удалить», «Экспорт CSV».
- Shift+click range select; ⌘A select all on current page.

### 2.8 Save bar (sticky bottom)
- Hidden when not dirty. Slides up (200 ms `cubic-bezier(.2,.8,.2,1)`) when dirty.
- Content: «N несохранённых полей» · «Отменить изменения» · «Сохранить черновик» (ghost) · «Опубликовать» (gold solid, 44 px tall).
- On publish: button shows spinner 800 ms, then success toast: «Опубликовано · версия 2026.2.1 · [просмотр]».

### 2.9 Image upload UX (2026 best practice)
- **Drag-drop anywhere** on an image field — full-field dropzone, dashed gold border, thumbnail preview replaces the empty state.
- **Paste from clipboard** — `Ctrl+V` on a focused image field uploads the pasted image.
- **File picker fallback** — 44 px «Загрузить» button.
- **URL field** — for remote images or existing `/img/...` paths; autocomplete from `img/` directory listing (server provides `/api/img-list`).
- **Crop / focal point** (Phase-2, optional) — set `object-position` focal point for the storefront.
- **Upload flow**: `FormData('file', f)` → `POST /api/upload` (already implemented in `server.js`) → returns `{url:'/img/uploads/up-<hash>.webp'}` → insert into field → preview loads.
- **Validation**: max 5 MB, accept `image/webp,image/jpeg,image/png`, server converts to `.webp` (already does).
- **Existing image picker**: gallery modal showing all `/img/uploads/*` + `/img/products/*` + `/img/editorial/*` with search and thumbnails (lazy-loaded).

### 2.10 Form design for dense admin (2026)
- **Two-column grid** for short fields (id + label, price + old, size + weight).
- **Single column** for long text (lead, desc).
- **Sticky section header** at top of form (section label + last-saved timestamp + per-section «Опубликовать»).
- **Field labels** in Golos Text 13/16 medium; helper text 12/16 in `boneMute`; error text 12/16 in `bloom`.
- **Grouped sections** (cards with 20 px radius — matches storefront `--radius`) separated by 24 px gap. Card title = Playfair 18/24.
- **Inline validation** — don’t wait for submit; show error if `price < 0`, `href` invalid, etc.
- **Required indicator** — small gold dot before label, not red asterisk (matches storefront palette).

---

## 3 · Specific admin.html design spec for Nilov

### 3.1 Sidebar sections (1:1 mapping to content.json)

`content.json` has **28 editable top-level keys** (+ `updatedAt` which is read-only, server-stamped). Mapped into **10 sidebar nav groups** (Directus-style icons + accents) for scannability. Every top-level key is reachable as a sub-section.

| # | Sidebar group (icon, accent) | Sub-sections (= content.json keys) | Notes |
|---|---|---|---|
| 1 | **Обзор** · dashboard · gold | _(synthetic dashboard)_ | last edits, version, dirty count, quick links, last 5 orders, low-stock products |
| 2 | **Витрина** · storefront · bloom | `hero`, `marquee`, `ticker`, `chapters`, `atelier`, `cta` | landing sections — live-preview heavy |
| 3 | **Каталог** · grid · bloom | `products`, `categories`, `occ`, `catalog` | products = repeater of 20 cards; categories/occ = id+label pickers powering `products[].cat`/`occ` |
| 4 | **Поводы и конструктор** · bouquet · moss | `occasions`, `constructor` | constructor = 7 matrix tables (shapes/sizes/blooms/greens/wraps/addons + base price) |
| 5 | **Доставка и подписка** · truck · moss | `delivery`, `subscription` | delivery.zones = 6-row repeater with kw keyword arrays; subscription.plans.feats nested |
| 6 | **Контент** · doc · boneDim | `reviews`, `journal`, `faq`, `guarantees`, `poll` | reviews.rating.breakdown nested; poll.options chips |
| 7 | **Навигация и подвал** · compass · boneDim | `nav`, `footer` | nav 6 links; footer.cols 3 cols × 5 links; footer.newsletter, legalLinks |
| 8 | **Маркетинг и заказы** · tag · gold | `promos`, `orders`, `users` | orders = 7 recent (read-mostly, status changer); users = tiers |
| 9 | **Сайт и SEO** · globe · gold | `meta`, `contacts`, `theme` | meta.seo nested; contacts.geo; theme = full token editor + 3 presets |
| 10 | **Система** · gear · boneMute | `admin`, `updatedAt` (readonly) | admin login/password/sessionMinutes; updatedAt timestamp |

**Count:** 10 nav groups, 28 sub-sections, all 1:1 with content.json keys.

### 3.2 Visual design (match storefront design system)

Re-use the exact tokens from `content.json.theme` so the admin visually belongs to the same brand:

- **Background:** `ink #0D120E` (main), `ink2 #141B15` (cards/sidebar), `ink3 #1C261D` (hover/elevated).
- **Text:** `bone #F4EEE2` (primary), `boneDim #CFC7B7` (labels), `boneMute #8E897D` (helper/meta).
- **Accent:** `bloom #E8607A` (primary actions, dirty dots, errors), `gold #C8A24A` (publish CTA, focus rings, required dots), `petal #E7B4B8` (soft highlights), `moss #2E3F31` (success, secondary backgrounds).
- **Type:** headings Playfair Display Variable 18–28/1.2 (section titles, modal headers); UI body Golos Text Variable 13–15/1.5 (labels, inputs); mono JetBrains Mono Variable 12/1.4 (ids, JSON, font-stack fields).
- **Radii:** 20 px (`--radius`) for cards/modals, 12 px (`--radiusSm`) for inputs/buttons/chips, 999 px (pill) for badges/toggles.
- **Easings/durations:** `cubic-bezier(.2,.8,.2,1)` 200 ms standard; `cubic-bezier(.16,1,.3,1)` 400 ms for sheet reveals; respect `prefers-reduced-motion` → instant.
- **Grain/noise:** optional 0.35 opacity grain texture on sidebar (matches storefront) — keep subtle, never on form fields.
- **Shadows:** none on dark; instead use 1 px `ink` border + `ink3` background lift for elevation.
- **Focus rings:** 2 px `gold` at 50% offset, never purple/blue — keep on-brand.

### 3.3 Field components per content.json section (key examples)

| Section | Field set & component mapping |
|---|---|
| **meta** | `version` text (readonly-ish); `siteName`, `siteNameFull`, `domain`, `legal` text; nested `seo` group: `title` textarea, `description` textarea (char count ≤160), `keywords` textarea, `ogImage` image field. |
| **theme** | color fields for `ink/ink2/ink3/bone/boneDim/boneMute/bloom/petal/gold/sand/moss`; `radius`/`radiusSm` text+preview chip; `container` text; `grain` & `motion` range sliders with live readout; `fontDisplay`/`fontBody`/`fontMono` text inputs with monospace font + sample-preview; `presets[]` repeater (each preset = nested group with same color keys + label + id). Live preview = a 240×160 swatch card rendered from current tokens. |
| **contacts** | text fields for city/phone/phoneHref/phoneNote/email/address/hours/telegram/whatsapp/instagram/vk + labels; nested `geo` group (lat/lng/zoom) with mini-map placeholder. |
| **nav**, **ticker**, **marquee** | simple repeaters of `{label, href}` or strings — inline editable rows, drag-reorder, no cards needed. |
| **hero** | `kicker` text; `titleLines[]` 2-item repeater (large display preview in Playfair); `titleAccent` text; `lead` textarea; `ctaPrimary`/`ctaSecondary` groups (`{label,href}`); `image` image + `imageAlt` text; `card1`/`card2` groups (`{title,value,note}`); `stats[]` repeater (`{value,suffix,label}`) — 4 items, with live counter preview. |
| **chapters** | repeater `{n, title, text, img, alt}` — 3 large cards, image-dominant. |
| **atelier** | kicker/title/lead/signature text; `facts[]` repeater `{k,v}` — 4 items, big-number preview. |
| **occasions** | repeater `{id,label,img,note}` — 6 image cards. |
| **catalog** | simple group `{kicker,title,lead,note}` text fields. |
| **categories**, **occ** | repeaters `{id,label}` — power the `cat`/`occ` select fields in products. Edit cascades. |
| **constructor** | `kicker/title/lead` text; `base` number (₽ suffix); 6 matrix repeaters with table-view: `shapes{}` `{id,label,k,note}`, `sizes{}` `{id,label,k,stems}`, `blooms{}` `{id,label,price,color}`, `greens{}` `{id,label,price,color}`, `wraps{}` `{id,label,price}`, `addons{}` `{id,label,price,note}`. Live price calculator preview: pick shape×size×3 blooms → shows estimated price. |
| **delivery** | `kicker/title/lead` text; `calc` group `{placeholder,button,hint}`; `zones[]` 6-row repeater `{id,name,price,eta,note,kw}` — `kw` is comma-separated chips editor; `slots[]` 8 string rows; `extra[]` 4-icon list `{icon,t,d}` — `icon` from a fixed set (camera/clock/leaf/truck) via select. |
| **subscription** | `kicker/title/lead` text; `periods[]` `{id,label,k}` 3 rows; `plans[]` repeater `{id,name,price,desc,tag,feats[]}` — nested string repeater inside each plan. |
| **reviews** | `kicker/title/lead` text; `rating` group `{value,count,breakdown[]}` — `breakdown` 5 rows `{s,p}` with bar-chart preview; `items[]` repeater `{n,d,src,r,t}` — 8 cards with star-rating select (1–5) and source select. |
| **journal** | repeater `{tag,date,read,title,excerpt,img}` — 3 large cards. |
| **faq** | repeater `{q,a}` — 7 collapsible rows, accordion UI. |
| **guarantees** | repeater `{icon,t,d}` — 4 cards, icon select. |
| **cta** | group `{title,lead,button,phoneLabel}` text fields. |
| **footer** | `about` textarea; `cols[]` 3 groups `{title, links[]}` — nested link repeater `{l,h}`; `newsletter` group `{title,text,placeholder,button,success}`; `bottom` text; `legalLinks[]` repeater `{l,h}`. |
| **promos** | repeater `{code,type,value,min,label,active}` — 4 rows, `type` select (percent/fixed), `active` toggle. |
| **orders** | repeater `{id,date,client,phone,sum,status,items,zone}` — 7 rows, `status` select (new/confirmed/delivered/cancelled), inline status change. |
| **users** | repeater `{name,email,orders,sum,tier}` — 4 rows, `tier` select. |
| **poll** | `question` text; `options[]` chip repeater with live vote-bar preview. |
| **admin** | `login` text, `password` password-field, `sessionMinutes` number. |
| **products** | the big one — 20 cards in a grid. Each card opens an edit drawer: `id` text, `name` text, `sub` text, `price`/`old` number pair, `cat` select (from `categories`), `occ[]` multi-chips (from `occasions`), `flowers[]` string repeater, `badge` text, `img`+`img2` image pair (with paired thumbnail), `size`/`weight` text, `popular`+`visible` toggles, `desc` richtext. |
| **updatedAt** | readonly timestamp display in the System section + footer. |

### 3.4 Live-preview approach (detailed)

- **Default layout**: 60/40 split — form (left, scrollable) + preview iframe (right, sticky).
- **Preview URL**: `/?preview=${Date.now()}&admin=1#<section-anchor>` — `?admin=1` adds a CSS class to `<html>` that suppresses non-essential motion (petals, marquee animation) and shows a thin gold outline on every element bound to a content path (`[data-nf-path]`).
- **Highlight bridge** (postMessage):
  - Hover a form field → admin posts `{type:'nf-highlight', path:'hero.titleLines[0]'}` → storefront iframe outlines that element gold (2 px) + a small floating label `hero.titleLines[0]`.
  - Click an outlined element in the iframe → storefront posts `{type:'nf-focus', path:...}` → admin scrolls form to that field and focuses it.
  - Implemented in `v2026/index.html` with a `data-nf-path` attribute on every rendered content node + a 60-line `nf-admin-bridge.js` listener.
- **Draft preview**: when dirty, the iframe shows a small badge `Черновик` top-right; preview reflects draft (POST `?dry=1` returns merged draft). On publish, badge disappears.
- **Mobile preview toggle**: 3 viewport buttons (mobile 390 / tablet 768 / desktop 1280) — iframe scales.

### 3.5 Image upload flow

```
[Drop zone / Paste / Browse] ──▶ POST /api/upload (FormData: file)
                                     │ X-Admin-Token header
                                     │ server: safePath, write img/uploads/up-<hash>.webp
                                     ▼
                                 { url: '/img/uploads/up-<hash>.webp' }
                                     │
                                     ▼
                          [insert URL into bound field]
                                     │
                                     ▼
                  [field shows 120×120 thumbnail preview]
                                     │
                          [Existing library] button → modal:
                          GET /api/img-list → { dirs:[editorial,products,uploads], files:[...] }
                          thumbnail grid, search, click to insert.
```

- **Bound fields**: every `img`, `img2`, `image`, `ogImage`, `chapters[].img`, `occasions[].img`, `journal[].img` — same component.
- **Validation**: client checks `type` and `size ≤ 5 MB` before upload; rejects with inline error.
- **Paste**: `paste` event on the field; if clipboard has image file, upload directly.
- **Drag from outside**: full-page drop overlay appears on `dragenter` anywhere — drops onto the currently-focused image field.
- **Focal point** (Phase-2): after upload, optional click on the preview sets `focal:{x,y}` stored alongside URL — storefront uses `object-position`.

### 3.6 Save semantics (concrete)

- **Endpoint**: `POST /api/content` (already in `server.js`).
- **Headers**: `X-Admin-Token: <token from db/admin-token.txt>`, `Content-Type: application/json`.
- **Body**: full merged `content.json` (admin keeps in-memory working copy; sends full to keep server dumb — server validates top-level keys against the existing file before write).
- **Modes**:
  - `POST /api/content` (default) = **publish** → atomic write to `db/content.json` (temp file + rename), snapshot previous to `db/versions/content-<ISO>.json`, bump `updatedAt`, return `{ok, version, updatedAt}`.
  - `POST /api/content?draft=1` = **autosave draft** → writes `db/content.draft.json` only; storefront unaffected; admin rehydrates draft on reload.
- **Autosave**: debounce 1.5 s after last keystroke → POST draft → status line `Черновик сохранён 12:04:31`.
- **Publish**:
  - Manual button «Опубликовать» (gold, 44 px, bottom sticky bar).
  - Optional commit message field (TinaCMS-inspired) — saved into `db/versions/content-<ISO>.json` metadata.
  - On success: toast `Опубликовано · v2026.2.1 · [просмотр]`, draft file deleted, dirty state cleared.
- **Conflict handling**: server compares `If-Match: <updatedAt>` header; if mismatch (someone else published since last fetch) → 409 → admin offers «Загрузить новую версию» (merge提示 or overwrite).
- **Version history**: `db/versions/` keeps last 20; admin shows a timeline panel with diff-vs-current and one-click rollback (rollback = new publish, not destructive).

### 3.7 Mobile-responsive admin (tablet editing)

- **≥ 1280 px**: full sidebar + split form/preview.
- **1024–1279 px**: collapsible icon sidebar (64 px), split form/preview preserved.
- **768–1023 px** (tablet): sidebar hidden behind hamburger, form/preview toggle tabs (Edit / Preview) — preview becomes full-screen on tap.
- **< 768 px** (phone): not a primary target, but functional — single column, all repeaters stack, preview reachable via a tab. **Editing on phone is read-mostly** (status changes for orders, publish a draft).
- **Touch targets**: all interactive elements ≥44×44 px (matches AGENTS.md rule 6). Spacing between fields ≥8 px.
- **No hover-only interactions**: every hover state has an equivalent focus/tap state.

### 3.8 Accessibility

- **WCAG 2.2 AA target.**
- **Focus rings**: 2 px gold (#C8A24A) at 50 % offset on every focusable; never removed (`outline: 0` only with custom replacement).
- **Keyboard nav**: full Tab order logical & visible; `⌘K` palette; `j/k` row navigation in lists; `Esc` closes any panel; trap focus inside modals.
- **Touch targets**: ≥44×44 px (rule 6) — toggles, buttons, checkboxes, repeater drag-handles (drag-handle has a 44 px tap area that opens a «move up / move down» menu).
- **Contrast**: bone on ink = 16.4:1 (AAA); boneMute on ink2 = 5.9:1 (AA); bloom on ink2 = 5.2:1 (AA); gold on ink2 = 4.7:1 (AA for large text only — use gold only for ≥18 px text or non-text).
- **Labels**: every field has a visible `<label for=>`; helper text in `aria-describedby`; errors in `aria-invalid=true` + `aria-describedby=err-<id>`.
- **Screen reader announcements**: `aria-live=polite` region announces «Опубликовано», «Сохранено как черновик», «Ошибка: цена не может быть отрицательной».
- **Motion**: `@media (prefers-reduced-motion: reduce)` → all transitions instant, no slide-up save bar (it just appears), no highlight pulse (steady outline).
- **Color is never the only signal**: dirty state shows dot AND text «несохранён»; errors show icon AND text; toggle shows position AND label.

---

## 4 · Anti-patterns to avoid

1. **Modal-only editing** — opening a modal for every repeater row kills flow. Use **inline expand** (accordion) for small rows, **drawer** (right-side sheet) for large records like a product.
2. **Save button per field** — drives users mad. One sticky save bar at the bottom, autosave drafts in between.
3. **Hidden autosave with no indicator** — users don’t trust silent saves. Always show timestamp `Черновик сохранён 12:04:31`.
4. **Separate «preview site» tab that requires a refresh** — breaks the editing flow. Inline iframe preview that reflects drafts is the 2026 standard (TinaCMS, Storyblok).
5. **Generic blue/purple admin theme** — instantly says «WordPress admin». Match the storefront’s dark-ink + bone + bloom + gold palette so the admin feels like the same brand.
6. **Tiny touch targets** (< 40 px) — admins edit on tablets in the studio. 44 px minimum.
7. **Removing the ability to see JSON** — power users want a «JSON» tab to paste in a chunk. Hide it behind an «advanced» disclosure if needed, but don’t remove.
8. **No version history** — owners will break things. Always snapshot last 20 publishes + offer one-click rollback.
9. **Forcing richtext where plain text lives** — `desc` is fine as richtext, but `nav[].label` should be a plain input. Don’t add formatting controls to short labels — adds 60 px of noise per row.
10. **Single 200-field page** — overwhelming. Group into cards with 4–8 fields each, sticky section nav on the right (jump-to-anchor).
11. **Modals for confirming normal actions** — only block destructive ones (delete a product, rollback a version). «Save» should not ask «Are you sure?».
12. **Loading the entire content.json into one form on first paint** — for 20 products with richtext this is 5 MB of DOM. Lazy-render: only the active section is in the DOM; others are virtualised.
13. **Treating the admin as a separate design language** — radii, fonts, easings, colors must match the storefront (`content.json.theme`). If a critic sees admin.html next to index.html, they should read «same brand, different role».
14. **No keyboard shortcuts** — content editors work fast; ⌘K and ⌘S are table stakes in 2026.
15. **Confirm-on-leave prompts for already-autosaved drafts** — annoying. Block leave only when there’s unpublishable work (i.e. dirty after autosave failure, or publish-in-flight).

---

## 5 · Build checklist (for the implementer agent)

- [ ] Layout: 280 px sidebar + 56 px topbar + 60/40 split main canvas, sticky save bar.
- [ ] Sidebar: 10 groups, 28 sub-sections, count badges, dirty dots, per-section icons, ⌘K palette.
- [ ] Field components: text, textarea, richtext, number, range, color, toggle, select, multi-chips, image, date, repeater, group, key-value, matrix, code — implemented as web components or vanilla JS renderers.
- [ ] Live preview: iframe `/?preview=ts&admin=1`, postMessage highlight bridge, viewport toggle (390/768/1280).
- [ ] Image upload: drag-drop + paste + browse + library modal → `/api/upload` → insert URL.
- [ ] Save: autosave 1.5 s debounce to `?draft=1`; «Опубликовать» to `/api/content`; version snapshots to `db/versions/`.
- [ ] Dirty state: tab title dot, sidebar dot, beforeunload guard, sticky save bar.
- [ ] Keyboard: ⌘K, ⌘S, ⌘/, j/k, g+s, Esc, ?.
- [ ] Bulk actions on products / orders / reviews.
- [ ] Mobile: tablet split-tabs, phone read-mostly, 44 px targets.
- [ ] A11y: WCAG 2.2 AA, focus rings, aria-live region, prefers-reduced-motion.
- [ ] Visual: storefront tokens, Playfair headings, Golos UI, 20/12 px radii, brand easings.

---

**End of research.** Builder agent: implement `v2026/admin.html` per §3, using `server.js`’s existing `/api/content` and `/api/upload`. Reference `content.json` directly for the live schema (no schema file to maintain — admin reads content.json keys at load).
