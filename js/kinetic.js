/* kinetic.js — smooth scroll (Lenis) + кинетическая типографика (W104-c, motion-агент).
   Vanilla ES2020, IIFE-паттерн сайта. Загружается на всех страницах (footer.php).

   1) LENIS SMOOTH SCROLL (js/vendor/lenis.min.js, self-host, MIT):
      lerp .14, wheelMultiplier 1, anchors:false (якоря ведём СВОИМ
      capture-обработчиком ниже — vendored onClick не делает
      preventDefault), allowNestedScroll (нативный скролл вложенных
      областей — карусели, корзина-drawer). НЕ активируется на
      pointer:coarse (тач) и prefers-reduced-motion — там остаётся
      нативный скролл.
      Совместимость с CSS scroll-driven анимациями (animation-timeline:
      view() — manifesto-параллакс): Lenis не подменяет нативный скролл,
      а ведёт его через scrollTo каждый кадр — scroll-события остаются
      нативными, таймлайны продолжают работать.
      Служебные html.lenis-классы + .no-scroll-лок оверлеев — css/motion-w104.css.

   2) LINE-REVEAL (кинетическая типографика): manifesto-цитата и premium-
      заголовки (.fc-manifesto__text, .fc-premium__title,
      .fc-footer-tagline__text) разбиваются на СТРОКИ при загрузке
      (по-словная разметка → замер offsetTop → группировка, слова не
      рвутся), translateY-маска со stagger 90мс, триггер
      IntersectionObserver (порог .3), один раз; после анимации разметка
      восстанавливается (адаптивные переносы не застывают).
      Разбивка — после document.fonts.ready (Playfair меняет переносы).

   3) H2-АКСЕНТЫ: Playfair-курсивы в eyebrow секций (.fc-row__eyebrow em)
      получают лёгкий slide-up + fade при появлении.

   4) СОБЫТИЙНЫЙ ПЕТАЛ-БЁРСТ (W104-η, C3-M3 P1.3): переиспользуемое ядро
      кассового бёрста (cart-cta.js, W104-δ) — window.NF_BURST.petals(opts).
      Потребитель: order-thanks.php (бёрст на странице успеха — «третье
      касание сигнатуры» после hero-канваса и add-to-cart). Кассовый
      бёрст в cart-cta.js не тронут (путь покупки — без регрессии).

   Деградация: скрытые состояния существуют ТОЛЬКО под html.kinetic-ready
   (класс ставит этот скрипт) — без JS/при сбое все тексты видимы;
   prefers-reduced-motion — контент виден сразу, без анимаций. */
(function () {
  'use strict';

  const ROOT = document.documentElement;
  const reduced = () => !!(window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const coarse = () => !!(window.matchMedia
    && window.matchMedia('(pointer: coarse)').matches);

  /* ============ 0. MOTION-LITE (W104-β, C1-M P0-2: скролл-jank) ============
     Слабое железо (≤4 ядер — включая 2-ядерный жюри-стенд) не тянет
     десятки scroll-driven view()-таймлайнов на каждый кадр скролла
     (замер: p95 39–67мс). html.motion-lite — CSS (motion-w104.css)
     переводит scroll-driven анимации на IO-fallback (reveal.js уже
     умеет: класс .reveal--visible + transition), параллакс/блум —
     статик. Lenis и кинетика остаются. 8+ ядер — полный motion. */
  (function motionLite() {
    if (reduced()) return;
    var cores = navigator.hardwareConcurrency || 8;
    if (cores <= 4) ROOT.classList.add('motion-lite');
  })();

  /* ============ 1. LENIS ============ */
  (function smoothScroll() {
    if (reduced() || coarse()) return;
    if (typeof window.Lenis !== 'function') return;

    let lenis;
    try {
      lenis = new window.Lenis({
        lerp: 0.14,             /* W104-δ (C2-M2 P1.5): .12 → .14 — хвост
                                   короче, отклик живее, без рывков */
        wheelMultiplier: 1,
        autoRaf: true,          /* собственный rAF-цикл Lenis */
        anchors: false,         /* W104-δ (C2-M2 P0-1): свой перехват ниже —
                                   vendored onClick БЕЗ preventDefault даёт
                                   нативный fragment-прыжок = телепорт */
        allowNestedScroll: true /* вложенные скролл-области (drawer, карусели) — нативно */
      });
    } catch (e) {
      return; /* непредвиденное — остаёмся на нативном скролле */
    }
    window.NF_LENIS = lenis;

    /* ============ W104-δ (C2-M2 P0-1): ЯКОРЯ БЕЗ ТЕЛЕПОРТА ============
       Воспроизведено 4 раза (включая реальный CDP-клик «Каталог»):
       scrollY 0 → мгновенный телепорт ~6587 → обратный рывок к ~1400 →
       повторный глисс 1.2-1.4с. Причина: lenis.min.js onClick не делает
       preventDefault — нативный fragment-прыжок исполняется мгновенно,
       а затем Lenis доезжает со СТАРОЙ позиции. Фикс: capture-фаза клика
       по same-page ссылкам с hash → preventDefault (нативный прыжок
       погашен) + ОДИН lenis.scrollTo с offset −96 (sticky-хедер ~73px
       + воздух). Затронуты: nav «Каталог» (/#catalog), hero-CTA
       «Выбрать букет» (#catalog), «Смотреть все», футер-якоря, таббар.
       hash в URL поддерживаем pushState'ом, фокус — на цель по прилёте
       (как после нативного якоря). */
    var ANCHOR_OFFSET = -96;
    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0
          || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (lenis.isStopped) return; /* оверлей со скролл-локом — нативно */
      var t = e.target;
      var a = t && t.closest ? t.closest('a[href]') : null;
      if (!a || !a.hash || a.hash === '#') return;
      var href = a.getAttribute('href') || '';
      /* тот же документ: голый #hash ИЛИ абсолютный /path#hash, совпадающий
         с текущим (отличающийся ?query = другая страница — пусть ходит
         нативной навигацией, её читает catalog-filter.js) */
      var norm = function (p) { return p === '/index.php' ? '/' : p; };
      var samePage = href.charAt(0) === '#'
        || (a.host === location.host
            && norm(a.pathname || '') === norm(location.pathname)
            && (a.search || '') === (location.search || ''));
      if (!samePage) return;
      var id = decodeURIComponent(a.hash.slice(1));
      var el = document.getElementById(id);
      if (!el) {
        if (id === 'top') { /* «наверх» без цели в DOM */
          e.preventDefault();
          lenis.scrollTo(0, { offset: ANCHOR_OFFSET });
        }
        return; /* цели нет — браузер и так ничего не делает */
      }
      e.preventDefault();
      try { history.pushState(null, '', a.hash); } catch (err) { /* file:// и пр. */ }
      lenis.scrollTo(el, {
        offset: ANCHOR_OFFSET,
        onComplete: function () {
          /* sequential focus starting point — как у нативного якоря */
          if (el.matches('a[href],button,input,select,textarea,[tabindex]')) {
            el.focus({ preventScroll: true });
          } else {
            el.setAttribute('tabindex', '-1');
            el.focus({ preventScroll: true });
          }
        }
      });
    }, { capture: true });

    /* W104-β (C1-M P1) → W104-δ: Lenis-десинк. onNativeScroll у Lenis
       синхронизирует animatedScroll только пока isScrolling !== 'smooth' —
       внешний скачок скролла (bfcache/scroll-restoration, программный
       window.scrollTo) оставляет animatedScroll устаревшим, и следующий
       якорный клик телепортирует к старой позиции → откат → повторный
       пробег. Рассинхрон ≥120px — lenis.reset(). Якорный grace-режим
       больше не нужен: с W104-δ нативный fragment-прыжок погашен
       preventDefault'ом (см. обработчик выше), actualScroll во время
       якорной анимации ведёт сам Lenis. */
    window.addEventListener('scroll', function () {
      if (lenis.isStopped) return;
      if (lenis.isScrolling === 'smooth') return; /* анимацию ведёт Lenis */
      if (Math.abs(lenis.actualScroll - lenis.animatedScroll) >= 120) {
        lenis.reset();
        window.__nfLenisResets = (window.__nfLenisResets | 0) + 1; /* диагностика */
      }
    }, { passive: true });

    /* Оверлеи со своим скроллом: колесо над ними не должно вести страницу.
       #cartPanel — в разметке footer.php; .lightbox создаёт lightbox.js
       (sync-скрипт, исполняется раньше этого defer-скрипта). */
    document.querySelectorAll('#cartPanel, .lightbox').forEach((el) => {
      el.setAttribute('data-lenis-prevent', '');
    });

    /* Скролл-лок оверлеев (.no-scroll на html/body ставят cart-ui.js и
       lightbox.js): пока открыт drawer/лайтбокс — Lenis останавливаем
       (html.lenis-stopped), закрыли — запускаем обратно. */
    const syncLock = () => {
      const locked = ROOT.classList.contains('no-scroll')
        || document.body.classList.contains('no-scroll');
      if (locked && !lenis.isStopped) lenis.stop();
      else if (!locked && lenis.isStopped) lenis.start();
    };
    if (window.MutationObserver) {
      const opts = { attributes: true, attributeFilter: ['class'] };
      new MutationObserver(syncLock).observe(ROOT, opts);
      new MutationObserver(syncLock).observe(document.body, opts);
      syncLock();
    }
  })();

  /* ============ 2 + 3. КИНЕТИЧЕСКАЯ ТИПОГРАФИКА ============ */
  const LINE_TARGETS = '.fc-manifesto__text, .fc-premium__title, .fc-footer-tagline__text';
  const ACCENT_TARGETS = '.fc-row__eyebrow em';
  const STAGGER = 90;    /* мс между строками */
  const DURATION = 720;  /* мс reveal строки */

  /* Разбивка на строки: слова → замер → маски-обёртки.
     Оригинальный текст хранится в data-kin-text (пере-разбивка при resize
     и восстановление после анимации). */
  function splitLines(el) {
    const raw = el.getAttribute('data-kin-text')
      || (el.textContent || '').replace(/[^\S\u00A0]+/g, ' ').trim(); /* W104-zeta: NBSP сохраняем — тире манифеста не открывает строку */
    if (!raw) return false;

    const words = raw.split(' ');
    el.textContent = '';
    const spans = words.map((w, i) => {
      const s = document.createElement('span');
      s.className = 'kin-w';
      s.textContent = w;
      el.appendChild(s);
      if (i < words.length - 1) el.appendChild(document.createTextNode(' '));
      return s;
    });

    /* группировка по строкам: offsetTop слова (все span — один offsetParent,
       чтения батчатся в один reflow после серии аппендов) */
    const lines = [];
    let top = null;
    for (const s of spans) {
      if (top === null || Math.abs(s.offsetTop - top) > 4) {
        lines.push([]);
        top = s.offsetTop;
      }
      lines[lines.length - 1].push(s);
    }

    el.textContent = '';
    lines.forEach((group, li) => {
      const mask = document.createElement('span');
      mask.className = 'kin-line';
      const inner = document.createElement('span');
      inner.className = 'kin-line__in';
      inner.style.setProperty('--kin-i', String(li));
      group.forEach((s, i) => {
        inner.appendChild(s);
        if (i < group.length - 1) inner.appendChild(document.createTextNode(' '));
      });
      mask.appendChild(inner);
      el.appendChild(mask);
    });

    el.classList.add('kin-split');
    el.setAttribute('data-kin-text', raw);
    return true;
  }

  /* После анимации возвращаем исходный текст — переносы снова адаптивны */
  function restore(el) {
    const raw = el.getAttribute('data-kin-text');
    if (raw === null) return;
    el.removeAttribute('data-kin-text');
    el.classList.remove('kin-split', 'kin-in');
    el.textContent = raw;
  }

  function kinetic() {
    if (reduced()) return;
    if (!('IntersectionObserver' in window)) return; /* тексты остаются видимыми */

    const targets = document.querySelectorAll(LINE_TARGETS);
    const accents = document.querySelectorAll(ACCENT_TARGETS);
    if (!targets.length && !accents.length) return;

    /* только теперь CSS получает право прятать строки (без JS тексты видны) */
    ROOT.classList.add('kinetic-ready');

    const io = new IntersectionObserver((entries) => {
      for (const en of entries) {
        if (!en.isIntersecting) continue;
        io.unobserve(en.target);
        const el = en.target;
        el.classList.add('kin-in');
        if (el.classList.contains('kin-split')) {
          const n = el.querySelectorAll('.kin-line__in').length;
          setTimeout(() => restore(el), DURATION + n * STAGGER + 80);
        }
      }
    }, { threshold: 0.3 });

    let splitAny = false;
    targets.forEach((el) => {
      if (splitLines(el)) { splitAny = true; io.observe(el); }
    });
    accents.forEach((el) => {
      el.classList.add('kin-acc');
      io.observe(el);
    });
    if (!splitAny && !accents.length) {
      ROOT.classList.remove('kinetic-ready');
      return;
    }

    /* До reveal при resize — пере-разбивка (после reveal разметка уже
       восстановлена, пере-разбивка не нужна) */
    let rt = 0;
    window.addEventListener('resize', () => {
      clearTimeout(rt);
      rt = setTimeout(() => {
        targets.forEach((el) => {
          if (el.classList.contains('kin-in') || !el.getAttribute('data-kin-text')) return;
          splitLines(el);
        });
      }, 180);
    }, { passive: true });
  }

  /* Шрифты самохостятся, но до замера строк они должны быть загружены:
     Playfair italic меняет ширины слов и переносы. */
  if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
    document.fonts.ready.then(() => kinetic());
  } else {
    kinetic();
  }

  /* ============ 4. СОБЫТИЙНЫЙ ПЕТАЛ-БЁРСТ — window.NF_BURST (W104-η,
     C3-M3 P1.3 «третье касание сигнатуры») ============
     Переиспользуемое ядро кассового бёрста (cart-cta.js, W104-δ): те же
     спрайты .nf-burst-petal (стили/палитра — css/motion-w104.css, тот же
     визуальный язык) разлетаются из точки события. Отличие от кассового:
     опция fall — вторая фаза «падения» (лепестки оседают на страницу),
     и произвольная точка/количество/дальность — событие задаёт страница
     (order-thanks: бёрст над подтверждением заказа через 600мс после
     load, 10–14 лепестков, 1.2–1.5с).
     Механика: WAAPI-спрайты <1.6с, transform/opacity-only (композит),
     снимают себя (onfinish/oncancel). prefers-reduced-motion — не
     запускаем вовсе (плюс CSS-гвард display:none в motion-w104.css). */
  window.NF_BURST = {
    /* petals({x, y, count, dist:[min,max], fall:[min,max], dur:[min,max]})
       x/y — точка в координатах вьюпорта (клиентские), остальное — px/мс.
       Дефолты — геометрия кассового бёрста (7 лепестков, ~54–128px). */
    petals: function (o) {
      if (reduced()) return;
      if (!document.body || typeof document.body.animate !== 'function') return;
      o = o || {};
      var x = isFinite(+o.x) ? +o.x : Math.round(window.innerWidth / 2);
      var y = isFinite(+o.y) ? +o.y : Math.round(window.innerHeight * 0.3);
      var n = Math.max(1, Math.min(24, +o.count || 7));
      var dist = o.dist || [54, 128];
      var fall = o.fall || [0, 0];
      var dur = o.dur || [620, 780];
      var COLORS = ['#ff4ea2', '#f4a9be', '#f5b301', '#ffd9ec', '#e03a8a'];
      for (var i = 0; i < n; i++) {
        (function (i) {
          var p = document.createElement('i');
          p.className = 'nf-burst-petal';
          p.style.left = x + 'px';
          p.style.top = y + 'px';
          p.style.background = COLORS[i % COLORS.length];
          document.body.appendChild(p);
          if (typeof p.animate !== 'function') { p.remove(); return; }
          var ang = (Math.PI * 2 * i) / n + (Math.random() - 0.5) * 0.7;
          var d = dist[0] + Math.random() * (dist[1] - dist[0]);
          var dx = Math.cos(ang) * d;
          /* дуга вверх на первой фазе — лепесток вспархивает */
          var dy0 = Math.sin(ang) * d * 0.55 - 24 - Math.random() * 30;
          /* вторая фаза: оседание вниз (fall) — «падают на страницу» */
          var dyF = dy0 + fall[0] + Math.random() * Math.max(0, fall[1] - fall[0]);
          var rot = (Math.random() < 0.5 ? -1 : 1) * (140 + Math.random() * 260);
          var t = dur[0] + Math.random() * Math.max(0, dur[1] - dur[0]);
          var a = p.animate(
            [
              { transform: 'translate(-50%,-50%) translate(0,0) rotate(0deg)', opacity: 1 },
              { transform: 'translate(-50%,-50%) translate(' + (dx * 0.62).toFixed(1) + 'px,' + (dy0 * 0.62 - 16).toFixed(1) + 'px) rotate(' + (rot * 0.55).toFixed(0) + 'deg)', opacity: 0.95, offset: 0.45 },
              { transform: 'translate(-50%,-50%) translate(' + dx.toFixed(1) + 'px,' + dyF.toFixed(1) + 'px) rotate(' + rot.toFixed(0) + 'deg)', opacity: 0 }
            ],
            { duration: t, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'forwards' }
          );
          a.onfinish = a.oncancel = function () { p.remove(); };
        })(i);
      }
    }
  };
})();
