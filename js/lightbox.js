/* Лайтбокс — увеличение фото товара по тапу/клику. Тот же паттерн
   открытия/закрытия, что и у панели корзины (cart-ui.js): backdrop-клик,
   Escape, блокировка скролла body (переиспользует .no-scroll), возврат
   фокуса на элемент, с которого лайтбокс был открыт.
   W104-δ (C2-M2 P1.2 «лайтбокс без анимации»): открытие — backdrop
   fade .24s + figure scale(.96)→1 .32s системной кривой (@starting-style
   + allow-discrete — motion-w104.css), закрытие — обратный ход .18s
   ease-in; hidden ставится CSS-переходом display, картинка снимается и
   фокус возвращается ПОСЛЕ анимации (260мс-страховка). Браузеры без
   allow-discrete: открытие — keyframes, закрытие мгновенное (как было).
   prefers-reduced-motion — всё мгновенно. */
(function () {
  const triggers = document.querySelectorAll('[data-lightbox-trigger]');
  if (!triggers.length) return;
  const lightbox = document.createElement('div');
  lightbox.className = 'lightbox';
  lightbox.hidden = true;
  lightbox.setAttribute('role', 'dialog');
  lightbox.setAttribute('aria-modal', 'true');
  lightbox.setAttribute('aria-label', 'Просмотр фото');
  /* <img> без src в разметку не кладём заранее — создаём при открытии и
     удаляем при закрытии. Причина: постоянный пустой <img> (0x0, скрыт
     через .lightbox[hidden]) ложно триггерил проверку "фото заполняет
     контейнер" в scripts/check-visual.js — тот сканирует все <img> на
     странице без учёта намеренно пустых шаблонных элементов (Task 11). */
  lightbox.innerHTML = `
    <div class="lightbox__backdrop" data-lightbox-close></div>
    <figure class="lightbox__figure">
      <button type="button" class="lightbox__close" data-lightbox-close aria-label="Закрыть">&times;</button>
    </figure>`;
  document.body.appendChild(lightbox);

  const figure = lightbox.querySelector('.lightbox__figure');
  let img = null;
  let lastTrigger = null;
  let closing = false;
  let closeTimer = 0;

  function open(trigger) {
    const src = trigger.dataset.lightboxSrc;
    if (!src) return;
    /* W104-δ: переоткрытие во время закрытия — переиспользуем живой <img>
     (finishClose не успел снять — он гардится снятым hidden) */
    closing = false;
    clearTimeout(closeTimer);
    if (img && img.isConnected) {
      img.src = src;
      img.alt = trigger.dataset.lightboxAlt || '';
    } else {
      img = document.createElement('img');
      img.className = 'lightbox__img';
      img.src = src;
      img.alt = trigger.dataset.lightboxAlt || '';
      /* W105-8fix1(г): нативный drag картинки не перехватывает указатель —
         пан в зуме живой (dragstart гасим и делегированно, см. ниже) */
      img.draggable = false;
      figure.insertBefore(img, figure.firstChild);
    }
    lastTrigger = trigger;
    lightbox.hidden = false;
    void lightbox.offsetWidth; /* коммит @starting-style (scale .96) до первого кадра */
    document.body.classList.add('no-scroll');
    lightbox.querySelector('.lightbox__close').focus();
  }

  function finishClose() {
    closing = false;
    clearTimeout(closeTimer);
    if (!lightbox.hidden) return; /* успели переоткрыть — картинка ещё нужна */
    if (img) {
      img.remove();
      img = null;
    }
    if (lastTrigger) {
      lastTrigger.focus();
      lastTrigger = null;
    }
  }

  function close() {
    if (lightbox.hidden && !closing) return;
    closing = true;
    clearTimeout(closeTimer);
    document.body.classList.remove('no-scroll');
    lightbox.hidden = true; /* allow-discrete: display:none после .18s выхода */
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      finishClose();
      return;
    }
    /* анимация выхода идёт — снимаем img/возвращаем фокус по её завершении
     (страховка 260мс > .24s — transitionend не ловим: у [hidden]-правил
     два свойства на двух элементах, таймер проще и надёжнее) */
    closeTimer = setTimeout(finishClose, 260);
  }

  triggers.forEach(function (trigger) {
    /* W97-fixA (A4): триггеры — div'ы (недостижимы с клавиатуры). Делаем их
       фокусируемыми «кнопками»: tabindex=0 + role=button + говорящий aria-label
       (по data-lightbox-alt, если своего нет). */
    trigger.setAttribute('tabindex', '0');
    trigger.setAttribute('role', 'button');
    if (!trigger.getAttribute('aria-label')) {
      trigger.setAttribute('aria-label', trigger.dataset.lightboxAlt
        ? 'Увеличить фото: ' + trigger.dataset.lightboxAlt
        : 'Увеличить фото');
    }
    trigger.addEventListener('click', function () {
      open(trigger);
    });
  });

  /* W97-fixA (A4): делегированный keydown по документу — Enter/Space на
     сфокусированном [data-lightbox-trigger] открывает лайтбокс.
     Оба гасим preventDefault: Space — чтобы не прокручивать страницу, Enter —
     чтобы браузер НЕ дошлёт синтетический click уже новой цели фокуса:
     open() переводит фокус на .lightbox__close, и без preventDefault «клик»
     Enter прилетает кнопке «Закрыть» — лайтбокс захлопывается в тот же такт
     (клавиатурный Enter «ничего не делал»; поймано при прогоне agent-browser). */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
    const active = document.activeElement;
    const t = active && active.closest ? active.closest('[data-lightbox-trigger]') : null;
    if (!t || !lightbox.hidden) return;
    e.preventDefault();
    open(t);
  });

  lightbox.addEventListener('click', function (e) {
    if (e.target.closest('[data-lightbox-close]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !lightbox.hidden) close();
  });

  /* ============ W104-fix6 (C6-M6 P0 + C6-D6 P1): zoom/pan + навигация ============
     П0-урок волны 5: обработчики, привязанные к .lightbox__img при инициализации,
     мертвы — img создаётся лениво в open(). Теперь ВСЁ через делегирование на
     .lightbox__figure (живёт с инициализации): клик по фото — toggle zoom 1.8
     с origin в точке клика; в зуме — drag-пан (grab/grabbing); пан-жест (>3px)
     не toggл-ит зум. Стрелки ←/→ и счётчик «N из M» — навигация по триггерам
     страницы (стрелки клавиатуры тоже). reduced-motion — без transition.
     W105-8fix1 (критики 8-b/8-c, волна 3, P1):
     (а) индекс — лайтбокс открывается НА КЛИКНУТОМ слайде: data-index на
         триггерах ставит js/product-gallery.js, navStep/updateNav ходят по
         curIdx (раньше индекс искался матчем src — дубликаты src в PDP-галерее
         всегда давали 0, «крупный план» открывался первым кадром);
     (б) навигация видна, когда ВИДОВ > 1 (list.length), а не «уникальных
         src > 1» — гейт W104-fix7 прятал стрелки на PDP (2 вида одного фото:
         «Общий вид» + «Крупный план») и второй кадр был недостижим на 390px;
     (в) «крупный план» (data-lightbox-zoom) продолжает рассказ слайда:
         лайтбокс стартует в зуме 1.8 с базой линзы галереи (50% 36%);
     (г) пан — РЕАЛЬНЫЙ translate поверх scale: раньше зум двигали подменой
         transform-origin (style.transform не менялся вовсе — мошн-критик
         сэмплировал 3 раза, 0 движения), а нативный drag <img draggable=true>
         съедал pointer-поток. Теперь: translate(tx,ty) scale(1.8) с клампом
         в границы кадра, draggable=false + preventDefault на dragstart,
         touch-action:none на img в зуме, move/up слушатели на window. */
  var ZOOM = 1.8;
  var zState = { zoomed: false, panning: false, px: 0, py: 0, ox: 50, oy: 50, tx: 0, ty: 0, moved: 0 };
  var reduced = window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var curIdx = 0;

  /* Навигация: стрелки + счётчик (только если триггеров > 1) */
  var navWrap = document.createElement('div');
  navWrap.className = 'lightbox__nav';
  navWrap.innerHTML = '<button type="button" class="lightbox__arrow lightbox__arrow--prev" aria-label="Предыдущее фото">&#8592;</button>'
    + '<span class="lightbox__counter" aria-live="polite"></span>'
    + '<button type="button" class="lightbox__arrow lightbox__arrow--next" aria-label="Следующее фото">&#8594;</button>';
  figure.appendChild(navWrap);

  function lbList() {
    /* W104-fix6b→W105-8fix1: список триггеров с src (NodeList — без .map,
    TypeError глухил updateNav; поймано живым прогоном). Индекс — ПОЗИЦИЯ
    вида, дубликаты src не схлопываются: «Общий вид» и «крупный план» —
    два РАЗНЫХ вида одного снимка. */
    return Array.prototype.slice.call(triggers)
      .filter(function (t) { return t.dataset.lightboxSrc; });
  }
  function lbIndexFor(trigger) {
    var list = lbList();
    /* data-index ставит js/product-gallery.js (порядок слайда в галерее);
     нет атрибута / вне диапазона — позиция в списке триггеров */
    var di = parseInt(trigger.getAttribute('data-index'), 10);
    if (isFinite(di) && di >= 0 && di < list.length) return di;
    var i = list.indexOf(trigger);
    return i >= 0 ? i : 0;
  }
  function updateNav() {
    var list = lbList();
    if (curIdx < 0 || curIdx >= list.length) curIdx = 0;
    /* W105-8fix1(б): гейт уникальности src снят — показываем навигацию при
    >1 ВИДЕ; единственный триггер (галерея выключена) — по-прежнему без стрелок */
    navWrap.style.display = list.length > 1 ? '' : 'none';
    navWrap.querySelector('.lightbox__counter').textContent = (curIdx + 1) + ' из ' + list.length;
  }
  function navStep(dir) {
    var list = lbList();
    if (list.length < 2 || !img) return;
    curIdx = (curIdx + dir + list.length) % list.length;
    var next = list[curIdx];
    endPan();
    img.src = next.dataset.lightboxSrc;
    img.alt = next.dataset.lightboxAlt || '';
    applyZoomOf(next);
    updateNav();
  }
  navWrap.querySelector('.lightbox__arrow--prev').addEventListener('click', function (e) { e.stopPropagation(); navStep(-1); });
  navWrap.querySelector('.lightbox__arrow--next').addEventListener('click', function (e) { e.stopPropagation(); navStep(1); });

  function zApply(skipTransition) {
    if (!img) return;
    if (zState.zoomed) {
      /* W105-8fix1(г): transform = translate ПОТОМ scale — translate в px
         экранных координат (порядок функций: scale применяется первым,
         сдвиг — уже в готовых пикселях). Пан = 1:1 за пальцем/курсором. */
      img.style.transformOrigin = zState.ox + '% ' + zState.oy + '%';
      img.style.transform = 'translate(' + Math.round(zState.tx) + 'px,' + Math.round(zState.ty) + 'px) scale(' + ZOOM + ')';
      /* тач: пока зум — жесты браузера не мешают пану; без зума снимаем,
         pinch-zoom страницы остаётся доступным */
      img.style.touchAction = 'none';
    } else {
      img.style.transform = '';
      img.style.touchAction = '';
    }
    img.style.cursor = zState.zoomed ? (zState.panning ? 'grabbing' : 'grab') : 'zoom-in';
    /* W104-fix7 (C7-D7 P0): пан НЕ должен пере-включать transition каждый кадр —
       иначе transform-origin анимируется с задержкой .35s и drag выглядит мёртвым */
    img.style.transition = (reduced || skipTransition) ? 'none' : 'transform .35s cubic-bezier(.22,1,.36,1)';
  }
  /* Кламп пана: зум-вид не должен отрываться от рамки — края картинки
     держатся за границей figure. tx ∈ [(ZOOM−1)·(ox−w), (ZOOM−1)·ox] (px). */
  function zBounds() {
    if (!img) return;
    var w = img.offsetWidth || 0, h = img.offsetHeight || 0;
    if (!w || !h) { zState.tx = 0; zState.ty = 0; return; }
    var s = ZOOM - 1;
    var ox = (zState.ox / 100) * w, oy = (zState.oy / 100) * h;
    zState.tx = Math.max(s * (ox - w), Math.min(s * ox, zState.tx));
    zState.ty = Math.max(s * (oy - h), Math.min(s * oy, zState.ty));
  }
  function zReset() {
    zState.zoomed = false; zState.panning = false; zState.moved = 0;
    zState.ox = 50; zState.oy = 50; zState.tx = 0; zState.ty = 0;
    zApply();
  }
  /* Вид триггера задаёт стартовое состояние: «крупный план»
     (data-lightbox-zoom, ставит product-gallery.js) — вход в зуме 1.8
     с базой линзы галереи 50% 36%; обычный кадр — целиком, без зума. */
  function applyZoomOf(trigger) {
    zState.panning = false; zState.moved = 0; zState.tx = 0; zState.ty = 0;
    if (trigger && trigger.getAttribute('data-lightbox-zoom') === '1' && img) {
      zState.zoomed = true; zState.ox = 50; zState.oy = 36;
    } else {
      zState.zoomed = false; zState.ox = 50; zState.oy = 50;
    }
    zApply();
  }

  /* Делегирование на figure: клики */
  figure.addEventListener('click', function (e) {
    if (e.target.closest('[data-lightbox-close]')) { close(); return; }
    if (e.target.closest('.lightbox__arrow')) return;
    if (e.target === img && img) {
      if (zState.moved > 3) { zState.moved = 0; return; } /* пан-жест не toggл-ит */
      var r = img.getBoundingClientRect();
      zState.ox = Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100));
      zState.oy = Math.max(0, Math.min(100, ((e.clientY - r.top) / r.height) * 100));
      zState.zoomed = !zState.zoomed;
      zState.tx = 0; zState.ty = 0;
      zApply();
      e.stopPropagation();
      return;
    }
    if (e.target === figure) close(); /* пустое место вокруг фото — закрыть */
  });
  /* Делегирование: пан (pointer events на img через figure).
     W105-8fix1(г): move/up — на window: без pointer-capture ретаргетит
     click (тап по зуму закрывал бы лайтбокс вместо отзума), а локальные
     слушатели теряли жест за пределами figure. */
  function onPanMove(e) {
    if (!zState.panning || !img) return;
    var dx = e.clientX - zState.px, dy = e.clientY - zState.py;
    zState.px = e.clientX; zState.py = e.clientY;
    if (!dx && !dy) return;
    zState.moved += Math.abs(dx) + Math.abs(dy);
    zState.tx += dx; zState.ty += dy;
    zBounds();
    zApply(true); /* пан — без transition */
  }
  function endPan() {
    if (!zState.panning) return;
    zState.panning = false;
    window.removeEventListener('pointermove', onPanMove);
    window.removeEventListener('pointerup', endPan);
    window.removeEventListener('pointercancel', endPan);
    zApply();
  }
  figure.addEventListener('pointerdown', function (e) {
    if (e.target !== img || !img || !zState.zoomed) return;
    zState.panning = true; zState.moved = 0;
    zState.px = e.clientX; zState.py = e.clientY;
    /* native drag <img> не стартует (гасит и mousedown-дефолт); дубль —
       dragstart-preventDefault ниже */
    e.preventDefault();
    window.addEventListener('pointermove', onPanMove);
    window.addEventListener('pointerup', endPan);
    window.addEventListener('pointercancel', endPan);
    zApply();
  });
  /* Страховка: нативный drag картинки (дефолт draggable=true у <img>)
     перехватывал указатель — пан «висел» без движения (мошн-критик 8-c) */
  lightbox.addEventListener('dragstart', function (e) { e.preventDefault(); });
  /* Клавиатура: стрелки — навигация (когда лайтбокс открыт) */
  document.addEventListener('keydown', function (e) {
    if (lightbox.hidden) return;
    if (e.key === 'ArrowLeft') { e.preventDefault(); navStep(-1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); navStep(1); }
  });
  /* Открытие: индекс кликнутого вида + зум-состояние вида + счётчик;
     закрытие — сброс (и пан-слушатели долой) */
  var origOpen = open;
  open = function (t) { origOpen(t); curIdx = lbIndexFor(t); applyZoomOf(t); updateNav(); };
  var origClose2 = close;
  close = function () { endPan(); zReset(); origClose2(); };
})();
