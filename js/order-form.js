/* Обработка формы заказа: сборка items[] из корзины + отправка без
   перезагрузки страницы. Контракт бэкенда:
   POST /api/orders  {name,phone,email,delivery_zone,delivery_address,
     payment_method,comment,pd_consent,items:[{product_id,qty}]}
   → 201 {id, paymentToken, redirectUrl?}  (total считает сервер)
   Если payment_method=online и есть redirectUrl — POST /api/payment/create
   {orderId, token} → {redirectUrl} → window.location = redirectUrl. */
(function () {
  const form = document.getElementById('orderForm');
  if (!form) return;

  const nameInput = document.getElementById('orderName');
  const phoneInput = document.getElementById('orderPhone');
  const emailInput = document.getElementById('orderEmail');
  const commentInput = document.getElementById('orderComment');
  const deliveryZoneInput = document.getElementById('orderDeliveryZone');
  const deliveryAddressInput = document.getElementById('orderDeliveryAddress');
  const deliveryAddressField = document.getElementById('orderDeliveryAddressField');
  const deliveryAddressError = document.getElementById('orderDeliveryAddressError');
  const pdConsentInput = document.getElementById('orderPdConsent');
  /* Критик покупатель B1: телефон получателя валидируется и на клиенте */
  const recPhoneInput = document.getElementById('orderRecipientPhone');
  const recPhoneError = document.getElementById('orderRecipientPhoneError');

  const nameError = document.getElementById('orderNameError');
  const phoneError = document.getElementById('orderPhoneError');
  const emailError = document.getElementById('orderEmailError');
  const pdConsentError = document.getElementById('orderPdConsentError');
  const statusEl = document.getElementById('orderStatus');
  const submitBtn = form.querySelector('.order-form__submit');

  const PHONE_RE = /^\+?[\d\s\-().]{7,20}$/;
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  /* Критик-мобайл (форма заказа, 8/10): поля и их подписи ошибок — списком,
     чтобы ошибка чистилась при вводе и вешался aria (баги 5,6). */
  const FIELD_PAIRS = [[nameInput, nameError], [phoneInput, phoneError], [emailInput, emailError],
    [deliveryAddressInput, deliveryAddressError], [pdConsentInput, pdConsentError],
    [recPhoneInput, recPhoneError]];

  function markInvalid(inputEl, errEl, msg) {
    errEl.textContent = msg;
    if (inputEl) {
      inputEl.classList.add('order-form__input--error');
      inputEl.setAttribute('aria-invalid', 'true');
      inputEl.setAttribute('aria-describedby', errEl.id);
    }
  }
  function clearField(inputEl, errEl) {
    errEl.textContent = '';
    if (inputEl) {
      inputEl.classList.remove('order-form__input--error');
      inputEl.removeAttribute('aria-invalid');
      inputEl.removeAttribute('aria-describedby');
    }
  }
  FIELD_PAIRS.forEach(function (pair) {
    var i = pair[0], e2 = pair[1];
    if (!i || !e2) return;
    var onFix = function () { if (e2.textContent) clearField(i, e2); };
    i.addEventListener('input', onFix);
    i.addEventListener('change', onFix);
  });

  function clearErrors() {
    FIELD_PAIRS.forEach(function (pair) { clearField(pair[0], pair[1]); });
  }

  /* Единый показ статус-плашки: без текста плашка скрыта (баг 1 — пустая мятная полоса) */
  function setStatus(text, kind) {
    statusEl.textContent = text || '';
    statusEl.className = 'order-form__status' + (text ? ' order-form__status--' + kind : '');
    statusEl.hidden = !text;
  }

  /* Зона доставки выбрана и это НЕ самовывоз (value="0" = самовывоз, бесплатно).
     Поля зоны/адреса могут отсутствовать, если зон нет. */
  function wantsDelivery() {
    return Boolean(deliveryZoneInput && deliveryZoneInput.value !== '' && deliveryZoneInput.value !== '0');
  }

  function wantsOnlinePayment() {
    const chosen = form.querySelector('input[name="payment_method"]:checked');
    return Boolean(chosen) && chosen.value === 'online';
  }

  function validate() {
    let valid = true;
    const firstInvalid = [];
    const name = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    const email = emailInput.value.trim();

    if (name.length < 2) {
      markInvalid(nameInput, nameError, 'Введите имя (минимум 2 символа)');
      firstInvalid.push(nameInput); valid = false;
    }

    if (!phone && !email) {
      markInvalid(phoneInput, phoneError, 'Укажите телефон или email');
      firstInvalid.push(phoneInput); valid = false;
    } else {
      if (phone && !PHONE_RE.test(phone)) {
        markInvalid(phoneInput, phoneError, 'Введите корректный телефон — например, +7 (999) 123-45-67');
        firstInvalid.push(phoneInput); valid = false;
      }
      if (email && !EMAIL_RE.test(email)) {
        markInvalid(emailInput, emailError, 'Введите корректный email');
        firstInvalid.push(emailInput); valid = false;
      }
    }

    /* Адрес обязателен, только когда выбрана реальная зона доставки */
    if (wantsDelivery() && deliveryAddressInput && !deliveryAddressInput.value.trim()) {
      markInvalid(deliveryAddressInput, deliveryAddressError, 'Укажите адрес доставки');
      firstInvalid.push(deliveryAddressInput); valid = false;
    }

    if (!pdConsentInput.checked) {
      markInvalid(pdConsentInput, pdConsentError, 'Необходимо согласие на обработку персональных данных');
      firstInvalid.push(pdConsentInput); valid = false;
    }

    /* Критик покупатель B1: телефон получателя — необязательный, но если введён, формат проверяется
       ДО отправки (сервер вернёт recipient_phone, но без клиентской валидации поле не подсвечивалось) */
    if (recPhoneInput && recPhoneInput.value.trim() && !PHONE_RE.test(recPhoneInput.value.trim())) {
      markInvalid(recPhoneInput, recPhoneError, 'Введите корректный телефон получателя — например, +7 (999) 123-45-67');
      firstInvalid.push(recPhoneInput); valid = false;
    }

    /* Критик-мобайл (баг 2): на 390px поля с ошибками ~1200px выше кнопки —
     показывать статус не нужно (валидация подсвечена на полях), а фокус и скролл
     должны привести покупателя к первой ошибке. */
    if (!valid && firstInvalid.length) {
      setStatus('', 'err');
      const el = firstInvalid[0];
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(function () { try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); } }, 350);
    }
    return valid;
  }

  /* Показ/обязательность поля адреса следует за выбором зоны; подсказка самовывоза —
     живёт только при самовывозе (критик-мобайл, баг 3: при доставке она противоречила) */
  function syncDeliveryAddressRequirement() {
    if (!deliveryAddressField) return;
    const delivery = wantsDelivery();
    deliveryAddressField.hidden = !delivery;
    if (deliveryAddressInput) deliveryAddressInput.required = delivery;
    const pickupHint = document.getElementById('orderPickupAddr');
    if (pickupHint) pickupHint.hidden = delivery;
    const dHint = document.getElementById('orderDeliveryHint');
    if (dHint) dHint.hidden = !delivery;
  }
  if (deliveryZoneInput) {
    deliveryZoneInput.addEventListener('change', syncDeliveryAddressRequirement);
    deliveryZoneInput.addEventListener('change', renderTotal);
  }
  syncDeliveryAddressRequirement();

  /* Причина отказа в создании платежа, адресованная покупателю */
  let paymentError = '';

  /* Создаёт платёж по уже сохранённому заказу.
     @returns {Promise<boolean>} true — браузер уходит на платёжную страницу */
  async function startPayment(order) {
    if (!order || !order.id || !order.paymentToken) return false;
    try {
      const res = await fetch('/api/payment/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orderId: order.id, token: order.paymentToken }),
      });
      const payment = await res.json().catch(function () { return null; });
      if (!res.ok) {
        paymentError = payment && payment.error ? payment.error : '';
        return false;
      }
      if (payment && payment.redirectUrl) {
        window.location.assign(payment.redirectUrl);
        return true;
      }
    } catch {
      /* Платёж не создался — заказ уже принят, дальше по телефону */
    }
    return false;
  }

  /* Итоговая сумма к оплате (букеты + выбранная зона доставки) */
  const totalEl = document.getElementById('orderTotal');

  function formatRub(value) {
    return String(Math.round(value)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  /* W96-fix2 (F6): склонение минут для сообщения о паузе рейт-лимита
     (1 минуту / 2 минуты / 5 минут) */
  function pluralMinutes(n) {
    const m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return n + ' минуту';
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return n + ' минуты';
    return n + ' минут';
  }

  function selectedDeliveryPrice() {
    if (!deliveryZoneInput) return 0;
    const option = deliveryZoneInput.selectedOptions[0];
    let price = option ? Number(option.dataset.price) || 0 : 0;
    /* Порог бесплатной доставки (критерий 13/16): free_delivery_threshold из настроек
       (0 = выключено). Корзина ≥ порога → платная зона становится бесплатной. */
    const cfg = window.NILOV_CONFIG || {};
    const threshold = Number(cfg.freeDeliveryThreshold) || 0;
    const items = window.cart ? window.cart.getTotal() : 0;
    if (threshold > 0 && items >= threshold && price > 0) price = 0;
    return price;
  }

  function renderTotal() {
    if (!totalEl) return;
    const items = window.cart ? window.cart.getTotal() : 0;
    if (!items) {
      totalEl.textContent = '';
      return;
    }
    const delivery = selectedDeliveryPrice();
    /* Порог бесплатной доставки: подсказка-мотиватор (критерий 13).
       Оригинальный hint сохраняем один раз — восстанавливаем, когда условие неактивно.
       🎉 показываем ТОЛЬКО когда платная зона обнулилась порогом (не при самовывозе). */
    const cfg2 = window.NILOV_CONFIG || {};
    const th = Number(cfg2.freeDeliveryThreshold) || 0;
    const hintEl = document.getElementById('orderDeliveryHint');
    if (hintEl && !hintEl.dataset.origText) hintEl.dataset.origText = hintEl.textContent;
    const zoneOpt = deliveryZoneInput ? deliveryZoneInput.selectedOptions[0] : null;
    const zonePrice = zoneOpt ? Number(zoneOpt.dataset.price) || 0 : 0;
    const byThreshold = th > 0 && zonePrice > 0 && items >= th;
    if (hintEl) {
      hintEl.textContent = (th > 0 && items < th && zonePrice > 0)
        ? 'Добавьте ещё ' + formatRub(th - items) + ' ₽ — и доставка станет бесплатной!'
        : hintEl.dataset.origText;
    }
    totalEl.textContent = delivery
      ? 'К оплате: ' + formatRub(items + delivery) + ' ₽ (букеты ' + formatRub(items) + ' ₽ + доставка ' + formatRub(delivery) + ' ₽)'
      : 'К оплате: ' + formatRub(items) + ' ₽' + (byThreshold ? ' — доставка бесплатная 🎉' : '');
    /* W97-fixA (A7): анонс «К оплате» в общий live-регион #nfSrLive
       (создаёт cart-ui.js; здесь — короткая версия без разбивки).
       Пишем только при изменении суммы — не спамим. */
    if (typeof window.nfAnnounce === 'function') {
      window.nfAnnounce('orderTotal', 'К оплате: ' + formatRub(items + delivery) + ' ₽');
    }
  }

  if (totalEl) {
    window.addEventListener('cart:change', renderTotal);
    renderTotal();
  }

  /* Надпись на кнопке совпадает с тем, что произойдёт по нажатию.
     Базовые тексты приходят с сервера (критерий 16: правятся в админке) —
     data-pay-label / data-nopay-label; фолбэки — прежние хардкоды. */
  function defaultSubmitLabel() {
    var payLabel = submitBtn.getAttribute('data-pay-label') || 'Оплатить заказ';
    var nopayLabel = submitBtn.getAttribute('data-nopay-label') || 'Отправить заказ';
    return wantsOnlinePayment() ? payLabel : nopayLabel;
  }

  /* При онлайн-оплате на почту приходит чек — hint под полем */
  const emailHint = document.getElementById('orderEmailHint');

  function syncEmailRequirement() {
    const online = wantsOnlinePayment();
    emailInput.required = online;
    if (emailHint) emailHint.hidden = !online;
    const req = document.getElementById('orderEmailReq');
    if (req) req.hidden = !online; /* fix критика: обязательность email видна ДО сабмита */
    /* fix R2: подсказка контактов не противоречит способу оплаты */
    const ch = document.getElementById('contactHint');
    if (ch) ch.textContent = online
      ? 'Укажите телефон и email — на email придёт чек об онлайн-оплате'
      : 'Укажите телефон и/или email — как удобнее для связи';
  }

  form.querySelectorAll('input[name="payment_method"]').forEach(function (el) {
    el.addEventListener('change', function () {
      submitBtn.textContent = defaultSubmitLabel();
      syncEmailRequirement();
    });
  });
  submitBtn.textContent = defaultSubmitLabel();
  syncEmailRequirement();

  function buildItemsPayload() {
    if (!window.cart) return [];
    return window.cart.getItems().map(function (item) {
      return { product_id: Number(item.product_id), qty: item.qty };
    });
  }

  /* Отправка */
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    clearErrors();
    paymentError = '';
    setStatus('', 'err');

    if (!validate()) return;

    const items = buildItemsPayload();
    if (items.length === 0) {
      setStatus('Корзина пуста — добавьте букет из каталога', 'err');
      return;
    }

    const wantsOnline = wantsOnlinePayment();
    submitBtn.disabled = true;
    submitBtn.textContent = wantsOnline ? 'Переходим к оплате…' : 'Отправляем…';

    try {
      const res = await fetch('/api/orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: nameInput.value.trim(),
          phone: phoneInput.value.trim(),
          email: emailInput.value.trim(),
          delivery_zone: deliveryZoneInput ? deliveryZoneInput.value : '',
          delivery_address: deliveryAddressInput ? deliveryAddressInput.value.trim() : '',
          payment_method: wantsOnline ? 'online' : 'cash',
          comment: commentInput ? commentInput.value.trim() : '',
          /* Критик functional: gift-UX + слоты (пустые не шлём, сервер и так обрежет) */
          recipient_name: (document.getElementById('orderRecipientName') || {value:''}).value.trim(),
          recipient_phone: (document.getElementById('orderRecipientPhone') || {value:''}).value.trim(),
          card_text: (document.getElementById('orderCardText') || {value:''}).value.trim(),
          delivery_date: (document.getElementById('orderDeliveryDate') || {value:''}).value
            ? (function(){ var v=(document.getElementById('orderDeliveryDate')||{value:''}).value; var d=v.split('-'); return d.length===3? d[2]+'.'+d[1]+'.'+d[0] : v; })() : '',
          delivery_slot: (document.getElementById('orderDeliverySlot') || {value:''}).value,
          company_website: (document.getElementById('orderCompanyWebsite') || {value:''}).value,
          /* Промокод: отправляем только код — скидку сервер пересчитает сам */
          promo_code: (window.PROMO_STATE && window.PROMO_STATE.code) || '',
          pd_consent: pdConsentInput.checked,
          items: items,
        }),
      });

      if (res.ok) {
        const created = await res.json().catch(function () { return null; });
        /* Цель Яндекс.Метрики: отправка заказа (после согласия в cookie-баннере) */
        if (window.ym && window.YM_COUNTER_ID) {
          const orderTotal = window.cart && typeof window.cart.getTotal === 'function'
            ? (window.cart.getTotal() + (selectedDeliveryPrice())) : 0;
          try { ym(window.YM_COUNTER_ID, 'reachGoal', 'ORDER_SUBMIT', { order_price: orderTotal, currency: 'RUB' }); } catch (err) { /* метрика не критична */ }
        }
        if (window.cart) window.cart.clear();
        /* Промокод одноразовый — после успешного заказа сбрасываем (server инкрементнул used) */
        if (window.PROMO_STATE) { window.PROMO_STATE.code = ''; window.PROMO_STATE.discount = 0; }
        form.reset();
        syncDeliveryAddressRequirement();
        syncEmailRequirement();

        /* Заказ уже принят и не потеряется — что бы дальше ни случилось
           с платежом, флорист его увидит и позвонит.
           Онлайн-оплата: сначала платёж (redirect на ЮKassa), потом вернёмся.
           Оплата при получении: сразу на страницу «Спасибо, заказ №N» —
           там номер заказа и обещание звонка (fix критика-покупателя). */
        const redirected = wantsOnline ? await startPayment(created) : false;
        if (redirected) return;

        if (!wantsOnline && created && created.id) {
          window.location.assign('/order-thanks?id=' + encodeURIComponent(created.id));
          return;
        }

        setStatus(wantsOnline
          ? 'Заказ принят, но перейти к оплате не удалось' + (paymentError ? ': ' + paymentError + '.' : '.') + ' Мы свяжемся с вами и поможем оплатить.'
          : 'Заказ принят! Мы свяжемся с вами в ближайшее время.', wantsOnline ? 'err' : 'ok');
      } else {
        const body = await res.json().catch(function () { return null; });
        /* W96-fix2 (F6): 429/rate_limited — было «Не получилось оформить заказ.
           Позвоните нам…» (невнятно). Теперь честно: подождать N минут.
           N — из заголовка Retry-After (сервер отдаёт секунды → округляем
           в минуты); нет заголовка — честная оценка «обычно 5–10 минут». */
        const rateLimited = res.status === 429
          || (body && Array.isArray(body.errors) && body.errors.indexOf('rate_limited') !== -1)
          || (body && typeof body.error === 'string' && body.error.indexOf('rate_limited') !== -1);
        if (rateLimited) {
          let waitMin = 0;
          const retryAfter = parseInt(res.headers.get('Retry-After') || '', 10);
          if (!isNaN(retryAfter) && retryAfter > 0) waitMin = Math.max(1, Math.round(retryAfter / 60));
          setStatus(waitMin > 0
            ? 'Слишком много попыток оформить заказ. Подождите примерно ' + pluralMinutes(waitMin) + ' и попробуйте снова.'
            : 'Слишком много попыток оформить заказ. Подождите немного (обычно 5–10 минут) и попробуйте снова.', 'err');
        } else if (body && body.item && window.cartUI) {
          window.cartUI.markUnavailable(body.item.product_id);
          setStatus('Один из товаров в заказе больше недоступен — уберите его из корзины (выделен красным) и попробуйте снова.', 'err');
        } else if (body && Array.isArray(body.errors) && body.errors.length) {
          /* Человеческие тексты ошибок сервера (fix: молчаливые 400) */
          const map = {
            email_required_for_online: 'Для онлайн-оплаты укажите email — на него придёт чек.',
            contact_required: 'Укажите телефон или email, чтобы мы могли связаться с вами.',
            name: 'Введите имя (минимум 2 символа).',
            phone: 'Введите корректный телефон в формате +7 (999) 123-45-67.',
            email: 'Проверьте email — похоже, в нём опечатка.',
            delivery_address_required: 'Укажите адрес доставки (улица, дом, квартира).',
            delivery_zone_unavailable: 'Выбранный район доставки недоступен — выберите другой или самовывоз.',
            pd_consent_required: 'Отметьте согласие на обработку персональных данных.',
            empty: 'Корзина пуста — выберите букет в каталоге.',
            empty_cart: 'Корзина пуста — выберите букет в каталоге.',
            items_required: 'Корзина пуста — выберите букет в каталоге.',
            /* Критик покупатель B1: ключи gift/слотов/лимитов были без перевода → «позвоните нам» вместо подсказки */
            recipient_phone: 'Проверьте телефон получателя — например, +7 (999) 123-45-67.',
            item_qty_invalid: 'Количество товара должно быть от 1 до 99 — поправьте в корзине.',
            too_many_items: 'В заказе слишком много позиций — уменьшите корзину.',
            delivery_date_invalid: 'Дата доставки некорректна — выберите сегодня или ближайшие 60 дней.',
            /* W96-fix2 (F6): страховка — 429 без статуса (прокси/кэш) с errors:[rate_limited] */
            rate_limited: 'Слишком много попыток оформить заказ. Подождите немного (обычно 5–10 минут) и попробуйте снова.',
          };
          const parts = body.errors.map(function (code) { return map[code] || null; }).filter(Boolean);
          /* Подсветка поля получателя при серверной ошибке recipient_phone (если клиент её пропустил) */
          if (body.errors.indexOf('recipient_phone') !== -1 && recPhoneInput && recPhoneError) {
            markInvalid(recPhoneInput, recPhoneError, map.recipient_phone);
          }
          setStatus(parts.length
            ? parts.join(' ')
            : 'Не получилось оформить заказ. Позвоните нам — поможем оформить по телефону.', 'err');
        } else {
          setStatus('Ошибка при отправке. Пожалуйста, позвоните нам напрямую.', 'err');
        }
      }
    } catch {
      setStatus('Нет связи с сервером. Пожалуйста, позвоните нам напрямую.', 'err');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = defaultSubmitLabel();
    }
  });
})();
