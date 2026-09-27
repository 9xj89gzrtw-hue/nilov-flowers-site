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

  /* ============ W104 (C5-D5 P1): zoom/pan в лайтбоксе ============
     Клик по фото — toggle zoom 1.8 с origin в точке клика; в зуме —
     перетаскивание (grab/grabbing), выход из зума — клик или Esc.
     Тач: pinch не делаем (нативный жест страницы не конфликтует —
     изображение в контейнере overflow:hidden, пан — перетаскиванием).
     reduced-motion: без transition. Курсор — zoom-in / zoom-out / grab. */
  var lbImg = lightbox.querySelector('.lightbox__img');
  var lbFig = lightbox.querySelector('.lightbox__figure');
  var zoomed = false, panning = false, px = 0, py = 0, ox = 0, oy = 0, moved = 0;
  var reduced = window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function applyZoom() {
    lbImg.style.transformOrigin = ox + '% ' + oy + '%';
    lbImg.style.transform = zoomed ? 'scale(1.8)' : '';
    lbImg.style.cursor = zoomed ? (panning ? 'grabbing' : 'grab') : 'zoom-in';
    lbImg.style.transition = reduced ? 'none' : 'transform .35s cubic-bezier(.22,1,.36,1)';
  }

  if (lbImg) {
    lbImg.style.cursor = 'zoom-in';
    lbImg.addEventListener('click', function (e) {
      if (moved > 3) { moved = 0; return; } /* пан-жест не toggл-ит зум */
      var r = lbImg.getBoundingClientRect();
      ox = Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100));
      oy = Math.max(0, Math.min(100, ((e.clientY - r.top) / r.height) * 100));
      zoomed = !zoomed;
      if (!zoomed) { px = 0; py = 0; }
      applyZoom();
      e.stopPropagation();
    });
    lbImg.addEventListener('pointerdown', function (e) {
      if (!zoomed) return;
      panning = true; moved = 0; px = e.clientX; py = e.clientY;
      lbImg.setPointerCapture && lbImg.setPointerCapture(e.pointerId);
      applyZoom();
    });
    lbImg.addEventListener('pointermove', function (e) {
      if (!panning || !zoomed) return;
      var dx = e.clientX - px, dy = e.clientY - py;
      moved += Math.abs(dx) + Math.abs(dy);
      px = e.clientX; py = e.clientY;
      /* пан в процентах origin — инверсия направления (тянем фото за курсором) */
      ox = Math.max(0, Math.min(100, ox - (dx / lbImg.getBoundingClientRect().width) * 100 / 1.8));
      oy = Math.max(0, Math.min(100, oy - (dy / lbImg.getBoundingClientRect().height) * 100 / 1.8));
      lbImg.style.transition = 'none';
      applyZoom();
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
      lbImg.addEventListener(ev, function () {
        if (panning) { panning = false; applyZoom(); }
      });
    });
    /* закрытие лайтбокса сбрасывает зум */
    var origClose = close;
    close = function () { zoomed = false; panning = false; px = py = 0; applyZoom(); origClose(); };
  }
  /* курсор-подсказка на пустом месте вокруг фото — «клик закрывает» */
  if (lbFig) lbFig.addEventListener('click', function (e) {
    if (e.target === lbFig && !e.target.closest('[data-lightbox-close]')) close();
  });
})();
