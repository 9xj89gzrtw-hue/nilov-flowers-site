/* Панель корзины: счётчик в шапке, drawer со списком позиций, +/- qty,
   удаление, итог, кнопка «Оформить заказ» (переход к форме на главной).
   Слушает 'cart:change' из cart.js — перерисовка при любой мутации. */
(function () {
  if (!window.cart) return;

  /* W98-fixF (F4): цель Метрики — guarded (нет счётчика/ym → тихий пропуск) */
  function nfGoal(name) {
    if (window.ym && window.__nfYmId) {
      try { ym(window.__nfYmId, 'reachGoal', name); } catch (e) { /* метрика не критична */ }
    }
  }

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

  /* H8 (W99-fixG2): плавные скроллы — уважаем prefers-reduced-motion
     (как five.js): при reduce — мгновенный 'auto'. */
  function scrollBehavior() {
    return (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
      ? 'auto' : 'smooth';
  }

  let unavailableProductId = null;

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatPrice(price) {
    /* W98-fixF (F6): тысячи — неразрывным пробелом U+00A0 (как PHP formatPrice):
       «2 500 ₽» не рвётся по строке на «2» и «500» */
    return String(Math.round(Number(price) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');
  }

  function itemRowHtml(item) {
    const isUnavailable = item.product_id === unavailableProductId;
    const media = item.image
      ? '<img class="cart-item__img" src="' + escapeHtml(item.image) + '" alt="Фото: ' + escapeHtml(item.name || '') + '" loading="lazy">'
      : '<div class="cart-item__img-placeholder"></div>';
    return (
      '<div class="cart-item' + (isUnavailable ? ' cart-item--unavailable' : '') + '" data-product-id="' + escapeHtml(item.product_id) + '">' +
        '<div class="cart-item__media">' + media + '</div>' +
        '<div class="cart-item__info">' +
          '<p class="cart-item__name">' + escapeHtml(item.name || '') + '</p>' +
          '<p class="cart-item__price">' + formatPrice(item.price) + '\u00A0₽ / шт' + (item.qty > 1 ? ' · ' + item.qty + ' шт' : '') + '</p>' +
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

  /* ============ A-b3 (3): пустая корзина — empty-state с CTA ============
     Бизнес-критик (P1): после удаления последнего товара drawer показывал
     задизейбленную кнопку «Оформить заказ» и сухую строку — нет ни объяснения,
     ни пути назад. Теперь: «В корзине пока пусто» + CTA «Выбрать букет»
     (закрывает drawer и ведёт к каталогу #catalog); задизейбленная кнопка
     оформления в пустом состоянии скрыта. Текст дефолтной настройки
     заменяем; кастомный текст владельца (cart_empty_text) не трогаем. */
  const DEFAULT_EMPTY_TEXT = 'Корзина пуста — выберите букет в каталоге';
  var emptyCtaBtn = null;
  (function setupEmptyState() {
    if (emptyEl && (emptyEl.textContent || '').trim() === DEFAULT_EMPTY_TEXT) {
      emptyEl.textContent = 'В корзине пока пусто';
    }
    if (emptyEl) {
      emptyEl.style.textAlign = 'center';
      emptyEl.style.margin = '6px 0 0';
    }
    if (emptyEl && !document.getElementById('cartEmptyCta')) {
      emptyCtaBtn = document.createElement('button');
      emptyCtaBtn.type = 'button';
      emptyCtaBtn.id = 'cartEmptyCta';
      emptyCtaBtn.className = 'btn btn--accent';
      emptyCtaBtn.textContent = 'Выбрать букет';
      /* тач-зона ≥44px (AGENTS): кнопка полношириная, 48px высотой */
      emptyCtaBtn.style.cssText = 'margin-top:16px;width:100%;min-height:48px';
      emptyCtaBtn.addEventListener('click', goToCatalog);
      emptyEl.parentNode.insertBefore(emptyCtaBtn, emptyEl.nextSibling);
    } else {
      emptyCtaBtn = document.getElementById('cartEmptyCta');
    }
  })();

  /* CTA пустой корзины: закрыть drawer и привести к каталогу (как
     goToOrderSection — Lenis с offset −96, иначе нативный скролл;
     на вторичных страницах без #catalog — переход на /#catalog). */
  function goToCatalog() {
    close();
    const catalogSection = document.getElementById('catalog');
    if (!catalogSection) {
      window.location.href = '/#catalog';
      return;
    }
    requestAnimationFrame(function () {
      const lenis = window.NF_LENIS;
      if (lenis && !lenis.isStopped && typeof lenis.scrollTo === 'function') {
        lenis.scrollTo(catalogSection, { offset: -96 });
      } else {
        catalogSection.scrollIntoView({ behavior: scrollBehavior(), block: 'start' });
      }
    });
  }

  function itemsWord(n) {
    const mod10 = n % 10;
    const mod100 = n % 100;
    /* W106-E1 (корректор P0): «товар/товара/товаров» → «букет/букета/букетов» —
       магазин продаёт букеты, не товары; та же терминология, что у пилюли
       поиска (pluralBuket в five.js) и чипов каталога. */
    if (mod10 === 1 && mod100 !== 11) return 'букет';
    if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return 'букета';
    return 'букетов';
  }

  /* ============ W97-fixA (A7): live-регион для молчаливых сумм ============
     #cartTotal / #orderSelected (и #orderTotal в order-form.js) менялись
     без анонса — скринридер молчал. Один общий визуально-скрытый div
     #nfSrLive (aria-live=polite, создаётся здесь при первом анонсе).
     Пишем ТОЛЬКО по факту изменения значения (кэш по ключу в data-атрибуте),
     чтобы не спамить на каждую перерисовку. */
  let liveEl = null;
  function announce(key, text) {
    if (!text) return;
    if (!liveEl || !liveEl.isConnected) {
      liveEl = document.getElementById('nfSrLive');
      if (!liveEl) {
        liveEl = document.createElement('div');
        liveEl.id = 'nfSrLive';
        liveEl.setAttribute('role', 'status');
        liveEl.setAttribute('aria-live', 'polite');
        liveEl.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap';
        document.body.appendChild(liveEl);
      }
    }
    if (liveEl.getAttribute('data-' + key) === text) return; /* значение не менялось */
    liveEl.setAttribute('data-' + key, text);
    liveEl.textContent = text;
  }
  window.nfAnnounce = announce; /* им же пользуется order-form.js (#orderTotal) */

  /* ============ ПРОМОКОД (критик functional top#3) ============
     Клиент хранит только код; скидку считает и проверяет сервер (/api/promo,
     повторный пересчёт в /api/orders). promoState читает order-form.js при сабмите. */
  const promoMsgEl = document.getElementById('cartPromoMsg');
  const promoInput = document.getElementById('cartPromoInput');
  const promoApplyBtn = document.getElementById('cartPromoApply');
  const promoState = { code: '', discount: 0, minOrder: 0, lastCode: '', kind: '', val: 0 };
  window.PROMO_STATE = promoState;

  /* W78 (владелец-критик w4h8 OPEN_NEW-1): та же формула, что в /api/promo и /api/orders
     (fixed: min(value, subtotal); percent: floor(subtotal*min(90,value)/100)) —
     итог в drawer всегда совпадает с тем, что засчитает сервер. */
  function serverDiscount(subtotal) {
    if (!promoState.kind) return 0;
    return promoState.kind === 'fixed'
      ? Math.min(promoState.val, subtotal)
      : Math.floor(subtotal * Math.max(0, Math.min(90, promoState.val)) / 100);
  }

  function promoFeedback(text, isErr) {
    /* G-g2: сообщение пишем в ОБА поля — drawer (#cartPromoMsg) и строку
       промокода в форме заказа (#orderPromoMsg, partials/order-form.php):
       источник PROMO_STATE один, состояния не расходятся. */
    if (promoMsgEl) {
      promoMsgEl.textContent = text;
      promoMsgEl.style.color = isErr ? 'var(--err,#d64545)' : 'var(--ink-soft)';
    }
    var formMsg = document.getElementById('orderPromoMsg');
    if (formMsg) {
      formMsg.textContent = text;
      formMsg.style.color = isErr ? 'var(--err,#d64545)' : 'var(--ink-soft)';
    }
  }

  function promoClear(silent) {
    promoState.code = ''; promoState.discount = 0; promoState.minOrder = 0;
    promoState.kind = ''; promoState.val = 0;
    if (!silent) promoFeedback('', false);
  }

  /* G-g2: прямая запись цветного статуса в ОБА поля промо (render-ветки
     W78/W79/W81 писали только в drawer — строка в форме заказа могла
     залипать с устаревшим текстом) */
  function promoMsgWrite(text, color) {
    if (promoMsgEl) { promoMsgEl.textContent = text; promoMsgEl.style.color = color; }
    var fm = document.getElementById('orderPromoMsg');
    if (fm) { fm.textContent = text; fm.style.color = color; }
  }

  /* G-g2 (редактор P1): применение промокода — единый запрос для поля
     drawer И поля в форме заказа (оба пишут общий promoState). */
  function applyPromoCode(rawCode) {
    const code = String(rawCode || '').trim();
    if (!code) { promoFeedback('Введите промокод', true); return; }
    if (promoApplyBtn) promoApplyBtn.disabled = true;
    const formApplyBtn = document.getElementById('orderPromoApply');
    if (formApplyBtn) formApplyBtn.disabled = true;
    promoFeedback('Проверяем…', false);
    fetch('/api/promo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: code, subtotal: window.cart.getTotal() })
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (promoApplyBtn) promoApplyBtn.disabled = false;
      if (formApplyBtn) formApplyBtn.disabled = false;
      if (res && res.ok) {
        promoState.code = res.code; promoState.discount = res.discount;
        promoState.lastCode = res.code;
        /* W78: сохраняем реальный порог и формулу — раньше minOrder вечно =0 убивал guard ниже */
        promoState.minOrder = (res.min | 0) > 0 ? (res.min | 0) : 0;
        promoState.kind = res.kind === 'fixed' ? 'fixed' : (res.kind === 'percent' ? 'percent' : '');
        promoState.val = res.val | 0;
        promoFeedback(res.label || 'Промокод применён', false);
        syncPromoInputs(res.code);
      } else {
        promoClear(true);
        if (res && res.error === 'min_order' && res.min) { promoState.lastCode = code.trim().toUpperCase(); promoState.minOrder = res.min | 0; } /* W86: регистр как на success */ /* W80: рост корзины → зелёная подсказка вместо залипшего красного */
        const msg = res && res.error === 'min_order' && res.min
          ? 'Промокод действует от ' + formatPrice(res.min) + '\u00A0₽'
          : 'Такого промокода нет или он истёк';
        promoFeedback(msg, true);
        promoState.code = ''; promoState.discount = 0;
      }
      render();
      /* сводка на /checkout слушает только cart:change — промо меняет
         итог без события, дёргаем её напрямую (экспорт ниже в IIFE) */
      if (typeof window.nfRenderCheckoutSummary === 'function') window.nfRenderCheckoutSummary();
    }).catch(function () {
      if (promoApplyBtn) promoApplyBtn.disabled = false;
      if (formApplyBtn) formApplyBtn.disabled = false;
      promoFeedback('Не удалось проверить промокод — попробуйте позже', true);
    });
  }

  /* применённый код — видим в обоих полях (drawer + форма); снятие — чистим */
  function syncPromoInputs(appliedCode) {
    if (promoInput && appliedCode) promoInput.value = appliedCode;
    var formInput = document.getElementById('orderPromoInput');
    if (formInput && appliedCode) formInput.value = appliedCode;
  }

  if (promoApplyBtn && promoInput) {
    promoApplyBtn.addEventListener('click', function () {
      applyPromoCode(promoInput.value);
    });
  }

  /* поле промокода в форме заказа (G-g2): клик по «Применить» и Enter
     (Enter в текст-поле внутри формы иначе отправил бы заказ) */
  (function orderPromoField() {
    var inp = document.getElementById('orderPromoInput');
    var btn = document.getElementById('orderPromoApply');
    if (!inp || !btn) return;
    btn.addEventListener('click', function () { applyPromoCode(inp.value); });
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        applyPromoCode(inp.value);
      }
    });
    /* состояние применённого кода видно сразу (например, применили в drawer
       на главной, потом дошли до формы) */
    if (promoState.code) inp.value = promoState.code;
  })();

  function render() {
    const items = window.cart.getItems();
    const totalQty = items.reduce((sum, i) => sum + i.qty, 0);

    count.textContent = String(totalQty);
    count.hidden = totalQty === 0;

    /* S3 (v2026.3): сумма заказа рядом со счётчиком — в шапке (#cartSum под
       иконкой) и в мобильной панели (#mnavCartSum у «Корзина»). Промо-скидку
       НЕ учитываем: это стоимость букетов, итог с промо виден в drawer/форме. */
    const cartSum = document.getElementById('cartSum');
    if (cartSum) {
      const t = items.length > 0 ? window.cart.getTotal() : 0;
      cartSum.textContent = items.length > 0 ? formatPrice(t) + '\u00A0₽' : '';
      cartSum.hidden = items.length === 0;
    }
    const mnavSum = document.getElementById('mnavCartSum');
    if (mnavSum) {
      mnavSum.textContent = items.length > 0 ? formatPrice(window.cart.getTotal()) + '\u00A0₽' : '';
      mnavSum.hidden = items.length === 0;
    }

    /* S3: прогресс до бесплатной доставки (порог FREE_DELIVERY_FROM из footer.php;
       прогресс-бар #cartFreeBar печатается только при включённой настройке). */
    updateFreeBar(window.cart.getTotal());

    itemsEl.innerHTML = items.map(itemRowHtml).join('');
    emptyEl.hidden = items.length > 0;
    /* A-b3 (3): CTA пустой корзины живёт в тандеме с подписью — виден только
       в пустом состоянии; кнопка «Оформить заказ» в пустом состоянии скрыта
       (задизейбленная кнопка — мёртвый элемент, путь вперёд — «Выбрать букет»). */
    if (emptyCtaBtn) emptyCtaBtn.hidden = items.length > 0;
    if (checkoutBtn) checkoutBtn.hidden = items.length === 0;
    itemsEl.hidden = items.length === 0;

    /* H7 (W99-fixG2): пустая корзина — не показываем «Итого: 0 ₽»: скрываем
     весь ряд итога (подпись «Корзина пуста» уже объясняет состояние),
     и не оставляем «0 ₽» в DOM. */
    const totalRow = totalEl.closest ? totalEl.closest('.cart-panel__total') : null;
    if (totalRow) totalRow.hidden = items.length === 0;
    totalEl.textContent = items.length === 0 ? '' : formatPrice(window.cart.getTotal()) + '\u00A0₽';
    /* A7: анонс итога — по факту изменения (учитывает промо-скидку ниже) */
    let payable = window.cart.getTotal();
    /* W81 (владелец OPEN_NEW-1): пустая корзина — блок промо и его сообщения глушим
       целиком: «действует от 5 000 ₽» на экране «Корзина пуста» выглядит залипшим. */
    var promoWrap = document.getElementById('cartPromo');
    var promoEmpty = items.length === 0;
    if (promoEmpty && (promoState.code || promoState.lastCode)) {
      promoClear(true);
      promoState.lastCode = ''; promoMsgWrite('', ''); /* W81b: не прятать msg с залипшим красным inline-color */
    } /* иначе minOrder=0 и зелёная ветка сработала бы ложно после возврата товара */
    if (promoWrap) promoWrap.hidden = promoEmpty;
    if (promoMsgEl) promoMsgEl.hidden = promoEmpty;
    /* Промокод (критик functional top#3): показываем серверную скидку; код и скидка
       уходят в POST /api/orders, где пересчитываются заново (клиенту не доверяем). */
    if (promoMsgEl) {
      if (promoState.code && window.cart.getTotal() < promoState.minOrder) {
        promoState.code = ''; promoState.discount = 0;
        promoState.kind = ''; promoState.val = 0;
        promoMsgWrite('Промокод ' + promoState.lastCode + ' действует от ' + formatPrice(promoState.minOrder) + '\u00A0₽ — добавьте ещё цветов', 'var(--err,#d64545)');
      } else if (!promoState.code && promoState.lastCode && window.cart.getTotal() >= promoState.minOrder) {
        /* W79 (владелец OPEN_NEW-1): корзина снова выше порога — залипшее красное
           предупреждение врёт о текущем состоянии; снимаем его, подсказываем повтор. */
        promoMsgWrite('Порог для промокода ' + promoState.lastCode + ' достигнут — введите код заново', 'var(--ok,#2e7d32)');
      } else if (promoState.code && promoState.kind) {
        /* W78: пересчёт по текущей корзине — скидка не должна «застывать» при смене qty.
           (Совпадение с серверной формулой /api/orders гарантировано той же функцией.) */
        promoState.discount = serverDiscount(window.cart.getTotal());
        promoFeedback((promoState.kind === 'fixed'
          ? '−' + formatPrice(promoState.discount) + '\u00A0₽'
          : '−' + promoState.val + '%') + ' по промокоду ' + promoState.code, false);
      }
    }
    if (promoState.code && promoState.discount > 0) {
      totalEl.innerHTML = '<s style="opacity:.55;margin-right:6px">' + formatPrice(window.cart.getTotal()) + '\u00A0₽</s>' + formatPrice(Math.max(0, window.cart.getTotal() - promoState.discount)) + '\u00A0₽';
      payable = Math.max(0, window.cart.getTotal() - promoState.discount);
    }
    /* H7 (W99-fixG2): пустая корзина — «Итого» не на экране, SR тоже молчит;
       кэш анонса сбрасываем, чтобы возврат товара с прежней суммой озвучился. */
    if (items.length === 0) {
      if (liveEl) liveEl.removeAttribute('data-cartTotal');
    } else {
      announce('cartTotal', 'Итого: ' + formatPrice(payable) + '\u00A0₽');
    }
    checkoutBtn.disabled = items.length === 0;
    if (orderSelected) {
      orderSelected.textContent =
        items.length > 0
          ? 'В заказе: ' + items.length + ' ' + itemsWord(items.length) + ' на ' + formatPrice(window.cart.getTotal()) + '\u00A0₽'
          : '';
    }
    announce('orderSelected', items.length > 0
      ? 'В заказе: ' + items.length + ' ' + itemsWord(items.length)
      : '');

    /* Панель открыта — обновляем апсейл при каждой мутации корзины,
       чтобы добавленный товар сразу исчезал из предложений. */
    if (!panel.hidden) {
      renderUpsell();
      if (panel.classList.contains('is-entering')) staggerItems(); /* W104-β: новые строки — в каскад */
    }

    /* W98-fixF (F7): пустая корзина на #order — показываем заглушку волны E
       (#orderEmptyState уже в DOM, скрыта инлайном) и прячем саму форму:
       заголовок секции и «В заказе: …» остаются. Инлайн-display (не [hidden]):
       CSS задаёт .order-form{display:grid} и перебил бы атрибут. Добавили товар /
       вернули из корзины — форма возвращается.
       K6 (W101): та же проверка — отдельная функция: вызывается не только из
       render() по 'cart:change', но и при инициализации (DOMContentLoaded) и на
       pageshow — возврат «Назад» из bfcache (после заказа корзина уже пуста,
       а DOM восстановлен со видимой формой) раньше ждал первого события
       корзины. */
    syncOrderEmptyState();
  }

  function syncOrderEmptyState() {
    var orderEmptyEl = document.getElementById('orderEmptyState');
    if (!orderEmptyEl) return;
    var itemsNow = window.cart ? window.cart.getItems() : [];
    var orderFormEl = document.getElementById('orderForm');
    orderEmptyEl.style.display = itemsNow.length === 0 ? 'block' : 'none';
    if (orderFormEl) orderFormEl.style.display = itemsNow.length === 0 ? 'none' : '';
  }

  /* ============ S3 (v2026.3): прогресс бесплатной доставки + бесплатные допы ============

     Прогресс: заполняемость трека = subtotal / FREE_DELIVERY_FROM; под треком —
     текст из настроек ({left} — сколько осталось). Порог достигнут — зелёный
     текст-достижение. Рендер без rAF: ширина через style.width (паттерн S2). */
  function updateFreeBar(subtotal) {
    var bar = document.getElementById('cartFreeBar');
    if (!bar) return;
    var from = Number(window.FREE_DELIVERY_FROM) || 0;
    if (from <= 0) { bar.hidden = true; return; }
    var fill = document.getElementById('cartFreeFill');
    var text = document.getElementById('cartFreeText');
    var pct = Math.max(0, Math.min(100, Math.round((subtotal / from) * 100)));
    if (fill) fill.style.width = pct + '%';
    bar.setAttribute('aria-valuenow', String(Math.min(subtotal, from)));
    bar.classList.toggle('is-reached', subtotal >= from);
    if (text) {
      if (subtotal >= from) {
        text.textContent = window.CART_FREE_PROGRESS_REACHED || 'Доставка бесплатно 🎉';
      } else {
        var left = Math.max(0, from - subtotal);
        text.textContent = (window.CART_FREE_PROGRESS_UNDER || 'Добавьте ещё {left} ₽ — и доставка бесплатна')
          .replace('{left}', formatPrice(left));
      }
    }
  }

  /* Бесплатные допы корзины: открытка (с текстом) и подкормка Chrysal.
     Состояние — localStorage nf_cart_extras; открытка синхронизируется с
     полем #orderCardText формы заказа (пользователь может редактировать
     в любом из мест); order-form.js перед отправкой читает window.NF_CART_EXTRAS
     и прикладывает к POST /api/orders (extras + card_text). */
  var cartExtras = { postcard: false, postcardText: '', chrysal: false };
  try {
    var savedExtras = JSON.parse(localStorage.getItem('nf_cart_extras') || '{}');
    if (savedExtras && typeof savedExtras === 'object') {
      cartExtras.postcard = !!savedExtras.postcard;
      cartExtras.postcardText = typeof savedExtras.postcardText === 'string' ? savedExtras.postcardText.slice(0, 500) : '';
      cartExtras.chrysal = !!savedExtras.chrysal;
    }
  } catch (e) { /* битый JSON — начинаем с чистого состояния */ }

  function saveExtras() {
    try { localStorage.setItem('nf_cart_extras', JSON.stringify(cartExtras)); } catch (e) {}
    window.NF_CART_EXTRAS = { postcard: cartExtras.postcard, postcardText: cartExtras.postcardText, chrysal: cartExtras.chrysal };
    /* двусторонняя синхронизация с полем открытки в форме заказа */
    var formCard = document.getElementById('orderCardText');
    if (formCard && cartExtras.postcard && formCard.value !== cartExtras.postcardText) {
      formCard.value = cartExtras.postcardText;
    }
  }
  window.NF_CART_EXTRAS = { postcard: cartExtras.postcard, postcardText: cartExtras.postcardText, chrysal: cartExtras.chrysal };
  window.nfCartExtrasReset = function () {
    cartExtras.postcard = false; cartExtras.postcardText = ''; cartExtras.chrysal = false;
    saveExtras();
    syncExtrasUi();
  };

  function syncExtrasUi() {
    var pc = document.getElementById('cartExtraPostcardOn');
    if (pc) pc.checked = cartExtras.postcard;
    var pcw = document.getElementById('cartExtraPostcardWrap');
    if (pcw) pcw.hidden = !cartExtras.postcard;
    var pct = document.getElementById('cartExtraPostcardText');
    if (pct && document.activeElement !== pct) pct.value = cartExtras.postcardText;
    var ch = document.getElementById('cartExtraChrysalOn');
    if (ch) ch.checked = cartExtras.chrysal;
  }

  (function initCartExtras() {
    var extrasBox = document.getElementById('cartExtras');
    if (!extrasBox) { saveExtras(); return; }
    extrasBox.addEventListener('change', function (e) {
      var t = e.target;
      if (t.id === 'cartExtraPostcardOn') {
        cartExtras.postcard = t.checked;
        if (!t.checked) { cartExtras.postcardText = ''; }
      } else if (t.id === 'cartExtraChrysalOn') {
        cartExtras.chrysal = t.checked;
      }
      saveExtras();
      syncExtrasUi();
    });
    extrasBox.addEventListener('input', function (e) {
      if (e.target.id === 'cartExtraPostcardText') {
        cartExtras.postcardText = e.target.value.slice(0, 500);
        saveExtras();
      }
    });
    /* редактирование открытки в форме заказа — обратно в корзину */
    var formCard = document.getElementById('orderCardText');
    if (formCard) {
      formCard.addEventListener('input', function () {
        if (cartExtras.postcard) {
          cartExtras.postcardText = formCard.value.slice(0, 500);
          saveExtras();
        }
      });
    }
    syncExtrasUi();
    saveExtras();
  })();

  /* K6 (W101): пустая корзина видна на #order сразу при загрузке страницы
     (и после bfcache-возврата) — не только после первого события корзины. */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncOrderEmptyState);
  } else {
    syncOrderEmptyState();
  }
  window.addEventListener('pageshow', syncOrderEmptyState);

  /* Апсейл в корзине: допродаём активные товары, которых ещё нет в корзине
     (в демо-режиме каталог уже в DOM — читаем карточки витрины, без сервера).
     K10 (W101): ПРЕДПОЧИТАЕМ товары с data-upsell="1" (show_in_upsell из БД —
     печатает index.php на каталог-карточках; это сладкие допы: клубника в
     шоколаде, макаруны…). Раньше брались первые попавшиеся карточки в DOM —
     на главной это хиты-карусель («ещё 3 букета»), на странице товара — случайные
     related-букеты. Теперь:
       • есть flagged-товары → показываем их первыми (остаток лимита — прочими);
       • flagged нет и это страница с полным каталогом (#catalogGrid) — прежний
         фолбэк (владелец мог не отметить ни один товар);
       • flagged нет и страница вторичная (товар/повод/категория — без каталога) —
         блок «Возможно, пригодится» прячем целиком: случайные букеты — не апсейл.
     Дубли карточек (карусель + каталог одного товара) — дедуп по id. */
  const upsellEl = document.getElementById('cartUpsell');
  const upsellItemsEl = document.getElementById('cartUpsellItems');

  function renderUpsell() {
    if (!upsellEl || !upsellItemsEl) return;
    if (window.UPSELL_ENABLED === 0) {
      upsellEl.hidden = true;
      upsellItemsEl.innerHTML = '';
      return;
    }
    const inCart = new Set(window.cart.getItems().map((i) => i.product_id));
    /* Категории-источники апсейла (критерий 16): window.UPSELL_CATEGORIES из настроек;
       пусто = все активные товары. Товар карты несёт data-category-id. */
    const allowedCats = Array.isArray(window.UPSELL_CATEGORIES) ? window.UPSELL_CATEGORIES.map(String) : [];
    const preferred = []; /* data-upsell="1" — сладкие допы (show_in_upsell) */
    const others = []; /* прочие товары страницы (фолбэк при незаполненных флагах) */
    const byId = new Map(); /* id → {flagged, item}: у товара может быть ДВЕ копии
       карточки в DOM (карусель секции без data-атрибутов + каталог-карточка с
       data-upsell) — флаг собираем со ВСЕХ копий, данные берём из первой */
    document.querySelectorAll('.product-card').forEach(function (card) {
      const cta = card.querySelector('[data-order-cta]');
      if (!cta) return;
      const id = cta.dataset.productId;
      if (!id || inCart.has(id)) return;
      if (allowedCats.length > 0) {
        const catId = card.getAttribute('data-category-id') || '';
        if (!allowedCats.includes(catId)) return;
      }
      const flagged = card.getAttribute('data-upsell') === '1';
      if (!byId.has(id)) {
        byId.set(id, {
          flagged: flagged,
          item: {
            id: id,
            name: cta.dataset.productName || '',
            price: Number(cta.dataset.productPriceRaw) || 0,
            image: cta.dataset.productImage || '',
          },
        });
      } else if (flagged) {
        /* карусельная копия шла первой без флага — каталог-копия подтверждает show_in_upsell */
        byId.get(id).flagged = true;
      }
    });
    byId.forEach(function (entry) {
      (entry.flagged ? preferred : others).push(entry.item);
    });
    const hasCatalog = !!document.getElementById('catalogGrid');
    let candidates;
    if (preferred.length > 0) candidates = preferred.concat(others);
    else if (hasCatalog) candidates = others;
    else candidates = [];
    if (candidates.length === 0) {
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
          '<p class="cart-upsell-item__price">' + formatPrice(item.price) + '\u00A0₽</p>' +
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
      /* W100 (CRO-критик): добавление апсейла — тот же шаг воронки add_to_cart. */
      nfGoal('add_to_cart');
    });
  }

  /* W104-β (C1-M P1): stagger 40мс айтемов при открытии drawer —
     индексы --ci + класс is-entering (keyframes — motion-w104.css).
     Пересчитываем и при cart:change пока идёт вход (новые строки).
     prefers-reduced-motion — без анимации. */
  var enterTimer = 0;
  function staggerItems() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var rows = itemsEl.querySelectorAll('.cart-item');
    if (!rows.length) return;
    Array.prototype.forEach.call(rows, function (r, i) {
      r.style.setProperty('--ci', String(Math.min(i, 8))); /* длинные корзины — потолок 320мс */
    });
    panel.classList.add('is-entering');
    clearTimeout(enterTimer);
    enterTimer = setTimeout(function () {
      panel.classList.remove('is-entering');
      Array.prototype.forEach.call(rows, function (r) { r.style.removeProperty('--ci'); });
    }, rows.length * 40 + 520);
  }

  function open() {
    /* G-g2 (жюри P0): drawer открылся — тост «Добавлено в корзину» гасим
       МГНОВЕННО, до первого кадра анимации: тост z130 выше drawer z100 и
       на мобиле лежал ровно на кнопке «Оформить заказ» (открытие drawer
       кликом по иконке корзины тост раньше не убирало — только показ НОВОГО
       тоста при открытом drawer был отменён). cart-cta.js держит и второй
       контур — MutationObserver по body.no-scroll (лайтбокс и пр.). */
    if (typeof window.nfKillCartToast === 'function') window.nfKillCartToast();
    /* W104-δ (C2-M2 P1.4): быстрое закрытие→открытие (<240мс) — отменяем
       незавершённый выход: drawer мягко возвращается по открыточной кривой
       (hidden ещё не встал — не мигаем), состояние открытого drawer
       восстанавливаем целиком (скролл-лок/aria/inert-фон). */
    if (panel.classList.contains('is-exiting')) {
      cancelExit();
      if (!panel.hidden) { applyOpenState(); return; }
    }
    if (!panel.hidden) return;
    /* W104-β (C1-M P1, drawer-лаг ~100мс): показать и стартовать transform
       в одном кадре. Порядок был: unhide → тяжёлая синхронщина (inert-цикл
       по всему DOM + renderUpsell с innerHTML + фокус) → и только потом
       первый кадр анимации (замер — 185мс от клика). Теперь: unhide →
       forced reflow (коммит @starting-style: translateX(100%)) → лёгкие
       синхронные вещи → тяжёлая работа после первого кадра (rAF×2). */
    panel.hidden = false;
    void panel.offsetWidth; /* коммит стартового состояния в этом кадре */
    applyOpenState();
  }

  function applyOpenState() {
    document.body.classList.add('no-scroll');
    /* a11y-критик S2: body.overflow не блокирует window-scroll на iOS/Safari — вешаем на html */
    document.documentElement.classList.add('no-scroll');
    toggle.setAttribute('aria-expanded', 'true');
    /* W98-fixF (F4): открытие drawer → cart_open (воронка) */
    nfGoal('cart_open');
    staggerItems();
    if (closeBtn) closeBtn.focus();
    /* Тяжёлая работа — после первого кадра движения drawer'а */
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        if (panel.hidden || panel.classList.contains('is-exiting')) return; /* успели закрыть — не инертим */
        /* a11y-критик S1 → W97-fixA (A5): инертим ВСЁ вне drawer: шапку
           (оба варианта класса — на случай легаси-страниц), город-бар,
           skip-link, main, футер, таббар, cookie-баннер/настройки и
           PWA-подсказку nilov.js. Панель корзины — fixed sibling вне
           этих контейнеров, фокус остаётся внутри. */
        var inertEls = document.querySelectorAll('header.fc-header, header.site-header, #fcCitybar, a.skip-link, main, footer.site-footer, nav.mnav, .cookie-banner, .cookie-settings, div[role="region"][aria-label="Установка приложения"]');
        inertEls.forEach(function (el) { el.inert = true; });
        panel._inertEls = inertEls;
        /* A5: Tab-ловушка поверх inert — на последнем элементе drawer Chrome может
           транзитом выкинуть фокус на <body>; перехватываем Tab и держим цикл
           внутри панели (first↔last), фокус никогда не покидает корзину. */
        panel._trapTab = function (e) {
          if (e.key !== 'Tab' || panel.hidden) return;
          const drawer = panel.querySelector('.cart-panel__drawer');
          if (!drawer) return;
          const nodes = drawer.querySelectorAll('button, input, select, textarea, a[href], [tabindex]:not([tabindex="-1"])');
          const list = Array.prototype.filter.call(nodes, function (n) {
            return !n.disabled && (n.offsetParent !== null || n === document.activeElement);
          });
          if (!list.length) return;
          const first = list[0], last = list[list.length - 1];
          const active = document.activeElement;
          if (!drawer.contains(active)) {
            e.preventDefault();
            (e.shiftKey ? last : first).focus();
          } else if (e.shiftKey && active === first) {
            e.preventDefault();
            last.focus();
          } else if (!e.shiftKey && active === last) {
            e.preventDefault();
            first.focus();
          }
        };
        document.addEventListener('keydown', panel._trapTab);
        renderUpsell();
      });
    });
  }

  /* ============ W104-δ (C2-M2 P1.4): drawer close — полный ход анимации ============
    Критик: ~200мс мёртвой паузы и срез слайда display:none на ~55% хода
    (env-лаг стилей + allow-discrete дефер на five.css). Теперь выходом
    управляет JS: класс .is-exiting (motion-w104.css: translateX(100%)
    + fade .26s ease-in-кривой), hidden ставится ТОЛЬКО после
    transitionend (fallback 340мс). Быстрое переоткрытие — cancelExit().
    prefers-reduced-motion — мгновенно, как раньше. */
  var exitTimer = 0;
  function cancelExit() {
    clearTimeout(exitTimer);
    exitTimer = 0;
    if (panel._exitDrawer && panel._exitOnEnd) {
      panel._exitDrawer.removeEventListener('transitionend', panel._exitOnEnd);
    }
    panel._exitDrawer = null;
    panel._exitOnEnd = null;
    panel.classList.remove('is-exiting');
  }

  function close() {
    if (panel.hidden) {
      if (panel.classList.contains('is-exiting')) cancelExit();
      return;
    }
    clearTimeout(enterTimer);
    panel.classList.remove('is-entering');
    Array.prototype.forEach.call(itemsEl.querySelectorAll('.cart-item'), function (r) {
      r.style.removeProperty('--ci');
    });
    document.body.classList.remove('no-scroll');
    document.documentElement.classList.remove('no-scroll');
    (panel._inertEls || []).forEach(function (el) { el.inert = false; });
    panel._inertEls = null;
    if (panel._trapTab) {
      document.removeEventListener('keydown', panel._trapTab);
      panel._trapTab = null;
    }
    toggle.setAttribute('aria-expanded', 'false');
    toggle.focus();
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      panel.hidden = true;
      return;
    }
    cancelExit(); /* повторный close во время выхода — перезапуск чистым состоянием */
    panel.classList.add('is-exiting');
    var drawerEl = panel.querySelector('.cart-panel__drawer');
    var done = function () {
      if (!panel.classList.contains('is-exiting')) return; /* успели открыть назад */
      cancelExit();
      panel.hidden = true; /* анимация уже доиграла — без среза */
    };
    var onEnd = function (ev) {
      if (ev.target === drawerEl && ev.propertyName === 'transform') done();
    };
    panel._exitDrawer = drawerEl;
    panel._exitOnEnd = onEnd;
    if (drawerEl) drawerEl.addEventListener('transitionend', onEnd);
    exitTimer = setTimeout(done, 340); /* страховка: transitionend не пришёл */
  }

  function goToOrderSection() {
    close();
    const orderSection = document.getElementById('order');
    const orderFormEl = document.getElementById('orderForm');
    if (!orderSection && !orderFormEl) {
      /* W106-E1 (Нильсен P0 «чекаут заперт в лендинге», спешащий P1 «телепорт
         на главную #order»): со вторичных страниц (PDP/категория/повод) кнопка
         «Оформить заказ» ведёт на отдельную страницу /checkout.php — там та же
         форма (partials/order-form.php) и сводка корзины; было — переброс на
         главную к якорю #order с потерей контекста товара. */
      window.location.href = '/checkout.php';
      return;
    }
    /* D-d1 (P1-14, 55+): цель — сама ФОРМА (#orderForm), не секция #order:
       прежний скролл к секции оставлял вверху вьюпорта хвост «Дополните
       букет» (замер: scrollY 7167 при orderTop 7359 — offset и scroll-padding
       складывались), покупатель 55+ не видел полей. Форма скрыта при пустой
       корзине (syncOrderEmptyState) — тогда секция как прежде.
       Скролл — числом (не элементом): элементный scrollTo в этом Lenis
       добавляет scroll-padding-top повторно (замер: offset:0 → −96),
       числовой цель попадает ровно. Работает и на /checkout.php — там
       секция #order рендерит тот же partial. */
    const formVisible = orderFormEl && orderFormEl.offsetParent !== null;
    const target = formVisible ? orderFormEl : orderSection;
    if (target) {
      /* W104-δ: скролл — через Lenis (единый пробег с якорями).
      rAF: close() снимает скролл-лок, MutationObserver в kinetic.js
      перезапускает Lenis микротаском — к кадру скролла он уже активен. */
      requestAnimationFrame(function () {
        const top = Math.max(0,
          target.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) - 96);
        const lenis = window.NF_LENIS;
        if (lenis && !lenis.isStopped && typeof lenis.scrollTo === 'function') {
          lenis.scrollTo(top);
        } else {
          window.scrollTo({ top: top, behavior: scrollBehavior() });
        }
      });
      /* D-d1: фокус — на первое поле формы (после перегруппировки это
         селект района), preventScroll — не дёргает только что выставленную
         позицию; поле остаётся в зоне видимости. */
      const firstField = target.querySelector('select, input:not([type=hidden]):not([tabindex="-1"]), textarea');
      if (firstField) {
        try { firstField.focus({ preventScroll: true }); } catch (e) { firstField.focus(); }
      }
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

  /* ============ W104-δ (C2-M2 P1.3): удаление айтема — коллапс, не снап ============
     Строка схлопывается (grid-template-rows 1fr→0fr — паттерн FAQ — плюс
     opacity/padding/margin, 260мс), и только затем cart.remove() →
     'cart:change' → render() (перерисовка списка + пересчёт итога).
     prefers-reduced-motion — сразу, двойной клик — игнор. */
  function removeRow(row, productId) {
    if (productId === unavailableProductId) unavailableProductId = null;
    if (!row || !row.isConnected
        || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
        || row.classList.contains('is-removing')) {
      window.cart.remove(productId);
      return;
    }
    row.classList.add('is-removing');
    var t = 0;
    var done = function () {
      clearTimeout(t);
      row.removeEventListener('transitionend', onEnd);
      window.cart.remove(productId);
    };
    var onEnd = function (ev) {
      if (ev.target === row && ev.propertyName === 'grid-template-rows') done();
    };
    row.addEventListener('transitionend', onEnd);
    t = setTimeout(done, 360); /* страховка: transitionend не пришёл */
  }

  itemsEl.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const row = btn.closest('[data-product-id]');
    const productId = row && row.dataset.productId;
    if (!productId) return;

    if (btn.dataset.action === 'remove') {
      removeRow(row, productId);
    } else if (btn.dataset.action === 'inc' || btn.dataset.action === 'dec') {
      const items = window.cart.getItems();
      const item = items.find((i) => i.product_id === productId);
      if (!item) return;
      const delta = btn.dataset.action === 'inc' ? 1 : -1;
      if (item.qty + delta < 1) {
        removeRow(row, productId);
      } else {
        window.cart.updateQty(productId, item.qty + delta);
      }
    }
  });

  if (checkoutBtn) checkoutBtn.addEventListener('click', goToOrderSection);
  if (continueBtn) continueBtn.addEventListener('click', close);

  window.addEventListener('cart:change', render);
  render();

  /* ============ W106-E1 (Нильсен P0 «чекаут заперт в лендинге»):
     СВОДКА КОРЗИНЫ на /checkout.php ============
     Корзина живёт в localStorage → страницу рендерит JS. Блок-плейсхолдер
     печатает checkout.php (#checkoutSummary*), здесь — компактная сводка:
     товары/цены → промо (если применён в drawer, PROMO_STATE общий) →
     «Букеты» → «Доставка» (тариф выбранного района формы, порог бесплатной
     доставки — та же формула, что order-form.js selectedDeliveryPrice) →
     «Итого». Пересчёт: cart:change + делегированный change селекта района. */
  (function checkoutSummary() {
    const root = document.getElementById('checkoutSummary');
    if (!root) return;
    const itemsEl = document.getElementById('checkoutSummaryItems');
    const emptyEl = document.getElementById('checkoutSummaryEmpty');
    const promoRow = document.getElementById('checkoutSummaryPromoRow');
    const promoLabel = document.getElementById('checkoutSummaryPromoLabel');
    const promoValue = document.getElementById('checkoutSummaryPromoValue');
    const itemsTotalEl = document.getElementById('checkoutSummaryItemsTotal');
    const deliveryEl = document.getElementById('checkoutSummaryDelivery');
    const totalEl = document.getElementById('checkoutSummaryTotal');
    if (!itemsEl || !totalEl) return;

    function zonePrice() {
      const sel = document.getElementById('orderDeliveryZone');
      if (!sel) return null;
      const opt = sel.selectedOptions[0];
      let price = opt ? Number(opt.dataset.price) || 0 : 0;
      const cfg = window.NILOV_CONFIG || {};
      const threshold = Number(cfg.freeDeliveryThreshold) || 0;
      const items = window.cart ? window.cart.getTotal() : 0;
      if (threshold > 0 && items >= threshold && price > 0) price = 0;
      return { price: price, pickup: sel.value === '0' || sel.value === '' };
    }

    function renderSummary() {
      const items = window.cart ? window.cart.getItems() : [];
      if (items.length === 0) {
        itemsEl.innerHTML = '';
        if (emptyEl) emptyEl.hidden = false;
        if (promoRow) promoRow.hidden = true;
        if (itemsTotalEl) itemsTotalEl.textContent = '—';
        if (deliveryEl) deliveryEl.textContent = '—';
        totalEl.textContent = '—';
        return;
      }
      if (emptyEl) emptyEl.hidden = true;
      itemsEl.innerHTML = items.map(function (item) {
        return '<li class="checkout-summary__item">'
          + '<span class="checkout-summary__name">' + escapeHtml(item.name || 'Букет')
          + (item.qty > 1 ? ' × ' + item.qty : '') + '</span>'
          + '<span class="checkout-summary__price">' + formatPrice(item.price * item.qty) + '\u00A0₽</span>'
          + '</li>';
      }).join('');

      const itemsTotal = window.cart.getTotal();
      const promo = window.PROMO_STATE || {};
      const discount = (promo.code && Number(promo.discount) > 0) ? Number(promo.discount) : 0;
      if (promoRow) {
        if (discount > 0) {
          promoLabel.textContent = 'Промокод ' + promo.code;
          promoValue.textContent = '−' + formatPrice(discount) + '\u00A0₽';
          promoRow.hidden = false;
        } else {
          promoRow.hidden = true;
        }
      }
      if (itemsTotalEl) itemsTotalEl.textContent = formatPrice(itemsTotal) + '\u00A0₽';

      const z = zonePrice();
      if (deliveryEl) {
        if (z === null) deliveryEl.textContent = '—';
        else if (z.pickup) deliveryEl.textContent = 'Самовывоз · 0\u00A0₽';
        else deliveryEl.textContent = (z.price === 0 ? 'Бесплатно' : formatPrice(z.price) + '\u00A0₽');
      }
      const delivery = z ? z.price : 0;
      totalEl.textContent = formatPrice(Math.max(0, itemsTotal - discount + delivery)) + '\u00A0₽';
    }

    document.addEventListener('change', function (e) {
      if (e.target && e.target.id === 'orderDeliveryZone') renderSummary();
    });
    window.addEventListener('cart:change', renderSummary);
    /* G-g2: прямой доступ из промо-потока (применение кода меняет итог
       без события корзины) */
    window.nfRenderCheckoutSummary = renderSummary;
    renderSummary();
  })();

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
