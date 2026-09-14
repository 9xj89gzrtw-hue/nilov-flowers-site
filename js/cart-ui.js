/* Панель корзины: счётчик в шапке, drawer со списком позиций, +/- qty,
   удаление, итог, кнопка «Оформить заказ» (переход к форме на главной).
   Слушает 'cart:change' из cart.js — перерисовка при любой мутации. */
(function () {
  if (!window.cart) return;

  const toggle = document.getElementById('cartToggle');
  const count = document.getElementById('cartCount');
  const panel = document.getElementById('cartPanel');
  const backdrop = document.getElementById('cartBackdrop');
  const closeBtn = document.getElementById('cartClose');
  const itemsEl = document.getElementById('cartItems');
  const emptyEl = document.getElementById('cartEmpty');
  const totalEl = document.getElementById('cartTotal');
  const checkoutBtn = document.getElementById('cartCheckout');
  const continueBtn = document.getElementById('cartContinue');
  const orderSelected = document.getElementById('orderSelected');
  if (!toggle || !panel) return;

  const cartMode = toggle.dataset.cartMode || 'drawer';

  let unavailableProductId = null;

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatPrice(price) {
    return String(Math.round(Number(price) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  function itemRowHtml(item) {
    const isUnavailable = item.product_id === unavailableProductId;
    const media = item.image
      ? '<img class="cart-item__img" src="' + escapeHtml(item.image) + '" alt="Фото букета ' + escapeHtml(item.name || '') + '" loading="lazy">'
      : '<div class="cart-item__img-placeholder"></div>';
    return (
      '<div class="cart-item' + (isUnavailable ? ' cart-item--unavailable' : '') + '" data-product-id="' + escapeHtml(item.product_id) + '">' +
        '<div class="cart-item__media">' + media + '</div>' +
        '<div class="cart-item__info">' +
          '<p class="cart-item__name">' + escapeHtml(item.name || '') + '</p>' +
          '<p class="cart-item__price">' + formatPrice(item.price) + ' ₽ / шт' + (item.qty > 1 ? ' · ' + item.qty + ' шт' : '') + '</p>' +
          (isUnavailable ? '<p class="cart-item__error">Этот товар больше недоступен</p>' : '') +
          '<div class="cart-item__qty">' +
            '<button type="button" class="cart-item__qtybtn" data-action="dec" aria-label="Уменьшить количество">−</button>' +
            '<span class="cart-item__qtynum" aria-live="polite">' + (item.qty || 1) + '</span>' +
            '<button type="button" class="cart-item__qtybtn" data-action="inc" aria-label="Увеличить количество">+</button>' +
          '</div>' +
        '</div>' +
        '<button type="button" class="cart-panel__close cart-item__remove" data-action="remove" aria-label="Удалить из корзины">&times;</button>' +
      '</div>'
    );
  }

  function itemsWord(n) {
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return 'позиция';
    if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return 'позиции';
    return 'позиций';
  }

  /* ============ ПРОМОКОД (критик functional top#3) ============
     Клиент хранит только код; скидку считает и проверяет сервер (/api/promo,
     повторный пересчёт в /api/orders). promoState читает order-form.js при сабмите. */
  const promoMsgEl = document.getElementById('cartPromoMsg');
  const promoInput = document.getElementById('cartPromoInput');
  const promoApplyBtn = document.getElementById('cartPromoApply');
  const promoState = { code: '', discount: 0, minOrder: 0, lastCode: '' };
  window.PROMO_STATE = promoState;

  function promoFeedback(text, isErr) {
    if (!promoMsgEl) return;
    promoMsgEl.textContent = text;
    promoMsgEl.style.color = isErr ? 'var(--err,#d64545)' : 'var(--ink-soft)';
  }

  function promoClear(silent) {
    promoState.code = ''; promoState.discount = 0; promoState.minOrder = 0;
    if (!silent) promoFeedback('', false);
  }

  if (promoApplyBtn && promoInput) {
    promoApplyBtn.addEventListener('click', function () {
      const code = promoInput.value.trim();
      if (!code) { promoFeedback('Введите промокод', true); return; }
      promoApplyBtn.disabled = true;
      promoFeedback('Проверяем…', false);
      fetch('/api/promo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code, subtotal: window.cart.getTotal() })
      }).then(function (r) { return r.json(); }).then(function (res) {
        promoApplyBtn.disabled = false;
        if (res && res.ok) {
          promoState.code = res.code; promoState.discount = res.discount;
          promoState.lastCode = res.code; promoState.minOrder = 0;
          promoFeedback(res.label || 'Промокод применён', false);
        } else {
          promoClear(true);
          const msg = res && res.error === 'min_order' && res.min
            ? 'Промокод действует от ' + formatPrice(res.min) + ' ₽'
            : 'Такого промокода нет или он истёк';
          if (res && res.error === 'min_order') promoState.minOrder = 0;
          promoFeedback(msg, true);
          promoState.code = ''; promoState.discount = 0;
        }
        render();
      }).catch(function () {
        promoApplyBtn.disabled = false;
        promoFeedback('Не удалось проверить промокод — попробуйте позже', true);
      });
    });
  }

  function render() {
    const items = window.cart.getItems();
    const totalQty = items.reduce((sum, i) => sum + i.qty, 0);

    count.textContent = String(totalQty);
    count.hidden = totalQty === 0;

    itemsEl.innerHTML = items.map(itemRowHtml).join('');
    emptyEl.hidden = items.length > 0;
    itemsEl.hidden = items.length === 0;

    totalEl.textContent = formatPrice(window.cart.getTotal()) + ' ₽';
    /* Промокод (критик functional top#3): показываем серверную скидку; код и скидка
       уходят в POST /api/orders, где пересчитываются заново (клиенту не доверяем). */
    if (promoMsgEl) {
      if (promoState.code && window.cart.getTotal() < promoState.minOrder) {
        promoState.code = ''; promoState.discount = 0;
        promoMsgEl.textContent = 'Промокод ' + promoState.lastCode + ' действует от ' + formatPrice(promoState.minOrder) + ' ₽ — добавьте ещё цветов';
        promoMsgEl.style.color = 'var(--err,#d64545)';
      }
    }
    if (promoState.code && promoState.discount > 0) {
      totalEl.innerHTML = '<s style="opacity:.55;margin-right:6px">' + formatPrice(window.cart.getTotal()) + ' ₽</s>' + formatPrice(Math.max(0, window.cart.getTotal() - promoState.discount)) + ' ₽';
    }
    checkoutBtn.disabled = items.length === 0;

    if (orderSelected) {
      orderSelected.textContent =
        items.length > 0
          ? 'В заказе: ' + items.length + ' ' + itemsWord(items.length) + ' на ' + formatPrice(window.cart.getTotal()) + ' ₽'
          : '';
    }

    /* Панель открыта — обновляем апсейл при каждой мутации корзины,
       чтобы добавленный товар сразу исчезал из предложений. */
    if (!panel.hidden) renderUpsell();
  }

  /* Апсейл в корзине: допродаём активные товары, которых ещё нет в корзине
     (в демо-режиме каталог уже в DOM — читаем карточки витрины, без сервера). */
  const upsellEl = document.getElementById('cartUpsell');
  const upsellItemsEl = document.getElementById('cartUpsellItems');

  function renderUpsell() {
    if (!upsellEl || !upsellItemsEl) return;
    const inCart = new Set(window.cart.getItems().map((i) => i.product_id));
    /* Категории-источники апсейла (критерий 16): window.UPSELL_CATEGORIES из настроек;
       пусто = все активные товары. Товар карты несёт data-category-id. */
    const allowedCats = Array.isArray(window.UPSELL_CATEGORIES) ? window.UPSELL_CATEGORIES.map(String) : [];
    const candidates = [];
    document.querySelectorAll('.product-card').forEach(function (card) {
      const cta = card.querySelector('[data-order-cta]');
      if (!cta) return;
      const id = cta.dataset.productId;
      if (!id || inCart.has(id)) return;
      if (allowedCats.length > 0) {
        const catId = card.getAttribute('data-category-id') || '';
        if (!allowedCats.includes(catId)) return;
      }
      candidates.push({
        id: id,
        name: cta.dataset.productName || '',
        price: Number(cta.dataset.productPriceRaw) || 0,
        image: cta.dataset.productImage || '',
      });
    });
    if (candidates.length === 0 || window.UPSELL_ENABLED === 0) {
      upsellEl.hidden = true;
      upsellItemsEl.innerHTML = '';
      return;
    }
    upsellItemsEl.innerHTML = candidates.slice(0, window.UPSELL_LIMIT || 3).map(upsellItemHtml).join('');
    upsellEl.hidden = false;
  }

  function upsellItemHtml(item) {
    const media = item.image
      ? '<img class="cart-upsell-item__img" src="' + escapeHtml(item.image) + '" alt="">'
      : '<div class="cart-upsell-item__img-placeholder"></div>';
    return (
      '<div class="cart-upsell-item">' +
        media +
        '<div class="cart-upsell-item__info">' +
          '<p class="cart-upsell-item__name">' + escapeHtml(item.name) + '</p>' +
          '<p class="cart-upsell-item__price">' + formatPrice(item.price) + ' ₽</p>' +
        '</div>' +
        '<button type="button" class="cart-upsell-item__add" data-upsell-add' +
          ' data-id="' + escapeHtml(item.id) + '" data-name="' + escapeHtml(item.name) + '"' +
          ' data-price="' + escapeHtml(item.price) + '" data-image="' + escapeHtml(item.image) + '"' +
          ' aria-label="Добавить: ' + escapeHtml(item.name) + '">+</button>' +
      '</div>'
    );
  }

  if (upsellItemsEl) {
    upsellItemsEl.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-upsell-add]');
      if (!btn) return;
      window.cart.add(btn.dataset.id, 1, {
        name: btn.dataset.name || '',
        price: Number(btn.dataset.price) || 0,
        image: btn.dataset.image || '',
      });
    });
  }

  function open() {
    panel.hidden = false;
    document.body.classList.add('no-scroll');
    toggle.setAttribute('aria-expanded', 'true');
    renderUpsell();
    if (closeBtn) closeBtn.focus();
  }

  function close() {
    panel.hidden = true;
    document.body.classList.remove('no-scroll');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.focus();
  }

  function goToOrderSection() {
    close();
    const orderSection = document.getElementById('order');
    if (orderSection) {
      orderSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      const nameInput = document.getElementById('orderName');
      if (nameInput) nameInput.focus();
    } else {
      /* Секция #order есть только на главной — переходим туда с якорем */
      window.location.href = '/#order';
    }
  }

  toggle.addEventListener('click', function () {
    if (cartMode === 'page') goToOrderSection();
    else open();
  });
  if (closeBtn) closeBtn.addEventListener('click', close);
  if (backdrop) backdrop.addEventListener('click', close);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !panel.hidden) close();
  });

  itemsEl.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const row = btn.closest('[data-product-id]');
    const productId = row && row.dataset.productId;
    if (!productId) return;

    if (btn.dataset.action === 'remove') {
      if (productId === unavailableProductId) unavailableProductId = null;
      window.cart.remove(productId);
    } else if (btn.dataset.action === 'inc' || btn.dataset.action === 'dec') {
      const items = window.cart.getItems();
      const item = items.find((i) => i.product_id === productId);
      if (!item) return;
      const delta = btn.dataset.action === 'inc' ? 1 : -1;
      if (item.qty + delta < 1) {
        if (productId === unavailableProductId) unavailableProductId = null;
        window.cart.remove(productId);
      } else {
        window.cart.updateQty(productId, item.qty + delta);
      }
    }
  });

  if (checkoutBtn) checkoutBtn.addEventListener('click', goToOrderSection);
  if (continueBtn) continueBtn.addEventListener('click', close);

  window.addEventListener('cart:change', render);
  render();

  window.cartUI = {
    open: open,
    /* Открывает панель и подсвечивает недоступную позицию — вызывается
       из order-form.js при 400 { item: { product_id } } */
    markUnavailable: function (productId) {
      unavailableProductId = String(productId);
      render();
      open();
    },
  };
})();
