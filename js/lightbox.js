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
     страницы (стрелки клавиатуры тоже). reduced-motion — без transition. */
  var zState = { zoomed: false, panning: false, px: 0, py: 0, ox: 50, oy: 50, moved: 0 };
  var reduced = window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Навигация: стрелки + счётчик (только если триггеров > 1) */
  var navWrap = document.createElement('div');
  navWrap.className = 'lightbox__nav';
  navWrap.innerHTML = '<button type="button" class="lightbox__arrow lightbox__arrow--prev" aria-label="Предыдущее фото">&#8592;</button>'
    + '<span class="lightbox__counter" aria-live="polite"></span>'
    + '<button type="button" class="lightbox__arrow lightbox__arrow--next" aria-label="Следующее фото">&#8594;</button>';
  figure.appendChild(navWrap);

  function lbSources() {
    /* W104-fix6b: triggers — NodeList, у него нет .map (TypeError глухил
       updateNav — счётчик молча оставался пустым; поймано живым прогоном) */
    return Array.prototype.slice.call(triggers).map(function (t) { return { src: t.dataset.lightboxSrc, alt: t.dataset.lightboxAlt || '' }; })
      .filter(function (s) { return s.src; });
  }
  function updateNav() {
    var list = lbSources();
    var idx = list.findIndex(function (s) { return img && s.src === img.getAttribute('src'); });
    /* W104-fix7 (C7-M7 P0): у товара одно фото (главный + zoom-слайд дублируют src) —
       стрелки по одинаковым изображениям бессмысленны; показываем навигацию
       только когда УНИКАЛЬНЫХ src больше одного */
    var uniq = list.map(function (s) { return s.src; }).filter(function (v, i, a) { return a.indexOf(v) === i; });
    navWrap.style.display = uniq.length > 1 ? '' : 'none';
    navWrap.querySelector('.lightbox__counter').textContent = (idx + 1) + ' из ' + list.length;
  }
  function navStep(dir) {
    var list = lbSources();
    if (list.length < 2 || !img) return;
    var idx = list.findIndex(function (s) { return s.src === img.getAttribute('src'); });
    var next = list[(idx + dir + list.length) % list.length];
    zReset();
    img.src = next.src;
    img.alt = next.alt;
    updateNav();
  }
  navWrap.querySelector('.lightbox__arrow--prev').addEventListener('click', function (e) { e.stopPropagation(); navStep(-1); });
  navWrap.querySelector('.lightbox__arrow--next').addEventListener('click', function (e) { e.stopPropagation(); navStep(1); });

  function zApply(skipTransition) {
    if (!img) return;
    img.style.transformOrigin = zState.ox + '% ' + zState.oy + '%';
    img.style.transform = zState.zoomed ? 'scale(1.8)' : '';
    img.style.cursor = zState.zoomed ? (zState.panning ? 'grabbing' : 'grab') : 'zoom-in';
    /* W104-fix7 (C7-D7 P0): пан НЕ должен пере-включать transition каждый кадр —
       иначе transform-origin анимируется с задержкой .35s и drag выглядит мёртвым */
    img.style.transition = (reduced || skipTransition) ? 'none' : 'transform .35s cubic-bezier(.22,1,.36,1)';
  }
  function zReset() { zState.zoomed = false; zState.panning = false; zState.moved = 0; zApply(); }

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
      zApply();
      e.stopPropagation();
      return;
    }
    if (e.target === figure) close(); /* пустое место вокруг фото — закрыть */
  });
  /* Делегирование: пан (pointer events на img через figure) */
  figure.addEventListener('pointerdown', function (e) {
    if (e.target !== img || !img || !zState.zoomed) return;
    zState.panning = true; zState.moved = 0; zState.px = e.clientX; zState.py = e.clientY;
    zApply();
  });
  figure.addEventListener('pointermove', function (e) {
    if (!zState.panning || !img) return;
    var dx = e.clientX - zState.px, dy = e.clientY - zState.py;
    zState.moved += Math.abs(dx) + Math.abs(dy);
    zState.px = e.clientX; zState.py = e.clientY;
    var r = img.getBoundingClientRect();
    zState.ox = Math.max(0, Math.min(100, zState.ox - (dx / r.width) * 100 / 1.8));
    zState.oy = Math.max(0, Math.min(100, zState.oy - (dy / r.height) * 100 / 1.8));
    zApply(true); /* пан — без transition */
  });
  ['pointerup', 'pointercancel'].forEach(function (ev) {
    figure.addEventListener(ev, function () {
      if (zState.panning) { zState.panning = false; zApply(); }
    });
  });
  /* Клавиатура: стрелки — навигация (когда лайтбокс открыт) */
  document.addEventListener('keydown', function (e) {
    if (lightbox.hidden) return;
    if (e.key === 'ArrowLeft') { e.preventDefault(); navStep(-1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); navStep(1); }
  });
  /* Открытие: курсор + счётчик + сброс зума; закрытие — сброс */
  var origOpen = open;
  open = function (t) { origOpen(t); if (img) { img.style.cursor = 'zoom-in'; } zReset(); updateNav(); };
  var origClose2 = close;
  close = function () { zReset(); origClose2(); };
})();
