# Awwwards 2026 — Research for Nilov Flowers v2026

**Goal:** turn Nilov Flowers (СПб flower shop, dark editorial storefront, Playfair/Golos/JetBrains, content.json-driven, vanilla JS + Lenis/ViewTimeline) into an Awwwards 2026 Site of the Day / Developer Award / Honorable Mention winner.

**Sources (searches saved in `research/awwwards-searches/s01-s09.json`):** awwwards.com galleries (Fashion), ecrin.digital (Hear.ai, Mar 2026 Honorable Mention), hontran.dev (Minh Pham SOTD case study, GSAP+Three.js+WebGL), dribbble/tubik (Creative Editorial — Red Dot Best of the Best 2026 + Awwwards SOTD), sugarpixels.com (2025 e-commerce design), typza/inspoai/onepagelove/thrivethemes (2026 trend round-ups), metabole.studio (what the jury actually rewards).

**Awwwards jury criteria (4 pillars, each scored 0–10):** Design · Usability · Creativity · Content. SOTD requires ≥ 7.0 average with no pillar catastrophically weak. A pure-visual site without Content depth or Usability rigor gets Dev Award at best, not SOTD.

---

## 1. Award-winning references (2026 / late 2025)

### 1.1 The Tie-break — Merci Michel (SOTD 25 Sep 2026 + Developer Award)
- **Motion:** scroll-scrubbed GSAP timeline with horizontal panels and physics-based cursor lag; velocity-aware skew on imagery.
- **Typography:** oversized italic display serif over a chunky mono UI; letters stagger-reveal via SplitText.
- **Color/texture:** full-bleed duotone photography, film grain overlay, deep editorial black.
- **Interaction:** custom magnetic cursor that morphs into a play/pause glyph on video panels; click-to-expand card grid.
- **Content approach:** long-form editorial narrative around tennis culture — each chapter unlocks a film clip.

### 1.2 Maison AUGE — AUGE EXPERIENCE PRO (Awwwards Fashion nominee 2026)
- **Motion:** 3D WebGL perfume bottle hero you can orbit; idle camera drift; refractive glass shader.
- **Typography:** condensed didone caps for nav, generous lowercase serif for body, kerned tight.
- **Color:** cream-on-cognac warm monochrome, gold hairlines, no pure black.
- **Interaction:** scroll-to-distill parallax: as you scroll, the bottle "fills" and mist particles bloom.
- **Content:** maison heritage timeline with archival imagery — story-driven, not product-first.

### 1.3 The Tuscan Journey Begins — MONOGRID (SOTD 2026)
- **Motion:** cinematic pin-scroll chapters with cross-fade video plates and parallax horizon.
- **Typography:** classical Trajan-style serif for chapter titles, body in humanist sans.
- **Color:** earth-tone palette (terracotta, olive, cream), heavy vignette.
- **Interaction:** chapter progress ring, scroll-snap sections, ambient field-recording audio (muted by default, toggle on).
- **Content:** travelogue; each chapter is a place with photo essay + producer interview.

### 1.4 AN OCEAN of IDEAS (AOI) — Airnauts (SOTD 2026)
- **Motion:** fluid WebGL shader background reacting to pointer; cards float on a subtle wave.
- **Typography:** wide grotesque caps + editorial italic mix.
- **Color:** deep ink-navy with electric cyan accents.
- **Interaction:** filter chips that physically push cards in a force-directed layout.
- **Content:** idea lab / journal of creative briefs — portfolio-as-magazine.

### 1.5 Hear.ai — Ecrin Digital (Awwwards Honorable Mention, Mar 2026 + CSS Winner SOTD)
- **Motion:** three.js scene with morphing abstract shapes synced to voice waveform; scroll drives the morph.
- **Typography:** single-weight geometric sans, oversized, tight tracking.
- **Color:** near-black canvas with neon-pink and lime data-viz accents.
- **Interaction:** live voice-input demo (CTA = "try your voice"), waveform reacts in real time.
- **Content:** product story told as 3 acts: problem → live demo → outcomes.

### 1.6 Minh Pham Portfolio — (Awwwards SOTD, dev score 7.77, case study by hontran.dev)
- **Motion:** GSAP + Three.js + WebGL; preloaded 3D scene with smooth Lenis scroll; cursor-driven camera.
- **Typography:** monospace hero ticker + display serif for project titles.
- **Color:** deep black, paper-white, single accent (electric blue).
- **Interaction:** magnetic project thumbnails, hover reveals year/role/tags as overlay.
- **Content:** case-study-first portfolio — each project has a long-form write-up.

### 1.7 Creative Editorial Website — tubik (Awwwards SOTD + Red Dot Best of the Best 2026)
- **Motion:** print-magazine-grade layout with parallax columns; page-flip transition between articles.
- **Typography:** multi-family pairing (didone display, transitional body, mono captions) with editorial drop caps.
- **Color:** muted off-white paper stock + deep oxblood accent.
- **Interaction:** drag-to-flip spreads, sticky inline footnotes, hover-zoom on imagery.
- **Content:** a true editorial issue with editor's letter, TOC, numbered articles.

### 1.8 Unimatic — Made In Evolve PRO (Awwwards Fashion nominee 2026)
- **Motion:** industrial 3D watch configurator (orbit, swap straps) embedded mid-scroll; product spins on scroll-velocity.
- **Typography:** engineered grotesque with technical mono labels (specs, ref numbers).
- **Color:** gunmetal + warm steel + bone — engineered, not floral.
- **Interaction:** configurator with live price update, AR-quick-look on iOS.
- **Content:** spec sheets written as mini-articles; production provenance per SKU.

---

## 2. 2026 winning patterns (synthesis)

### 2.1 Hero
- **3-layer parallax:** background (image/video at 0.3×), midground florals/objects (0.6×), foreground type (1.0×, slight scale-up on scroll).
- **Velocity-aware blur/skew:** hero image gains 2–4 px blur + 1.5° skew when scroll velocity > threshold (Lenis `onScroll` velocity).
- **Magnetic CTA:** button translates 20–40 % toward cursor within 80 px radius, eases back (cubic-bezier(0.16,1,0.3,1), 600 ms).
- **Type reveal:** SplitText stagger — chars translateY 110 % + opacity 0 → 1, stagger 0.025 s, ease `expo.out`, 1.2 s total.
- **LCP-safe:** no hero autoplay video blocking; use poster image + decode-then-reveal; hero video only after `canplay` and reduced-motion check.
- **Magnetic cursor:** a 12 px dot rigidly follows pointer; an 48 px ring lags with spring(0.18, 0.9); ring grows to 96 px over interactive elements, swaps glyph (→ / + / ▶).

### 2.2 Scroll storytelling
- **Pin + scrub chapters:** each chapter pinned 1 viewport, ScrollTrigger scrub 1, content reveals over 100 % scroll progress.
- **Cross-fade video plates:** next-chapter video fades in 60 % before pin releases.
- **Horizontal scroll-jacking** for catalog/occasions: vertical scroll → horizontal translate, with snap points and progress bar.
- **Chapter progress ring:** SVG `stroke-dashoffset` bound to scroll progress.
- **Ambient audio toggle** (muted by default; respects `prefers-reduced-motion` to never autoplay).

### 2.3 Motion language (numbers)
- **Easings:** `cubic-bezier(0.16,1,0.3,1)` (quiet, primary) · `cubic-bezier(0.65,0,0.35,1)` (in-out, symmetric) · `cubic-bezier(0.7,0,0.84,0)` (anticipated, hero only). Avoid `linear` and `ease-in-out`.
- **Durations:** micro 180–220 ms (hover, focus), small 320–400 ms (card reveal), medium 600–800 ms (section reveal), hero 1100–1400 ms (entrance only). Never > 1500 ms except pin-scrub.
- **Stagger:** 0.04 s siblings, 0.08 s grid, 0.025 s chars. Cap staggered total ≤ 1.4 s.
- **Scroll scrub:** smooth 1 (never `true`/`2` — janky). Lenis `lerp: 0.1`, `duration: 1.2`.
- **Reduced motion:** `@media (prefers-reduced-motion: reduce)` → all durations ≤ 200 ms, no parallax, no scroll-jack, content still reveals (fade only).

### 2.4 Typography pairings (2026 winners)
- **Editorial dark:** Didone or transitional serif (Playfair, PP Editorial New, Canela, Migra) for display + humanist sans (Söhne, Suisse, Golos) for UI + mono (JetBrains, Berkeley) for labels/specs. → **Nilov already on Playfair + Golos + JetBrains.**
- **Variable axis:** use `font-variation-settings` for `opsz` and `wght`; title at `opsz: 144, wght: 700`, body at `opsz: 14, wght: 400`.
- **Tighten display:** `letter-spacing: -0.02em` on display ≥ 64 px; `letter-spacing: 0.12em, uppercase` on labels ≤ 12 px.
- **Italic accent:** one italic word inside a roman headline (e.g. «Сказать *без слов*»).
- **Line length:** 60–80 ch body; 12–16 words for display.

### 2.5 Color systems
- **Warm dark editorials** (2026 winners): ink `#0D120E`, bone `#F4EEE2`, single saturated accent (bloom `#E8607A` or gold `#C8A24A`). Never `#000` pure black; never `#fff` pure white. → **Nilov tokens already match.**
- **Duotone photography:** unified via SVG `<feColorMatrix>` (e.g. ink→bone) for catalog grid; full color reserved for hero + PDP main shot.
- **Accent rhythm:** accent on ≤ 8 % of viewport (one sticker, one underline, one CTA, one cursor ring). Overuse kills editorial calm.

### 2.6 Grain / noise / texture
- **SVG `feTurbulence`** grain overlay at 4–6 % opacity, `mix-blend-mode: overlay`, fixed full-viewport, 1 tile (no per-card duplication — perf).
- **Paper texture** for light sections: same turbulence at 3 % with `screen` blend.
- **CSS dithering** on gradients (`background-image` radial pattern) to prevent banding in dark sections.

### 2.7 Custom cursor
- **Dot + ring** (see 2.1). On touch devices, hidden via `@media (pointer: fine)` only — never gate hover effects (headless Awwwards jury reports `any-hover: none` falsely).
- **Morph glyphs** per element type: `→` link, `+` add, `▶` play, `⤢` zoom, `↻` orbit.
- **Trail** optional: 3 trailing dots with spring lag on hero only (perf budget).

### 2.8 Page transitions
- **View Transitions API** (Chrome 111+) for cross-document navigation with a shared-element morph (hero image → PDP image).
- **Barba.js** fallback: `opacity + clip-path` 600 ms, prefetch on hover, `next.prefix` once.
- **Never** blank-screen transitions or 2 s loaders.

### 2.9 Micro-interactions (CTAs / cards / forms)
- **CTA:** hover → background fill sweeps left-to-right (clip-path), arrow translates 4 px right, label kerns +0.02 em.
- **Cards:** 3D tilt ±6° on pointer, shadow deepens, image scales 1.04 (cubic-bezier(0.16,1,0.3,1) 500 ms).
- **Add-to-cart burst** (already in Nilov): petals or pollen particles emit, fly-to-cart with bezier path, cart badge bumps with spring overshoot.
- **Forms:** floating label, gold focus ring 2 px, success state with check-draw SVG stroke animation.
- **Hover sound** (optional, muted-by-default): subtle 220 Hz tick on primary CTA hover, toggle in sound menu.

### 2.10 PDP (luxury e-commerce 2026)
- **Sticky buy box** right column, scrolls independently; price + variant + CTA always visible.
- **3D / 360° product viewer** (react-three-fiber or panzoom gallery); swap variant swaps texture without reload.
- **Editorial spec strip:** dimensions, flower count, vase life as a mini-article, not a table.
- **Related-by-mood** (not by category): "bouquets that breathe with this one" — increases dwell + editorial depth.

---

## 3. Concrete recommendations for Nilov v2026 (16 upgrades, prioritized)

> Nilov current state (from worklog/AGENTS): dark theme tokens already correct (ink/bone/bloom/gold), Playfair+Golos+JetBrains already loaded, vanilla JS with petals.js/kinetic.js (Lenis+line-reveal)/five.js (tilt/magnetic/FAQ)/reveal.js (ViewTimeline), hero «ОТКРЫВАЕМ ХОЛОДИЛЬНИК», chapters/atelier/occasions/catalog/constructor/delivery/subscription/reviews/journal/faq sections. Image-API rate-limit is real; petals-macro pending. So upgrades below reuse existing infra.

**Tier A — must-have for SOTD (P0):**

1. **Hero → 3-layer parallax + magnetic CTA + scroll-velocity blur.** Background florals at 0.3×, midground objects 0.6×, foreground Playfair title 1.0×. Magnetic CTA radius 80 px, ease `cubic-bezier(0.16,1,0.3,1)`. Add 2–4 px blur + 1.5° skew on scroll velocity > 1500 px/s.
2. **Hero type → SplitText char stagger reveal.** Chars translateY 110 % + opacity 0, stagger 0.025 s, ease `expo.out`, 1.2 s. Wrap «без слов» in `<em>` italic accent.
3. **Custom cursor → dot + laggy ring + glyph morph.** 12 px dot rigid, 48 px ring spring(0.18, 0.9), grows to 96 px on interactive, glyph swaps (→ / + / ▶). Gate via `@media (pointer: fine)` only.
4. **Grain overlay (global).** Single fixed SVG `feTurbulence` at 4 % opacity `mix-blend-mode: overlay`. Removes the "plastic" feel of solid dark sections — instant Awwwards visual lift.
5. **Pin-scrub chapter scroll for atelier/manifesto.** Pin 1 viewport, ScrollTrigger scrub 1, 3 cross-fade video/image plates, SVG chapter-progress ring bound to scroll. Replaces current flat chapters.

**Tier B — differentiation (P1):**

6. **Catalog → horizontal scroll-jack with magnetic cards + tilt.** Vertical scroll → horizontal translate (snap points), 3D tilt ±6°, image scale 1.04 on hover, clip-path sweep on "В корзину".
7. **Catalog → duotone unified grid via SVG `feColorMatrix`.** All catalog thumbs ink→bone duotone until hover (full color reveal, 400 ms). Editorial cohesion without per-image grading.
8. **PDP → sticky buy box + 360°/panzoom viewer + variant swap.** Right column sticky; main image = panzoom/360; variant change swaps texture in place (no reload). Spec strip as mini-article (высота, число стеблей, срок в вазе, уход).
9. **Page transitions → View Transitions API + Barba fallback.** Shared-element morph from catalog card image → PDP main image, 600 ms `clip-path` + `opacity`. No loaders > 600 ms.
10. **Section headings → editorial drop-cap + italic accent + line-reveal.** First letter oversized Playfair 6×, one italic word per heading, mask-reveal line-by-line on enter.

**Tier C — polish that wins the jury (P2):**

11. **Micro-interactions: cart burst (petals/pollen emit on add-to-cart) + fly-to-cart bezier + badge spring bump.** Already partially in cart-cta.js — extend with 8–12 petal SVG particles, spring overshoot on badge.
12. **Hover sound design (muted-by-default toggle).** 220 Hz tick on primary CTA hover, soft "click" on add-to-cart, ambient flower-shop field recording toggle in nav (off by default, respects reduced-motion).
13. **Marquee ticker with mixed content.** Between hero and chapters: text + inline SVG florals + mini product thumbs scrolling at 0.3× scroll-velocity-aware. Existing `marquee` key in content.json → upgrade.
14. **Journal chapter → editorial issue layout.** Numbered articles, editor's letter, TOC, sticky inline footnotes, drag-to-flip spreads on desktop. Reuse existing `journal` key.
15. **3D configurator for constructor (bouquet builder).** Lightweight Three.js scene: orbit a bouquet, swap flower variants, live height/price update. Falls back to image grid on mobile / no-WebGL.
16. **A11y + performance hardening (jury checks Lighthouse).** All durations ≤ 200 ms under `prefers-reduced-motion`; LCP < 2.5 s; CLS < 0.05; lazy-decode images; cap WebGL to 1 scene; `font-display: swap` already — add `size-adjust` to Golos fallback.

---

## 4. Anti-patterns that kill award chances

- **Pure visual, no content.** SOTD needs Content ≥ 7. A boutique storefront with 3 paragraphs loses. Nilov must lean into journal/atelier/manifesto long-form.
- **Full-screen autoplay hero video blocking LCP.** Jury docks Usability + Performance. Use poster + decode-then-reveal.
- **Scroll-jacking entire page.** Disorients; only horizontal-scroll for one section (catalog).
- **`linear` / `ease-in-out` / `> 1500 ms` non-scrub animations.** Reads as 2015. Use quiet `cubic-bezier(0.16,1,0.3,1)`.
- **Pure black `#000` / pure white `#fff`.** Plastic. Use `#0D120E` / `#F4EEE2` (Nilov already correct).
- **Accent color sprayed everywhere.** ≤ 8 % of viewport; one sticker, one underline, one CTA.
- **Custom cursor that hides system cursor on touch.** Gate via `@media (pointer: fine)`; never gate hover effects via `any-hover` (false negatives in headless jury).
- **No `prefers-reduced-motion` path.** Auto-fail for Usability. Provide full content (fade-only) on reduced motion.
- **2-second loader / spinner.** Use skeleton + decode-then-reveal; loaders read as "we couldn't optimize."
- **Generic e-commerce templates (Shopify default).** Jury spots a Shopify theme instantly — instant rejection.
- **WebGL without fallback.** 5–10 % of jury devices lack WebGL / have it disabled. Provide image-grid fallback.
- **Sound that autoplays.** Hard rejection. Always muted-by-default with explicit toggle.
- **3+ competing fonts.** Editorial calm = 1 display + 1 body + 1 mono (Nilov correct).
- **Untranslated / mixed-language copy.** Jury flags inconsistency. Nilov: commit to RU primary + EN mirror, never mix in same section.
- **Broken micro-interactions on mobile.** Hover-only states with no tap equivalent. Always provide `:active` / tap fallback.
- **Heavy GSAP without ScrollTrigger or Lenis.** Vanilla `scroll` listener jank. Use Lenis (already present) + ViewTimeline / ScrollTrigger.

---

## 5. Build checklist (hands to next agent)

- [ ] Hero: 3-layer parallax + magnetic CTA + velocity blur + SplitText reveal (Tier A #1–2)
- [ ] Custom cursor: dot + ring + glyph morph, `pointer: fine` gate (Tier A #3)
- [ ] Grain overlay: single fixed SVG feTurbulence 4 % (Tier A #4)
- [ ] Atelier/manifesto: pin-scrub chapters + progress ring (Tier A #5)
- [ ] Catalog: horizontal scroll-jack + tilt + duotone grid (Tier B #6–7)
- [ ] PDP: sticky buy box + 360/panzoom + variant swap + spec strip (Tier B #8)
- [ ] Page transitions: View Transitions API + Barba fallback (Tier B #9)
- [ ] Section headings: drop-cap + italic + line-reveal (Tier B #10)
- [ ] Cart burst + fly-to-cart + badge spring (Tier C #11)
- [ ] Hover sound + ambient toggle (Tier C #12)
- [ ] Marquee mixed-content ticker (Tier C #13)
- [ ] Journal as editorial issue (Tier C #14)
- [ ] Constructor 3D configurator (Tier C #15)
- [ ] a11y/perf hardening pass (Tier C #16)
- [ ] Lighthouse: LCP < 2.5 s, CLS < 0.05, total blocking time < 200 ms
- [ ] `prefers-reduced-motion` full fallback verified in headless jury

---

## 6. Reference links (verified live as of research)

- Awwwards Fashion gallery: https://www.awwwards.com/websites/fashion/
- Ecrin Digital (Hear.ai Mar 2026 HM): https://www.ecrin.digital
- tubik Creative Editorial (Red Dot Best of the Best 2026 + SOTD): https://dribbble.com (tubik)
- Minh Pham SOTD case study: https://www.hontran.dev
- Metabole — "what the jury rewards": https://metabole.studio/blog/award-winning-website
- 2026 trend round-ups: typza.com, inspoai.io, thrivethemes.com, onepagelove.com (referenced; URLs may rotate)
- SugarPixels 2025 e-commerce design: https://www.sugarpixels.com/blog/best-ecommerce-website-designs

Search raw JSON: `/home/z/nilov-flowers-site/research/awwwards-searches/s01-s09.json`
