/* kinetic.js — smooth scroll (Lenis) + кинетическая типографика (W104-c, motion-агент).
   Vanilla ES2020, IIFE-паттерн сайта. Загружается на всех страницах (footer.php).

   1) LENIS SMOOTH SCROLL (js/vendor/lenis.min.js, self-host, MIT):
      lerp .12, wheelMultiplier 1, anchors:true (клики по якорям #… ведёт
      Lenis), allowNestedScroll (нативный скролл вложенных областей —
      карусели, корзина-drawer). НЕ активируется на pointer:coarse (тач)
      и prefers-reduced-motion — там остаётся нативный скролл.
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
        lerp: 0.12,
        wheelMultiplier: 1,
        autoRaf: true,          /* собственный rAF-цикл Lenis */
        anchors: true,          /* якоря #… — через lenis.scrollTo (плавно) */
        allowNestedScroll: true /* вложенные скролл-области (drawer, карусели) — нативно */
      });
    } catch (e) {
      return; /* непредвиденное — остаёмся на нативном скролле */
    }
    window.NF_LENIS = lenis;

    /* W104-β (C1-M P1): Lenis-десинк. onNativeScroll у Lenis синхронизирует
       animatedScroll только пока isScrolling !== 'smooth' — внешний скачок
       скролла (bfcache/scroll-restoration, программный window.scrollTo,
       нативный hash-прыжок) оставляет animatedScroll устаревшим, и
       следующий якорный клик телепортирует к старой позиции → откат →
       повторный пробег. Латаем своим scroll-слушателем: рассинхрон
       ≥120px между фактическим scrollY и animatedScroll — lenis.reset()
       (синх animatedScroll/targetScroll + остановка текущей анимации:
       продолжать её с чужой позиции и есть телепорт).
       ИСКЛЮЧЕНИЕ — якорные клики: нативный fragment-прыжок срабатывает
       в том же клике, что и lenis-анимация, и его scroll-событие приходит
       ДО первого кадра анимации (actual уже у цели, animated — на старте).
       Без грейса мы бы убили анимацию — якорь стал бы мгновенным телепортом.
       Грейс 150мс с capture-фазы click по same-page ссылке. */
    var anchorGraceUntil = 0;
    document.addEventListener('click', function (e) {
      var t = e.target;
      var a = t && t.closest ? t.closest('a[href]') : null;
      if (!a || !a.hash) return;
      var href = a.getAttribute('href') || '';
      var samePage = href.charAt(0) === '#'
        || (a.host === location.host && a.pathname === location.pathname);
      if (samePage) anchorGraceUntil = performance.now() + 150;
    }, { capture: true, passive: true });
    window.addEventListener('scroll', function () {
      if (lenis.isStopped) return;
      if (performance.now() < anchorGraceUntil) return; /* якорный клик — ведёт Lenis */
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
      || (el.textContent || '').replace(/\s+/g, ' ').trim();
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
})();
