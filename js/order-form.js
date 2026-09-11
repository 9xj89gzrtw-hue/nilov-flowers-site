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

  const nameError = document.getElementById('orderNameError');
  const phoneError = document.getElementById('orderPhoneError');
  const emailError = document.getElementById('orderEmailError');
  const pdConsentError = document.getElementById('orderPdConsentError');
  const statusEl = document.getElementById('orderStatus');
  const submitBtn = form.querySelector('.order-form__submit');

  const PHONE_RE = /^\+?[\d\s\-().]{7,20}$/;
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function clearErrors() {
    nameError.textContent = '';
    phoneError.textContent = '';
    emailError.textContent = '';
    pdConsentError.textContent = '';
    if (deliveryAddressError) deliveryAddressError.textContent = '';
    [nameInput, phoneInput, emailInput].forEach(function (el) {
      el.classList.remove('order-form__input--error');
    });
    if (deliveryAddressInput) deliveryAddressInput.classList.remove('order-form__input--error');
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
    const name = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    const email = emailInput.value.trim();

    if (name.length < 2) {
      nameError.textContent = 'Введите имя (минимум 2 символа)';
      nameInput.classList.add('order-form__input--error');
      valid = false;
    }

    if (!phone && !email) {
      phoneError.textContent = 'Укажите телефон или email';
      phoneInput.classList.add('order-form__input--error');
      valid = false;
    } else {
      if (phone && !PHONE_RE.test(phone)) {
        phoneError.textContent = 'Введите корректный номер телефона';
        phoneInput.classList.add('order-form__input--error');
        valid = false;
      }
      if (email && !EMAIL_RE.test(email)) {
        emailError.textContent = 'Введите корректный email';
        emailInput.classList.add('order-form__input--error');
        valid = false;
      }
    }

    /* Адрес обязателен, только когда выбрана реальная зона доставки */
    if (wantsDelivery() && deliveryAddressInput && !deliveryAddressInput.value.trim()) {
      if (deliveryAddressError) deliveryAddressError.textContent = 'Укажите адрес доставки';
      deliveryAddressInput.classList.add('order-form__input--error');
      valid = false;
    }

    if (!pdConsentInput.checked) {
      pdConsentError.textContent = 'Необходимо согласие на обработку персональных данных';
      valid = false;
    }

    return valid;
  }

  /* Показ/обязательность поля адреса следует за выбором зоны */
  function syncDeliveryAddressRequirement() {
    if (!deliveryAddressField) return;
    const delivery = wantsDelivery();
    deliveryAddressField.hidden = !delivery;
    if (deliveryAddressInput) deliveryAddressInput.required = delivery;
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

  function selectedDeliveryPrice() {
    if (!deliveryZoneInput) return 0;
    const option = deliveryZoneInput.selectedOptions[0];
    return option ? Number(option.dataset.price) || 0 : 0;
  }

  function renderTotal() {
    if (!totalEl) return;
    const items = window.cart ? window.cart.getTotal() : 0;
    if (!items) {
      totalEl.textContent = '';
      return;
    }
    const delivery = selectedDeliveryPrice();
    totalEl.textContent = delivery
      ? 'К оплате: ' + formatRub(items + delivery) + ' ₽ (букеты ' + formatRub(items) + ' ₽ + доставка ' + formatRub(delivery) + ' ₽)'
      : 'К оплате: ' + formatRub(items) + ' ₽';
  }

  if (totalEl) {
    window.addEventListener('cart:change', renderTotal);
    renderTotal();
  }

  /* Надпись на кнопке совпадает с тем, что произойдёт по нажатию */
  function defaultSubmitLabel() {
    return wantsOnlinePayment() ? 'Оплатить заказ' : 'Отправить заказ';
  }

  /* При онлайн-оплате на почту приходит чек — hint под полем */
  const emailHint = document.getElementById('orderEmailHint');

  function syncEmailRequirement() {
    const online = wantsOnlinePayment();
    emailInput.required = online;
    if (emailHint) emailHint.hidden = !online;
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
    statusEl.textContent = '';
    statusEl.className = 'order-form__status';

    if (!validate()) return;

    const items = buildItemsPayload();
    if (items.length === 0) {
      statusEl.textContent = 'Корзина пуста — добавьте букет из каталога';
      statusEl.classList.add('order-form__status--err');
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
        form.reset();
        syncDeliveryAddressRequirement();
        syncEmailRequirement();

        /* Заказ уже принят и не потеряется — что бы дальше ни случилось
           с платежом, флорист его увидит и позвонит */
        const redirected = wantsOnline ? await startPayment(created) : false;
        if (redirected) return;

        statusEl.textContent = wantsOnline
          ? 'Заказ принят, но перейти к оплате не удалось' + (paymentError ? ': ' + paymentError + '.' : '.') + ' Мы свяжемся с вами и поможем оплатить.'
          : 'Заказ принят! Мы свяжемся с вами в ближайшее время.';
        statusEl.classList.add(wantsOnline ? 'order-form__status--err' : 'order-form__status--ok');
      } else {
        const body = await res.json().catch(function () { return null; });
        if (body && body.item && window.cartUI) {
          window.cartUI.markUnavailable(body.item.product_id);
          statusEl.textContent = 'Один из товаров в заказе больше недоступен — уберите его из корзины (выделен красным) и попробуйте снова.';
        } else {
          statusEl.textContent = 'Ошибка при отправке. Пожалуйста, позвоните нам напрямую.';
        }
        statusEl.classList.add('order-form__status--err');
      }
    } catch {
      statusEl.textContent = 'Нет связи с сервером. Пожалуйста, позвоните нам напрямую.';
      statusEl.classList.add('order-form__status--err');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = defaultSubmitLabel();
    }
  });
})();
