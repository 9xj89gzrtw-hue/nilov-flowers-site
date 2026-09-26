/* petals.js — «Лепестки»: СИГНАТУРНЫЙ canvas-эффект hero (W104-c, motion-агент).
   Vanilla Canvas 2D, ноль зависимостей, strict ES2020. Canvas авто-инъекцией
   prepend'ится в .fc-hero__main (фолбэк .fc-hero): absolute; inset:0; z-index:1 —
   между фото/скримом (z:auto) и .fc-hero__content (z:2) — текст читаем.
   Лепестки (16–34, адаптивно): bezier-форма розы/пиона с асимметрией, 8–22px,
   tumble + sin-качание, падение 12–40px/s; палитра бренда с альфой .55–.85
   и вертикальным градиентом. Ветер — sin/cos-noise поля; курсор — порыв
   (радиус 140px, repulse+swirl, затухает). Перф: DPR cap 2, один rAF, пауза
   вне вьюпорта (IO) и в hidden-табе; старт после window load + html.js-ready.
   prefers-reduced-motion — один статичный кадр (13 лепестков).
   Отключение: класс no-petals на <html> (в т.ч. на лету) или
   window.NF_PETALS.disable()/.enable() — для будущей настройки админки. */
(function () {
  'use strict';

  const ROOT = document.documentElement;
  const REDUCED = !!(window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const RND = (a, b) => a + Math.random() * (b - a);

  /* [тело, кончик-хайлайт] — оттенки бренда: розовые доминируют,
     янтарный — редкий акцент (не конфетти) */
  const PALETTE = [
    ['#ff4ea2', '#ffd9ec'],
    ['#ff4ea2', '#ffc4dd'],
    ['#e03a8a', '#ffc9e5'],
    ['#e03a8a', '#ffb0d9'],
    ['#ffd9ec', '#fff6fb'],
    ['#f5b301', '#ffe9bb']
  ];

  const GUST_R = 140;          /* радиус порыва от курсора, px */
  const GUST_R2 = GUST_R * GUST_R;
  const GUST_VMAX = 160;       /* потолок скорости порыва, px/s */
  const BLOOM_FADE = 0.55;     /* W104-β: рамп альфы одного лепестка, с */

  let host = null;
  let canvas = null;
  let ctx = null;
  let W = 0;
  let H = 0;
  let DPR = 1;
  let petals = [];
  let rafId = 0;
  let running = false;
  let started = false;
  let inView = true;
  let tornDown = true;
  let time = 0;
  let lastTs = 0;
  let resizeTimer = 0;
  const cursor = { x: 0, y: 0, vx: 0, vy: 0, t: 0, energy: 0, seen: false };

  const isOff = () => ROOT.classList.contains('no-petals');

  /* Сколько лепестков: 24–36 на десктопе, меньше на мобиле/слабом железе */
  function petalCount() {
    const mobile = window.innerWidth < 900;
    let n = mobile ? 24 : 34;
    if ((navigator.hardwareConcurrency || 8) <= 4) n -= 6;
    if ((window.devicePixelRatio || 1) < 1.5) n -= 4;
    return Math.max(mobile ? 16 : 24, Math.min(36, n));
  }

  function makePetal(spread) {
    /* W104-β (C1-M «как усилить»): глубина сцены d∈[0,1] связывает размер/
    альфу/скорость/ветер/амплитуду — дальние лепестки мелкие, полупрозрачные
    и медленные, ближние — крупные, плотные и быстрые. Поле получает объём
    вместо набора одинаковых случайных величин. */
    const d = Math.random();          /* 0 — далеко, 1 — близко */
    const s = 8 + 14 * d;             /* 8–22px */
    const p = {
      s,
      depth: d,
      w: s * RND(0.72, 0.98),         /* полуширина (в самой широкой части ≈0.8w) */
      a1: RND(-2.5, 2.5), a2: RND(-2.5, 2.5),
      a3: RND(-2.5, 2.5), a4: RND(-2.5, 2.5),  /* асимметрия безье */
      baseX: RND(-50, W + 50),
      y: spread ? RND(-30, H + 10) : RND(-160, -16),
      vy: 12 + 28 * d,                /* 12–40px/s — глубина = скорость */
      windK: 0.45 + 1.15 * d,         /* личный коэффициент ветра (= глубина) */
      amp: 6 + 18 * d,                /* амплитуда sin-качания */
      fr: RND(0.35, 0.95),            /* частота качания */
      ph: RND(0, Math.PI * 2),
      rot: RND(0, Math.PI * 2),
      tum: RND(-1.15, 1.15) * (0.55 + 0.65 * d), /* tumble, рад/с */
      gvx: 0, gvy: 0,                  /* скорость порыва (затухает) */
      alpha: 0.55 + 0.3 * d,           /* .55–.85 — глубина = плотность */
      col: PALETTE[(Math.random() * PALETTE.length) | 0],
      grad: null,
      bloom: -1,                       /* W104-β: <0 — уже проявлен (статика/реcайкл) */
      x: 0
    };
    p.x = p.baseX;
    return p;
  }

  function respawn(p) {
    p.y = RND(-150, -14);
    p.baseX = RND(-50, W + 50);
    p.x = p.baseX;
    p.gvx = 0;
    p.gvy = 0;
    p.rot = RND(0, Math.PI * 2);
    p.bloom = -1; /* реcайкл после старта — без повторного рампа */
  }

  /* Форма лепестка розы/пиона: узкое основание → широкие бока →
     мягко скруглённый верх; контрольные точки у каждого свои (асимметрия
     живого лепестка). Безье трижды: левый бок, арка верхушки, правый бок. */
  function tracePath(p) {
    const w = p.w;
    const h = p.s;
    ctx.beginPath();
    ctx.moveTo(0, h);                                        /* основание */
    ctx.bezierCurveTo(
      -w * 0.55 + p.a1, h * 0.62,
      -w * 1.12 + p.a2, h * 0.05,
      -w * 0.6 + p.a3 * 0.3, -h * 0.86);                     /* левое плечо */
    ctx.bezierCurveTo(
      -w * 0.24 + p.a3 * 0.4, -h * 1.04,
      w * 0.24 + p.a4 * 0.4, -h * 1.04,
      w * 0.6 - p.a3 * 0.3, -h * 0.86);                      /* арка верхушки */
    ctx.bezierCurveTo(
      w * 1.12 - p.a2, h * 0.05,
      w * 0.55 - p.a1, h * 0.62,
      0, h);                                                 /* правый бок */
    ctx.closePath();
  }

  function drawPetal(p) {
    /* W104-β (bloom вместо pop-in): каскадный спавн — до своего момента
    лепесток не рисуется, дальше 0.55с рамп альфы (0→полная) и мягкий
    рост масштаба 0.72→1: поле «распускается» ~1.4с, а не включается
    разом после window.load. */
    const bk = p.bloom < 0 ? 1
      : Math.max(0, Math.min(1, (time - p.bloom) / BLOOM_FADE));
    if (bk <= 0) return;               /* ещё не родился */
    if (!p.grad) {
      /* вертикальный градиент в локальных координатах — кэшируется
         (градиент пользователя живёт в текущем трансформе):
         насыщенное основание → светлый кончик */
      const g = ctx.createLinearGradient(0, p.s, 0, -p.s * 1.04);
      g.addColorStop(0, p.col[0]);
      g.addColorStop(0.62, p.col[0]);
      g.addColorStop(1, p.col[1]);
      p.grad = g;
    }
    const c = Math.cos(p.rot);
    const sn = Math.sin(p.rot);
    const gs = 0.72 + 0.28 * bk;       /* bloom: вырастает из 0.72 */
    ctx.setTransform(DPR * c * gs, DPR * sn * gs, -DPR * sn * gs, DPR * c * gs, p.x * DPR, p.y * DPR);
    ctx.globalAlpha = p.alpha * bk;
    ctx.fillStyle = p.grad;
    tracePath(p);
    ctx.fill();
  }

  function drawAll() {
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    ctx.clearRect(0, 0, W, H);
    for (let i = 0; i < petals.length; i++) drawPetal(petals[i]);
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.globalAlpha = 1;
  }

  /* Статичный кадр (prefers-reduced-motion): 13 лепестков по всему полю */
  function drawStatic() {
    const n = 13;
    petals = [];
    for (let i = 0; i < n; i++) petals.push(makePetal(true));
    for (let i = 0; i < n; i++) { petals[i].y = RND(H * 0.06, H * 0.92); }
    drawAll();
  }

  /* Глобальное поле ветра: композиция медленных sin/cos (плавный noise) */
  function windAt(t) {
    return Math.sin(t * 0.32) * 0.55
      + Math.sin(t * 0.11 + 1.7) * 0.3
      + Math.cos(t * 0.23 + 4.2) * 0.15;
  }

  function frame(ts) {
    if (!running) return;
    rafId = requestAnimationFrame(frame);
    if (!lastTs) lastTs = ts;
    let dt = (ts - lastTs) / 1000;
    lastTs = ts;
    if (dt > 0.05) dt = 0.05;            /* таб-свитч не даёт скачка */
    time += dt;

    /* энергия курсора и его скорость затухают */
    cursor.energy *= Math.exp(-1.8 * dt);
    if (cursor.energy < 0.004) cursor.energy = 0;
    const cvd = Math.exp(-6 * dt);
    cursor.vx *= cvd;
    cursor.vy *= cvd;

    /* координаты курсора в системе canvas (rect нужен только при порыве) */
    let cx = 0;
    let cy = 0;
    const gust = cursor.energy > 0.01 && cursor.seen;
    if (gust) {
      const r = host.getBoundingClientRect();
      cx = cursor.x - r.left;
      cy = cursor.y - r.top;
    }

    const wind = windAt(time);
    const damp = Math.exp(-2.4 * dt);

    for (let i = 0; i < petals.length; i++) {
      const p = petals[i];

      /* порыв: repulse от курсора + swirl + толчок по движению курсора */
      if (gust) {
        const dx = p.x - cx;
        const dy = p.y - cy;
        const d2 = dx * dx + dy * dy;
        if (d2 < GUST_R2) {
          const d = Math.sqrt(d2) || 1;
          const f = 1 - d / GUST_R;
          const k = f * f * cursor.energy;
          const ux = dx / d;
          const uy = dy / d;
          p.gvx += (ux * 46 - uy * 34) * k + cursor.vx * f * 0.5;
          p.gvy += (uy * 30 + ux * 34) * k * 0.7 + cursor.vy * f * 0.5;
          if (p.gvx > GUST_VMAX) p.gvx = GUST_VMAX;
          else if (p.gvx < -GUST_VMAX) p.gvx = -GUST_VMAX;
          if (p.gvy > GUST_VMAX) p.gvy = GUST_VMAX;
          else if (p.gvy < -GUST_VMAX) p.gvy = -GUST_VMAX;
        }
      }

      /* интеграция: ветер + порыв + собственное падение.
         W104-β (C1-M «флаттер»): вертикальная скорость модулируется фазой
         качания — на экстремумах дуги (|sway|→1) лепесток «зависает»
         (до −45% vy), в центре дуги падает полной скоростью. Живая
         биомеханика листа вместо равномерного лифта. */
      const sway = Math.sin(time * p.fr + p.ph);
      p.baseX += (wind * 16 * p.windK + p.gvx) * dt;
      p.y += (p.vy * (1 - 0.45 * sway * sway) + p.gvy) * dt;
      p.gvx *= damp;
      p.gvy *= damp;
      p.rot += (p.tum + p.gvx * 0.012) * dt;
      p.x = p.baseX + sway * p.amp;

      /* реcайкл: вышел за нижнюю/боковую/верхнюю границу — родился сверху */
      if (p.y > H + 30 || p.y < -320 || p.x < -90 || p.x > W + 90) respawn(p);

      drawPetal(p);
    }
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.globalAlpha = 1;
  }

  function syncRun() {
    const should = started && !REDUCED && !isOff() && inView && !document.hidden;
    if (should && !running) {
      running = true;
      lastTs = 0;
      rafId = requestAnimationFrame(frame);
    } else if (!should && running) {
      running = false;
      if (rafId) cancelAnimationFrame(rafId);
      rafId = 0;
    }
  }

  function resize() {
    if (!host || !canvas) return;
    W = Math.max(1, host.clientWidth);
    H = Math.max(1, host.clientHeight);
    DPR = Math.min(2, window.devicePixelRatio || 1);
    canvas.width = Math.round(W * DPR);
    canvas.height = Math.round(H * DPR);
    if (REDUCED) drawStatic();
  }

  function onResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      resize();
      if (!REDUCED) petals = petals.map(() => makePetal(true)); /* пересев поля */
    }, 150);
  }

  /* Курсор = порыв ветра: слушаем пассивно, считаем скорость/энергию */
  function onMove(e) {
    if (!running) return;
    const x = e.clientX;
    const y = e.clientY;
    const now = performance.now();
    if (cursor.seen) {
      const dt = Math.max(8, now - cursor.t);
      const nvx = (x - cursor.x) / dt * 16;
      const nvy = (y - cursor.y) / dt * 16;
      cursor.vx = Math.max(-42, Math.min(42, cursor.vx * 0.75 + nvx * 0.25));
      cursor.vy = Math.max(-42, Math.min(42, cursor.vy * 0.75 + nvy * 0.25));
      const sp = Math.hypot(x - cursor.x, y - cursor.y);
      cursor.energy = Math.min(1, cursor.energy + Math.min(0.45, sp * 0.012));
    }
    cursor.x = x;
    cursor.y = y;
    cursor.t = now;
    cursor.seen = true;
  }

  /* ---------- жизненный цикл ---------- */

  function build() {
    host = document.querySelector('.fc-hero__main') || document.querySelector('.fc-hero');
    if (!host) return false;
    canvas = document.createElement('canvas');
    canvas.className = 'fc-petals';
    canvas.setAttribute('aria-hidden', 'true');
    /* инлайн-страховка позиционирования (основные стили — css/motion-w104.css) */
    canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;'
      + 'pointer-events:none;z-index:1;display:block';
    host.insertBefore(canvas, host.firstChild);
    /* фолбэк-хост .fc-hero — статичный: даём canvas опорный контейнер */
    if (!host.classList.contains('fc-hero__main')
      && getComputedStyle(host).position === 'static') {
      host.style.position = 'relative';
    }
    ctx = canvas.getContext('2d');
    if (!ctx) { canvas.remove(); return false; }

    resize();
    if (!REDUCED) petals = Array.from({ length: petalCount() }, () => makePetal(true));

    if ('IntersectionObserver' in window) {
      new IntersectionObserver((entries) => {
        inView = entries[entries.length - 1].isIntersecting;
        syncRun();
      }, { threshold: 0 }).observe(host);
    }
    document.addEventListener('visibilitychange', syncRun);
    window.addEventListener('resize', onResize, { passive: true });
    window.addEventListener('mousemove', onMove, { passive: true });

    tornDown = false;
    return true;
  }

  function start() {
    if (started || REDUCED || isOff()) return;
    if (tornDown && !build()) return;
    started = true;
    scheduleBloom(); /* W104-β: волна проявления вместо pop-in */
    syncRun();
  }

  /* W104-β (C1-M): каскадный bloom — момент старта рампа у каждого свой
     (случайный порядок, ближние чуть раньше), вся волна ~0.9с + рамп
     0.55с ≈ 1.4с. Плюс лёгкий сдвиг вниз: «начинают падать». */
  function scheduleBloom() {
    for (let i = 0; i < petals.length; i++) {
      const p = petals[i];
      p.bloom = time + (1 - p.depth) * 0.18 + Math.random() * 0.72;
    }
  }

  function teardown() {
    started = false;
    running = false;
    if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
    if (canvas && canvas.parentNode) canvas.parentNode.removeChild(canvas);
    canvas = null;
    ctx = null;
    petals = [];
    tornDown = true;
  }

  /* ---------- запуск: js-ready + window load (LCP не трогаем) ---------- */
  function tryStart() {
    if (document.readyState === 'complete' && ROOT.classList.contains('js-ready')) {
      start();
      return true;
    }
    return false;
  }

  if (isOff()) {
    /* выключено сервером/админкой — только следим за включением */
  } else if (REDUCED) {
    /* reduced-motion: один статичный кадр сразу (без load-ожидания — не анимация) */
    if (!build()) return;
    drawStatic();
  } else if (!tryStart()) {
    window.addEventListener('load', function onLoad() {
      window.removeEventListener('load', onLoad);
      if (tryStart()) return;
      /* load прошёл, js-ready ещё нет: ждём класс от nilov.js,
         страховка 2.5с — лепестки от него не зависят */
      const mo = new MutationObserver(function () {
        if (tryStart()) mo.disconnect();
      });
      mo.observe(ROOT, { attributes: true, attributeFilter: ['class'] });
      setTimeout(function () { mo.disconnect(); start(); }, 2500);
    });
  }

  /* Тумблер no-petals на лету (будущая админка) */
  new MutationObserver(function () {
    if (isOff()) {
      if (!tornDown) teardown();
    } else if (tornDown) {
      if (REDUCED) { if (build()) drawStatic(); }
      else start();
    }
  }).observe(ROOT, { attributes: true, attributeFilter: ['class'] });

  /* Публичный API для админки/отладки */
  window.NF_PETALS = {
    get active() { return !tornDown; },
    disable() { ROOT.classList.add('no-petals'); },
    enable() { ROOT.classList.remove('no-petals'); },
    /* диагностика порыва/перфа (только чтение) */
    stats() {
      let g = 0;
      for (let i = 0; i < petals.length; i++) {
        const v = Math.abs(petals[i].gvx) + Math.abs(petals[i].gvy);
        if (v > g) g = v;
      }
      return {
        running,
        petals: petals.length,
        /* W104-β: сколько лепестков уже полностью проявились (bloom-волна) */
        bloomed: petals.reduce((n, p) => n + (p.bloom < 0 || time - p.bloom >= BLOOM_FADE ? 1 : 0), 0),
        cursorEnergy: Math.round(cursor.energy * 100) / 100,
        gustSpeedMax: Math.round(g)
      };
    }
  };
})();
