/* Кнопка «+» / «Добавить в корзину» — добавляет товар в корзину по
   data-атрибутам кнопки data-order-cta.
   Fix (критик-покупатель, FRICTION): панель НЕ открываем автоматически —
   её backdrop перекрывал форму заказа. Счётчик на иконке корзины даёт
   достаточно обратной связи; панель открывается кликом по корзине. */
(function () {
  if (!window.cart) return;

  /* W98-fixF (F4): цель воронки Метрики — guarded (счётчик выключен или ещё
     не загружен после согласия cookie → тихий пропуск; ПД в параметры не шлём). */
  function nfGoal(name) {
    if (window.ym && window.__nfYmId) {
      try { ym(window.__nfYmId, 'reachGoal', name); } catch (e) { /* метрика не критична */ }
    }
  }

  /* W97-fixA (A8): пульс кнопки «+» гасится при prefers-reduced-motion.
     Проверяем matchMedia на каждом клике (динамично реагирует на смену
     системной настройки), плюс слушатель change отменяет уже летящую анимацию. */
  var reduceMQ = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  var pulse = null;
  if (reduceMQ && typeof reduceMQ.addEventListener === 'function') {
    reduceMQ.addEventListener('change', function (ev) {
      if (ev.matches && pulse) { pulse.cancel(); pulse = null; }
    });
  }

  /* ============ W104-δ (C2-D2 P1 «add-to-cart без отклика» + C2-M2
     «событийный лепесток»): burstAndFly = (а) мини-бёрст 7 лепестков из
     точки клика + (б) fly-to-cart — миниатюра/пилюля летит к иконке
     корзины, по прилёту — пульс бейджа. Дешёвая одна операция на клик:
     DOM-спрайты живут <1с и снимаются сами; всё на transform/opacity
     (композит). prefers-reduced-motion — не запускаем вовсе. Drawer НЕ
     открываем (осознанный FRICTION-выбор W103). Стили — motion-w104.css. */
  function burstAndFly(fromEl, clientX, clientY) {
    if (reduceMQ && reduceMQ.matches) return;
    if (!fromEl || fromEl.nodeType !== 1) return;
    if (!document.body || typeof fromEl.animate !== 'function') return;

    var cx = clientX, cy = clientY;
    /* клавиатурный Enter/Space даёт clientX/Y = 0 — берём центр кнопки */
    if (cx == null || cy == null || (cx === 0 && cy === 0)) {
      var r0 = fromEl.getBoundingClientRect();
      cx = r0.left + r0.width / 2;
      cy = r0.top + r0.height / 2;
    }

    /* (а) мини-бёрст: 7 лепестков, разлёт по дуге с вращением, 620–780мс */
    var COLORS = ['#ff4ea2', '#f4a9be', '#f5b301', '#ffd9ec', '#e03a8a'];
    var makePetal = function (i) {
      var p = document.createElement('i');
      p.className = 'nf-burst-petal';
      p.style.left = cx + 'px';
      p.style.top = cy + 'px';
      p.style.background = COLORS[i % COLORS.length];
      document.body.appendChild(p);
      if (typeof p.animate !== 'function') { p.remove(); return; }
      var ang = (Math.PI * 2 * i) / 7 + (Math.random() - 0.5) * 0.7;
      var dist = 54 + Math.random() * 74;
      var dx = Math.cos(ang) * dist;
      var dy = Math.sin(ang) * dist - 24 - Math.random() * 30; /* лёгкий подъём — дуга */
      var rot = (Math.random() < 0.5 ? -1 : 1) * (140 + Math.random() * 260);
      var dur = 620 + Math.random() * 160;
      var anim = p.animate(
        [
          { transform: 'translate(-50%,-50%) translate(0,0) rotate(0deg)', opacity: 1 },
          { transform: 'translate(-50%,-50%) translate(' + (dx * 0.62).toFixed(1) + 'px,' + (dy * 0.62 - 16).toFixed(1) + 'px) rotate(' + (rot * 0.55).toFixed(0) + 'deg)', opacity: 0.95, offset: 0.58 },
          { transform: 'translate(-50%,-50%) translate(' + dx.toFixed(1) + 'px,' + dy.toFixed(1) + 'px) rotate(' + rot.toFixed(0) + 'deg)', opacity: 0 }
        ],
        { duration: dur, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'forwards' }
      );
      /* IIFE-скоуп: каждый onfinish снимает СВОЙ спрайт (var-цикл без
         замыкания оставлял 6 из 7 лепестков в DOM навсегда) */
      (function (sprite, a) {
        a.onfinish = a.oncancel = function () { sprite.remove(); };
      })(p, anim);
    };
    for (var i = 0; i < 7; i++) makePetal(i);

    /* (б) fly-to-cart: миниатюра летит к иконке корзины (560мс),
       scale 1 → 0.3; по прилёту — пульс бейджа корзины */
    var cartBtn = document.getElementById('cartToggle');
    if (!cartBtn || typeof cartBtn.getBoundingClientRect !== 'function') return;
    var from = fromEl.getBoundingClientRect();
    var to = cartBtn.getBoundingClientRect();
    var chip = document.createElement('div');
    chip.className = 'nf-fly-chip';
    var imgSrc = fromEl.dataset ? fromEl.dataset.productImage : '';
    if (imgSrc) {
      var im = document.createElement('img');
      im.src = imgSrc;
      im.alt = '';
      chip.appendChild(im);
    } else {
      chip.textContent = '✓';
    }
    chip.style.left = (from.left + from.width / 2 - 22) + 'px';
    chip.style.top = (from.top + from.height / 2 - 22) + 'px';
    document.body.appendChild(chip);
    if (typeof chip.animate !== 'function') { chip.remove(); return; }
    var fx = (to.left + to.width / 2) - (from.left + from.width / 2);
    var fy = (to.top + to.height / 2) - (from.top + from.height / 2);
    var fly = chip.animate(
      [
        { transform: 'translate(0,0) scale(1)', opacity: 1 },
        { transform: 'translate(' + (fx * 0.5).toFixed(1) + 'px,' + (fy * 0.5 - 40).toFixed(1) + 'px) scale(0.72)', opacity: 1, offset: 0.55 },
        { transform: 'translate(' + fx.toFixed(1) + 'px,' + fy.toFixed(1) + 'px) scale(0.3)', opacity: 0.9 }
      ],
      { duration: 560, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'forwards' }
    );
    fly.onfinish = fly.oncancel = function () {
      chip.remove();
      var count = document.getElementById('cartCount');
      if (count && !count.hidden) {
        count.classList.remove('is-pulse');
        void count.offsetWidth; /* reflow — перезапуск keyframes */
        count.classList.add('is-pulse');
      }
    };
  }

  document.querySelectorAll('[data-order-cta]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      const { productId, productName, productPriceRaw, productImage } = btn.dataset;
      if (!productId) return;
      window.cart.add(productId, 1, {
        name: productName || '',
        price: Number(productPriceRaw) || 0,
        image: productImage || '',
      });
      /* W98-fixF (F4): успешное добавление → add_to_cart */
      nfGoal('add_to_cart');
      /* W104-δ: лепестковый бёрст из точки клика + полёт миниатюры
         к иконке корзины (скрипается при prefers-reduced-motion) */
      burstAndFly(btn, e.clientX, e.clientY);
      /* W98-fixF (F5): временное состояние кнопки «В корзине ✓» (~2.5с) — видно,
         что добавление сработало. Исходную разметку сохраняем ОДИН раз (dataset):
         повторный клик до возврата — просто продлеваем таймер, не ломаем состояние.
         Карточные CTA — круглые 44px «+»: им только «✓» (длинный текст сломает кнопку). */
      if (!('nfCtaOrig' in btn.dataset)) {
        btn.dataset.nfCtaOrig = btn.innerHTML;
        btn.dataset.nfCtaPlus = btn.textContent.trim() === '+' ? '1' : '';
        btn.dataset.nfCtaAria = btn.getAttribute('aria-label') || '';
      }
      btn.textContent = btn.dataset.nfCtaPlus === '1' ? '✓' : 'В корзине ✓';
      /* W100 (редактор): aria-label синхронен видимому состоянию — SR не говорит
         «Добавить в корзину», когда кнопка уже «В корзине ✓». */
      btn.setAttribute('aria-label', 'Добавлено в корзину');
      clearTimeout(btn._nfCtaReset);
      btn._nfCtaReset = setTimeout(function () {
        btn.innerHTML = btn.dataset.nfCtaOrig;
        if (btn.dataset.nfCtaAria !== '') { btn.setAttribute('aria-label', btn.dataset.nfCtaAria); }
        else { btn.removeAttribute('aria-label'); }
      }, 2500);
      /* Мягкая обратная связь без перекрытия: короткая анимация самой кнопки
         (skipped при prefers-reduced-motion — A8) */
      if (!reduceMQ || !reduceMQ.matches) {
        pulse = btn.animate(
          [
            { transform: 'scale(1)', background: '' },
            { transform: 'scale(1.18)', offset: 0.4 },
            { transform: 'scale(1)' },
          ],
          { duration: 320, easing: 'cubic-bezier(.22,1,.36,1)' }
        );
      }
      /* Awwwards-usability: SR-анонс добавления (drawer закрыт — qty-aria-live не виден) */
      let sr = document.getElementById('cartSrAnnounce');
      if (!sr) {
        sr = document.createElement('div');
        sr.id = 'cartSrAnnounce';
        sr.setAttribute('aria-live', 'polite');
        sr.setAttribute('role', 'status');
        sr.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap';
        document.body.appendChild(sr);
      }
      const items = window.cart.getItems ? window.cart.getItems() : [];
      let c = 0; items.forEach(function (it) { c += (it.qty || 1); });
      /* Awwwards-usability -0.2: «1 товар(ов)» → русское склонение (совпадает с itemsWord в cart-ui) */
      var w = c % 10 === 1 && c % 100 !== 11 ? 'товар' : (c % 10 >= 2 && c % 10 <= 4 && (c % 100 < 12 || c % 100 > 14) ? 'товара' : 'товаров');
      /* W98-fixF (F5): родо-независимое «Добавлено в корзину: {имя}» (было «добавлен») */
      sr.textContent = 'Добавлено в корзину: ' + (productName || 'Букет') + (c ? ', в корзине ' + c + ' ' + w : '');
      /* W67 (obvious-витрина NEW-6): на десктопе фидбек был только счётчик — sr-анонс невидим.
         Визуальный тост: pointer-events:none, над таббаром, под drawer; без открытия панели (FRICTION). */
      var tt = document.getElementById('cartToast');
      if (!tt) {
        tt = document.createElement('div');
        tt.id = 'cartToast';
        tt.setAttribute('role', 'status');
        document.body.appendChild(tt);
      }
      tt.textContent = (productName || 'Букет') + ' — в корзине' + (c ? ' (' + c + ' ' + w + ')' : '');
      tt.classList.add('is-visible');
      clearTimeout(tt._hideT);
      tt._hideT = setTimeout(function () { tt.classList.remove('is-visible'); }, 1800);
    });
  });
})();
