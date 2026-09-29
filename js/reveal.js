/* Reveal-анимация появления секций/карточек при скролле.
   W105 (3-a, P0 «половина сайта невидима»): переработка решения.

   КОНТРАКТ С CSS (не менять врозь):
   - nilov.css (scroll-reveal v2): в ПОЛНОМ режиме (view()-таймлайны живы)
     НЕ-карточные .reveal анимирует сам CSS (scrub, двусторонний); класс
     .reveal--visible глушит таймлайн → секциям в полном режиме класс НЕ
     ставим (иначе снап). «Полный режим» = CSS.supports('animation-timeline:
     view()') && html БЕЗ motion-lite.
   - five.css (M5): карточки (.fc-carousel .reveal, .catalog__grid .reveal)
     в режиме view() получают animation:none и живут ТОЛЬКО классом
     .reveal--visible (каскад fc-card-in + stagger --i). Карусели —
     overflow-x:auto: view()-таймлайн карточки привязался бы к
     ГОРИЗОНТАЛЬНОМУ скроллу трека, поэтому карточкам IntersectionObserver
     нужен ВСЕГДА, в любом режиме.

   ПОРЯДОК СКРИПТОВ (footer.php): reveal.js — defer, СРАЗУ ПОСЛЕ kinetic.js
   (defer): kinetic успевает поставить html.motion-lite (≤4 ядра) ДО нашего
   решения. Раньше reveal.js был sync и завершался до kinetic → на ≤4 ядрах
   motion-w104.css гасил таймлайны, а IO уже не ставился → вечный opacity:0
   (RC1/RC2 рекона 2-a/2-b).

   САМО-ЛЕЧЕНИЕ (никакой контент не должен оставаться невидимым):
   a) MutationObserver на классе <html>: motion-lite добавили ПОЗЖЕ решения
      (смена порядка скриптов в будущем, runtime-переключение) → IO забирает
      и секции (таймлайны убиты motion-w104.css).
   b) Дедлайн-ватчдог: элемент ≥12% виден в вьюпорте дольше 1200мс и всё
      ещё computed opacity < .05 → форс .reveal--visible. Срабатывает, даже
      если сломалось всё остальное (IO, таймлайны, порядок скриптов).
   prefers-reduced-motion / среда без IO — статика: класс всем, всё видно. */
(function () {
  'use strict';

  var root = document.documentElement;
  var targets = Array.prototype.slice.call(document.querySelectorAll('.reveal'));
  if (!targets.length) return;

  /* Reduced-motion или совсем старый браузер: всё видно сразу, без анимаций
     (CSS @media prefers-reduced-motion дублирует это на своей стороне). */
  if ((window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
      || !('IntersectionObserver' in window)) {
    targets.forEach(function (el) { el.classList.add('reveal--visible'); });
    return;
  }

  var cssView = typeof CSS !== 'undefined' && CSS.supports
    && CSS.supports('animation-timeline', 'view()');
  var lite = function () { return root.classList.contains('motion-lite'); };
  /* Полный режим = есть view() и motion-lite НЕ активен: секции — CSS-таймлайн */
  var fullMode = function () { return cssView && !lite(); };

  /* Карточки: хосты из five.css M5 + общий случай — .reveal внутри ЛЮБОГО
     overflow-x скролл-контейнера (будущие карусели): view() там видит
     горизонтальный скролл, не вертикальный (RC3).
     W106 (B2): + .fc-collage — коллаж хитов живёт каскадом fc-card-in
     (stagger --i), а не scrub-таймлайном секций. */
  var CARD_HOST = '.fc-carousel, .fc-collage, .catalog__grid';
  function isCard(el) {
    if (el.closest && el.closest(CARD_HOST)) return true;
    for (var p = el && el.parentElement; p && p !== document.body; p = p.parentElement) {
      var ox = getComputedStyle(p).overflowX;
      if (ox === 'auto' || ox === 'scroll') return true;
    }
    return false;
  }

  /* ---------- IO: единственный постановщик .reveal--visible ---------- */
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) {
        en.target.classList.add('reveal--visible');
        io.unobserve(en.target);
      }
    });
  }, { threshold: .12 });

  function observeAll() {
    var full = fullMode();
    targets.forEach(function (el) {
      if (el.classList.contains('reveal--visible')) return;
      if (full && !isCard(el)) return; /* секции — на CSS-таймлайне */
      io.observe(el);
    });
  }

  /* ---------- Доля элемента в вьюпорте (для синхронного старта и ватчдога).
     Учитываем обе оси: карточка, срезанная каруселью по горизонтали,
     в вьюпорте НЕ считается (IO тоже не считает — clipped). ---------- */
  function visibleFrac(el) {
    var r = el.getBoundingClientRect();
    var vh = window.innerHeight, vw = window.innerWidth;
    if (r.height <= 0 || r.width <= 0) return 0;
    if (r.bottom <= 0 || r.top >= vh || r.right <= 0 || r.left >= vw) return 0;
    var v = (Math.min(r.bottom, vh) - Math.max(r.top, 0)) / r.height;
    var h = (Math.min(r.right, vw) - Math.max(r.left, 0)) / r.width;
    return Math.min(v, h);
  }

  /* ---------- СТАРТ (defer, после kinetic.js — решение принимаем поздно) ---------- */
  observeAll();
  /* Вышефолдным элементам — класс СИНХРОННО, не ждём асинхронный колбэк IO:
     reveal.js исполняется до nilov.js (js-ready), значит между «js-ready
     погасил safety-net» и «IO доставил класс» не будет кадра невидимости. */
  targets.forEach(function (el) {
    if (el.classList.contains('reveal--visible')) return;
    if (fullMode() && !isCard(el)) return; /* секции в полном режиме — таймлайн */
    if (visibleFrac(el) >= .12) el.classList.add('reveal--visible');
  });

  /* ---------- a) МОТИОН-ЛИТ ПРИЕХАЛ ПОЗЖЕ → IO забирает всё ---------- */
  if ('MutationObserver' in window) {
    new MutationObserver(function () { observeAll(); })
      .observe(root, { attributes: true, attributeFilter: ['class'] });
  }

  /* ---------- b) ДЕДЛАЙН-ВАТЧДОГ (1200мс ≥12%-видимости при opacity<.05) ---------- */
  var DEADLINE_MS = 1200;
  var MIN_VIS = .12; /* порог видимости как у IO: щепка на краю — не в счёт */
  var firstSeen = new WeakMap();
  var wdTimer = 0;

  function watchdogTick() {
    if (document.hidden) return;
    var now = performance.now();
    var remaining = 0;
    for (var i = 0; i < targets.length; i++) {
      var el = targets[i];
      if (el.classList.contains('reveal--visible')) continue;
      if (visibleFrac(el) < MIN_VIS) {
        firstSeen.delete(el); /* ушёл из вьюпорта — часы сбрасываются */
        remaining++;
        continue;
      }
      var t0 = firstSeen.get(el);
      if (t0 === undefined) { firstSeen.set(el, now); remaining++; continue; }
      if (now - t0 >= DEADLINE_MS
          && parseFloat(getComputedStyle(el).opacity) < .05) {
        el.classList.add('reveal--visible'); /* крайняя мера: контент обязан быть виден */
      } else {
        remaining++;
      }
    }
    if (!remaining && wdTimer) { clearInterval(wdTimer); wdTimer = 0; }
  }
  wdTimer = setInterval(watchdogTick, 400);
  watchdogTick();
})();
