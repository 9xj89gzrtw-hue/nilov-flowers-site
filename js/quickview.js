/* S5 (ЭТАП 5): QUICK VIEW — модалка быстрого просмотра букета.
   Клик по фото карточки каталога (сетка главной + посадочные категории) →
   аккуратный слайдер (2 ракурса) + состав поштучно + бесплатная открытка +
   дополнения (Кризал 0 ₽, сладкие подарки из БД).
   ДАННЫЕ читаются из DOM карточки (без дублирования data-атрибутов):
   .product-card__name → название+href, __price → цена, __comp → состав,
   __size-text → размер, __split-text → сплит, __img/__img2 → фото,
   __badge--hit/--sale → плашки. Кнопки НЕ дублируют логику cart-cta.js /
   oneclick.js: «В корзину»/«В 1 клик» кликают живые кнопки ИСХОДНОЙ
   карточки (тост, лепестковый бёрст, SR-анонс, валидация — всё работает).
   Открытка/Кризал пишутся в тот же localStorage 'nf_cart_extras', что
   корзина-drawer (cart-ui.js) и форма заказа (order-form.js).
   Тумблер: настройка feature_quickview (0 — фото ведёт на страницу товара). */
(function () {
  'use strict';

  var cfg = window.NF_QUICKVIEW || {};
  if (cfg.enabled === 0 || cfg.enabled === '0') return;

  var modal = null;
  var sourceCard = null;
  var lastFocus = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  var ICON = {
    flower: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="2.4"/><path d="M12 6.6c0-2 1-3.6 2.4-3.6 1.2 0 1.8 1.2 1.4 2.6M12 6.6c0-2-1-3.6-2.4-3.6-1.2 0-1.8 1.2-1.4 2.6M14.2 10.9c1.9-.9 3.8-.4 4.4.8.5 1.1-.4 2.2-1.8 2.5M9.8 10.9c-1.9-.9-3.8-.4-4.4.8-.5 1.1.4 2.2 1.8 2.5M13.6 12.7c1.3 1.6 1.3 3.6.2 4.4-1 .7-2.2.1-2.8-1.2M10.4 12.7c-1.3 1.6-1.3 3.6-.2 4.4 1 .7 2.2.1 2.8-1.2"/><path d="M12 15.9V21"/></svg>',
    clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    updown: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/></svg>',
    dia: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M6.5 17.5 17.5 6.5"/></svg>',
    split: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="7.5" height="14" rx="1.5"/><rect x="13.5" y="5" width="7.5" height="14" rx="1.5"/></svg>',
    zap: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
    candy: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.5 8.5 3 6l3-3 2.5 2.5M18.5 8.5 21 6l-3-3-2.5 2.5M8.5 8.5h7a5.5 5.5 0 0 1 0 11h-7a5.5 5.5 0 0 1 0-11z"/></svg>'
  };

  function ensureModal() {
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'quickview';
    modal.id = 'quickviewModel';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="quickview__backdrop" data-qv-close></div>' +
      '<div class="quickview__dialog" role="dialog" aria-modal="true" aria-labelledby="qvTitle">' +
      '  <button type="button" class="quickview__close" data-qv-close aria-label="Закрыть">&times;</button>' +
      '  <div class="quickview__gallery">' +
      '    <div class="quickview__stage">' +
      '      <span class="quickview__badge quickview__badge--sale" hidden></span>' +
      '      <span class="quickview__badge quickview__badge--hit" hidden></span>' +
      '      <img class="quickview__main" alt="">' +
      '    </div>' +
      '    <div class="quickview__thumbs"></div>' +
      '  </div>' +
      '  <div class="quickview__info">' +
      '    <h3 class="quickview__title" id="qvTitle"></h3>' +
      '    <div class="quickview__price-row">' +
      '      <p class="quickview__price"></p>' +
      '      <span class="quickview__price--old" hidden></span>' +
      '    </div>' +
      '    <p class="quickview__split" hidden><span class="quickview__split-chip"></span><span class="quickview__split-text"></span></p>' +
      '    <p class="quickview__delivery" hidden>' + ICON.clock + '<span></span></p>' +
      '    <span class="quickview__size" hidden>' + ICON.updown + '<span></span></span>' +
      '    <div class="quickview__comp" hidden>' +
      '      <p class="quickview__comp-title"></p>' +
      '      <div class="quickview__comp-list"></div>' +
      '    </div>' +
      '    <div class="quickview__extras">' +
      '      <p class="quickview__extras-title"></p>' +
      '      <label class="quickview__extra"><input type="checkbox" id="qvChrysal"><span class="quickview__extra-name"></span><span class="quickview__extra-note">0&nbsp;₽</span></label>' +
      '      <div class="quickview__sweets-box"></div>' +
      '      <label class="quickview__extra" style="flex-wrap:wrap"><span class="quickview__extra-name quickview__card-label"></span><span class="quickview__extra-note">0&nbsp;₽</span></label>' +
      '      <textarea class="quickview__card-text" id="qvCardText" maxlength="300" rows="2"></textarea>' +
      '    </div>' +
      '    <div class="quickview__actions">' +
      '      <button type="button" class="quickview__tocart"></button>' +
      '      <button type="button" class="quickview__oneclick">' + ICON.zap + '<span></span></button>' +
      '    </div>' +
      '    <a class="quickview__full" href="#"></a>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(modal);

    modal.addEventListener('click', function (e) {
      if (e.target.closest('[data-qv-close]')) { close(); return; }
      if (e.target.closest('.quickview__thumb')) {
        var t = e.target.closest('.quickview__thumb');
        showImage(parseInt(t.getAttribute('data-qv-idx'), 10) || 0);
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) { close(); }
    });
    modal.querySelector('.quickview__tocart').addEventListener('click', addToCart);
    modal.querySelector('.quickview__oneclick').addEventListener('click', function () {
      saveExtras();
      var btn = sourceCard ? sourceCard.querySelector('[data-oneclick]') : null;
      close();
      if (btn) { btn.click(); }
    });
    return modal;
  }

  /* ---------- чтение данных карточки ---------- */
  function readCard(card) {
    var nameEl = card.querySelector('.product-card__name');
    var priceEl = card.querySelector('.product-card__price');
    var discEl = card.querySelector('.product-card__price--discount');
    var oldEl = card.querySelector('.product-card__price--old');
    var compEl = card.querySelector('.product-card__comp');
    var sizeEl = card.querySelector('.product-card__size-text');
    var splitEl = card.querySelector('.product-card__split-text');
    var deliveryEl = card.querySelector('.product-card__delivery');
    var imgEl = card.querySelector('.product-card__img');
    var img2El = card.querySelector('.product-card__img2');
    var hitEl = card.querySelector('.product-card__badge--hit');
    var saleEl = card.querySelector('.product-card__badge--sale');
    var sizeDia = card.querySelector('.product-card__size svg');
    var isDia = false;
    if (sizeDia) {
      /* у ⌀-иконки нет стрелок — различаем по path-атрибуту updown */
      var p = sizeDia.querySelector('path');
      isDia = !p || !/M12 3v18/.test(p.getAttribute('d') || '');
    }
    return {
      name: nameEl ? nameEl.textContent.trim() : 'Букет',
      href: nameEl ? nameEl.getAttribute('href') : '#catalog',
      price: discEl ? discEl.textContent.trim() : (priceEl ? priceEl.textContent.trim() : ''),
      old: oldEl ? oldEl.textContent.trim() : '',
      comp: compEl ? compEl.textContent.trim() : '',
      size: sizeEl ? sizeEl.textContent.trim() : '',
      sizeIsDia: isDia,
      split: splitEl ? splitEl.textContent.trim() : '',
      delivery: deliveryEl ? deliveryEl.textContent.trim() : '',
      img: imgEl ? (imgEl.currentSrc || imgEl.getAttribute('src') || '') : '',
      img2: img2El ? (img2El.getAttribute('src') || '') : '',
      hit: !!hitEl,
      sale: saleEl ? saleEl.textContent.trim() : ''
    };
  }

  /* ---------- слайдер ---------- */
  var images = [];
  function showImage(idx) {
    if (!images[idx]) return;
    var main = modal.querySelector('.quickview__main');
    main.style.opacity = '0';
    setTimeout(function () { main.src = images[idx]; main.style.opacity = '1'; }, 90);
    main.alt = sourceCard ? (sourceCard.querySelector('.product-card__name') || {}).textContent || '' : '';
    var thumbs = modal.querySelectorAll('.quickview__thumb');
    for (var i = 0; i < thumbs.length; i++) {
      thumbs[i].classList.toggle('is-active', i === idx);
    }
  }

  /* ---------- открытка/Кризал — общий стор с корзиной ---------- */
  function readExtras() {
    try {
      var s = JSON.parse(localStorage.getItem('nf_cart_extras') || '{}');
      return {
        postcard: !!s.postcard,
        postcardText: typeof s.postcardText === 'string' ? s.postcardText.slice(0, 500) : '',
        chrysal: !!s.chrysal
      };
    } catch (e) { return { postcard: false, postcardText: '', chrysal: false }; }
  }

  function saveExtras() {
    var chrysal = modal.querySelector('#qvChrysal');
    var cardText = modal.querySelector('#qvCardText');
    var text = (cardText ? cardText.value : '').slice(0, 500).trim();
    var extras = {
      postcard: text !== '',
      postcardText: text,
      chrysal: chrysal ? chrysal.checked : false
    };
    try { localStorage.setItem('nf_cart_extras', JSON.stringify(extras)); } catch (e) {}
    window.NF_CART_EXTRAS = extras;
    var formCard = document.getElementById('orderCardText');
    if (formCard && extras.postcard && formCard.value !== extras.postcardText) {
      formCard.value = extras.postcardText;
    }
    /* Синхронизация с живыми чекбоксами корзины-drawer (cart-ui.js): меняем
       состояние и стреляем change/input — обработчики cart-ui сами поднимут
       свой стор и перерисуют UI (единый владелец состояния — cart-ui). */
    var dPostcard = document.getElementById('cartExtraPostcardOn');
    if (dPostcard && dPostcard.checked !== extras.postcard) {
      dPostcard.checked = extras.postcard;
      dPostcard.dispatchEvent(new Event('change', { bubbles: true }));
    }
    var dChrysal = document.getElementById('cartExtraChrysalOn');
    if (dChrysal && dChrysal.checked !== extras.chrysal) {
      dChrysal.checked = extras.chrysal;
      dChrysal.dispatchEvent(new Event('change', { bubbles: true }));
    }
    var dPostcardText = document.getElementById('cartExtraPostcardText');
    if (dPostcardText && dPostcardText.value !== extras.postcardText) {
      dPostcardText.value = extras.postcardText;
      dPostcardText.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  /* ---------- «В корзину»: допы + клик живой CTA карточки ---------- */
  function addToCart() {
    saveExtras();
    var sweets = modal.querySelectorAll('.quickview__sweet-check');
    for (var i = 0; i < sweets.length; i++) {
      if (sweets[i].checked) {
        var d = sweets[i].dataset;
        if (window.cart && d.qvSweetId) {
          window.cart.add(d.qvSweetId, 1, {
            name: d.qvSweetName || '',
            price: Number(d.qvSweetPrice) || 0,
            image: d.qvSweetImage || ''
          });
        }
      }
    }
    var btn = sourceCard ? sourceCard.querySelector('[data-order-cta]') : null;
    if (btn) { btn.click(); }
    setTimeout(close, 420);
  }

  /* ---------- открытие ---------- */
  function open(card) {
    var m = ensureModal();
    sourceCard = card;
    lastFocus = document.activeElement;
    var d = readCard(card);

    m.querySelector('.quickview__title').textContent = d.name;
    m.querySelector('.quickview__full').textContent = cfg.fullLink || 'Полное описание букета';
    m.querySelector('.quickview__full').href = d.href;
    m.querySelector('.quickview__price').textContent = d.price;
    var oldEl = m.querySelector('.quickview__price--old');
    if (d.old) { oldEl.textContent = d.old; oldEl.hidden = false; } else { oldEl.hidden = true; }

    var hitEl = m.querySelector('.quickview__badge--hit');
    if (d.hit) { hitEl.textContent = cfg.hitText || 'Хит'; hitEl.hidden = false; } else { hitEl.hidden = true; }
    var saleEl = m.querySelector('.quickview__badge--sale');
    if (d.sale) { saleEl.textContent = d.sale; saleEl.hidden = false; } else { saleEl.hidden = true; }

    var splitEl = m.querySelector('.quickview__split');
    if (d.split) {
      splitEl.querySelector('.quickview__split-text').textContent = d.split;
      splitEl.querySelector('.quickview__split-chip').innerHTML = ICON.split + '<span>' + esc(cfg.splitChip || 'Сплит') + '</span>';
      splitEl.hidden = false;
    } else { splitEl.hidden = true; }

    var delEl = m.querySelector('.quickview__delivery');
    if (d.delivery) {
      delEl.querySelector('span').textContent = d.delivery;
      delEl.hidden = false;
    } else { delEl.hidden = true; }

    var sizeEl = m.querySelector('.quickview__size');
    if (d.size) {
      sizeEl.querySelector('svg').innerHTML = d.sizeIsDia ? ICON.dia.replace(/<\/?svg[^>]*>/g, '') : ICON.updown.replace(/<\/?svg[^>]*>/g, '');
      sizeEl.querySelector('span').textContent = d.size;
      sizeEl.hidden = false;
    } else { sizeEl.hidden = true; }

    /* состав — поштучно, по разделителю «·» */
    var compBox = m.querySelector('.quickview__comp');
    var compList = m.querySelector('.quickview__comp-list');
    compList.innerHTML = '';
    var parts = d.comp ? d.comp.split('·').map(function (s) { return s.trim(); }).filter(Boolean) : [];
    if (parts.length) {
      m.querySelector('.quickview__comp-title').textContent = cfg.compTitle || 'Состав';
      for (var i = 0; i < parts.length; i++) {
        var row = document.createElement('p');
        row.className = 'quickview__comp-item';
        row.innerHTML = ICON.flower + '<span>' + esc(parts[i]) + '</span>';
        compList.appendChild(row);
      }
      compBox.hidden = false;
    } else { compBox.hidden = true; }

    /* дополнения: названия + сладости из конфига (БД) */
    m.querySelector('.quickview__extras-title').textContent = cfg.extrasTitle || 'Дополнить букет';
    m.querySelector('.quickview__extra-name').textContent = cfg.chrysalText || 'Кризал — подкормка для свежести';
    m.querySelector('.quickview__card-label').textContent = cfg.cardLabel || 'Открытка в подарок — напишем от руки';
    m.querySelector('#qvCardText').placeholder = cfg.cardPlaceholder || 'Текст открытки';
    var sweetsBox = m.querySelector('.quickview__sweets-box');
    sweetsBox.innerHTML = '';
    (cfg.sweets || []).forEach(function (sw) {
      var lab = document.createElement('label');
      lab.className = 'quickview__extra';
      lab.innerHTML =
        '<input type="checkbox" class="quickview__sweet-check"' +
        ' data-qv-sweet-id="' + parseInt(sw.id, 10) + '"' +
        ' data-qv-sweet-name="' + esc(sw.name) + '"' +
        ' data-qv-sweet-price="' + (Number(sw.price) || 0) + '"' +
        ' data-qv-sweet-image="' + esc(sw.image || '') + '">' +
        '<span>' + ICON.candy + ' ' + esc(sw.name) + '</span>' +
        '<span class="quickview__extra-note">' + String(Math.round(Number(sw.price) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0') + '\u00A0₽</span>';
      sweetsBox.appendChild(lab);
    });

    m.querySelector('.quickview__tocart').textContent = cfg.tocart || 'В корзину';
    m.querySelector('.quickview__oneclick span').textContent = cfg.oneclick || 'В 1 клик';

    /* слайдер: основной ракурс + второй, если есть */
    images = [];
    if (d.img) images.push(d.img);
    if (d.img2) images.push(d.img2);
    var thumbs = m.querySelector('.quickview__thumbs');
    thumbs.innerHTML = '';
    if (images.length > 1) {
      for (var k = 0; k < images.length; k++) {
        var tb = document.createElement('button');
        tb.type = 'button';
        tb.className = 'quickview__thumb' + (k === 0 ? ' is-active' : '');
        tb.setAttribute('data-qv-idx', String(k));
        tb.setAttribute('aria-label', 'Ракурс ' + (k + 1));
        tb.innerHTML = '<img src="' + esc(images[k]) + '" alt="" loading="lazy">';
        thumbs.appendChild(tb);
      }
      thumbs.hidden = false;
    } else {
      thumbs.hidden = true;
    }
    var mainImg = m.querySelector('.quickview__main');
    if (images.length) { mainImg.src = images[0]; }

    /* предзаполнение открытки/Кризала текущим состоянием корзины */
    var ex = readExtras();
    m.querySelector('#qvChrysal').checked = ex.chrysal;
    m.querySelector('#qvCardText').value = ex.postcardText;

    m.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    m.querySelector('.quickview__close').focus();
  }

  function close() {
    if (!modal) return;
    modal.hidden = true;
    document.documentElement.style.overflow = '';
    if (lastFocus && typeof lastFocus.focus === 'function') { lastFocus.focus(); }
    sourceCard = null;
  }

  /* ---------- делегирование: клик по фото карточки ---------- */
  document.addEventListener('click', function (e) {
    var link = e.target.closest ? e.target.closest('.product-card__media-link') : null;
    if (!link) return;
    var card = link.closest('.product-card');
    /* карточка карусели (без CTA-кнопки) и выключенный quickview — обычный переход */
    if (!card || !card.querySelector('[data-order-cta]')) return;
    e.preventDefault();
    open(card);
  });
})();
