/* Обработка формы заказа: сборка items[] из корзины + отправка без
   перезагрузки страницы. Контракт бэкенда:
   POST /api/orders  {name,phone,email,delivery_zone,delivery_address,
     payment_method,comment,pd_consent,items:[{product_id,qty}]}
   → 201 {id, paymentToken, redirectUrl?}  (total считает сервер)
   Если payment_method=online и есть redirectUrl — POST /api/payment/create
   {orderId, token} → {redirectUrl} → window.location = redirectUrl.

   A-b3 (волна критиков 0, UX P0):
   1) Телефон ОБЯЗАТЕЛЕН (раньше хватало email — заказ уходил без телефона,
      флорист не мог позвонить). Живая маска +7 (___) ___-__-__ при вводе,
      вставка из буфера не ломается (цифры извлекаются из любой раскладки:
      «+7 921 123-45-67», «89211234567», «9211234567» → одно и то же).
   2) Успех — модальная панель из JS (раньше единственный канал был
      редирект на /order-thanks; теперь — мгновенный видимый отклик
      с номером заказа + ссылка на детальную страницу).
   3) paymentMethod: понимает и новое имя name="paymentMethod"
      (radio/hidden, появится из PHP), и легаси name="payment_method". */
(function () {
  const form = document.getElementById('orderForm');
  if (!form) return;

  /* W98-fixF (F4): цель Метрики — guarded (счётчик выключен / не загружен
     после cookie-согласия → тихий пропуск). В параметры — только суммы, без ПД. */
  function nfGoal(name, params) {
    if (window.ym && window.__nfYmId) {
      try { ym(window.__nfYmId, 'reachGoal', name, params || undefined); } catch (e) { /* метрика не критична */ }
    }
  }

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

  /* A-b3 (1): телефон получателя — необязательное поле, для него остаётся
     свободный PHONE_RE (сервер так же). Основной телефон — строгая РФ-норма
     ниже (phoneBody): 10 цифр после нормализации кода страны +7/8. */
  const PHONE_RE = /^\+?[\d\s\-().]{7,20}$/;
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  /* ---------- A-b3 (1): телефон обязателен + живая маска ---------- */

  /* Цифры номера без кода страны: '+7 (921) 123-45-67' и '89211234567'
     → '9211234567'. Две ловушки разбора:
     1) В отформатированной строке первая семёрка — НАШ префикс «+7», а не
        цифра пользователя: после маскировки её надо исключить до всех
        остальных правил (иначе набор с «8» превращался в +7 (789…).
     2) Межгородный код 7/8 в начале СЫРЫХ цифр — съедается, но голый
        городской код (812-… — сам начинается с 8) не трогаем:
          • 7… — всегда код страны (местные с 7 не начинаются);
          • 89… — 8 + мобильный 9xx; 88… — 8 + городской 8xx (812/844/…):
            оба — межгород, первую цифру съедаем;
          • 81…/80… — голый городской код (812, 800-…) — это уже тело номера.
     Норма итога та же, что у серверного canonPhone (api/orders.php). */
  function phoneBody(value) {
    var s = String(value || '');
    var d = s.replace(/\D/g, '');
    if (d && s.charAt(0) === '+') d = d.slice(1); /* семёрка формата «+7» */
    if (d && d.charAt(0) === '7') d = d.slice(1); /* код страны */
    else if (d.length >= 2 && d.charAt(0) === '8' && (d.charAt(1) === '9' || d.charAt(1) === '8')) d = d.slice(1); /* 8 + 9xx/8xx */
    return d.slice(0, 10);
  }

  /* Прогрессивная маска: '+7 (921', '+7 (921) 123', … '+7 (921) 123-45-67' */
  function formatPhone(d) {
    if (!d) return '';
    let out = '+7 (' + d.slice(0, 3);
    if (d.length >= 4) out += ') ' + d.slice(3, 6);
    if (d.length >= 7) out += '-' + d.slice(6, 8);
    if (d.length >= 9) out += '-' + d.slice(8, 10);
    return out;
  }

  /* Живая маска с сохранением позиции курсора: сколько цифр было слева от
     курсора до правки — столько остаётся после. Вставка из буфера (input
     с готовой строкой) даёт digitsBefore = все цифры → курсор в конец
     отформатированного номера; правка в середине не прыгает в конец. */
  function bindPhoneMask(input) {
    if (!input) return;
    input.addEventListener('input', function () {
      const raw = input.value;
      const caret = input.selectionStart;
      let digitsBefore = caret == null ? 0 : raw.slice(0, caret).replace(/\D/g, '').length;
      /* семёрка «+7» — часть формата: в счётчике цифр слева от курсора
         ей места нет (иначе курсор уезжает на позицию правее) */
      if (raw.charAt(0) === '+' && digitsBefore > 0) digitsBefore -= 1;
      const body = phoneBody(raw);
      const masked = formatPhone(body);
      if (raw !== masked) input.value = masked;
      let pos = 0, seen = 0;
      const target = Math.min(digitsBefore, body.length);
      for (let i = 0; i < masked.length; i++) {
        if (/\d/.test(masked.charAt(i))) {
          seen++;
          if (seen === target) { pos = i + 1; break; }
        }
      }
      if (seen < target) pos = masked.length;
      if (target === 0) pos = 0;
      try { input.setSelectionRange(pos, pos); } catch (e) { /* tel-инпут без API выделения */ }
    });
  }
  bindPhoneMask(phoneInput);

  /* Подпись «Телефон» честно помечаем обязательной — как «Ваше имя *» в PHP.
     Только если звёздочки ещё нет (владелец мог вписать свою в настройке). */
  (function markPhoneRequired() {
    const label = document.querySelector('label[for="orderPhone"]');
    if (!label || (label.textContent || '').indexOf('*') !== -1) return;
    const star = document.createElement('span');
    star.textContent = ' *';
    star.style.cssText = 'color:var(--rose-cta,#AE4A71);font-weight:600';
    label.appendChild(star);
  })();

  /* H8 (W99-fixG2): скролл к первой ошибке — уважаем prefers-reduced-motion
     (как five.js/cart-ui.js): при reduce — мгновенный 'auto'. */
  function scrollBehavior() {
    return (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
      ? 'auto' : 'smooth';
  }
  function reducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  /* C-c4 (CRO P0 «тихий сабмит»): единый скролл к полю формы с компенсацией
     sticky-хедера — паттерн nfScrollToEl из five.js/cart-ui.js. НЮАНС (замер
     вживую): html{scroll-padding-top:96px/170px≤899px} (five.css:1280) — Lenis
     при element-target САМ прибавляет scroll-padding (поле ложилось на
     206/280px от верха при offset −110 — двойная компенсация). Поэтому в
     Lenis-пути offset:0 (итог 96/170 от верха — ниже хедера 73/145), а
     fallback-путь читает scroll-padding сам — посадка одинаковая. Бывший
     scrollIntoView({block:'center'}) в связке с Lenis давал рывок. */
  function headerScrollPadding() {
    var spt = 0;
    try { spt = parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0; } catch (e) { /* старые браузеры */ }
    return spt > 0 ? spt : 96;
  }
  function scrollToField(el, opts) {
    if (!el) return;
    opts = opts || {};
    var focused = false;
    function focusIt() {
      if (!opts.focus || focused || !el.isConnected) return;
      focused = true;
      try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
    }
    /* W106-E1 (Нильсен P0 «на мобиле автоскролла нет»): страховка кадра —
       если через 900мс после запуска скролла поле так и не оказалось
       во вьюпорте (Lenis-вариант не долетел / isStopped-скачок / плавный
       скролл перебит тачем), доворачиваем мгновенным window.scrollTo —
       «первая ошибка перед глазами» гарантирована на любом устройстве. */
    function ensureVisible() {
      if (!el.isConnected) return;
      var r = el.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight || 0;
      if (r.height > 0 && (r.top < 0 || r.bottom > vh)) {
        var t = r.top + (window.scrollY || window.pageYOffset) - headerScrollPadding();
        try { window.scrollTo({ top: Math.max(0, t), behavior: 'auto' }); } catch (e) { window.scrollTo(0, Math.max(0, t)); }
      }
      focusIt();
    }
    var lenis = window.NF_LENIS;
    if (lenis && !lenis.isStopped && typeof lenis.scrollTo === 'function') {
      lenis.scrollTo(el, { offset: 0, onComplete: focusIt });
      setTimeout(focusIt, 700); /* страховка: onComplete не гарантирован в старых сборках Lenis */
      setTimeout(ensureVisible, 900); /* W106-E1: мобильная страховка кадра */
      return;
    }
    var top = el.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) - headerScrollPadding();
    window.scrollTo({ top: Math.max(0, top), behavior: scrollBehavior() });
    setTimeout(focusIt, reducedMotion() ? 0 : 420);
    setTimeout(ensureVisible, 900); /* W106-E1: мобильная страховка кадра */
  }

  /* C-c4 (CRO P0): кнопка при N>1 ошибках на 1.5с сама говорит, сколько
     осталось («Заполните 2 поля») — клик больше не «мёртвый»; параллельно
     тихая aria-live сводка над кнопкой для скринридера (поля ошибок —
     aria-live по одному, сводка называет объём целиком). */
  var submitLive = null;
  var btnFlashTimer = 0;
  if (submitBtn) {
    submitLive = document.createElement('span');
    submitLive.id = 'orderSubmitLive';
    submitLive.setAttribute('role', 'status');
    submitLive.setAttribute('aria-live', 'polite');
    submitLive.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;'
      + 'padding:0;border:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap';
    submitBtn.parentNode.insertBefore(submitLive, submitBtn);
  }
  function pluralFields(n) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return 'поле';
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'поля';
    return 'полей';
  }
  function announceInvalid(count) {
    if (!submitLive) return;
    submitLive.textContent = count > 1
      ? 'Заполните ' + count + ' ' + pluralFields(count) + ', чтобы отправить заказ'
      : '';
  }
  function flashSubmitLabel(text) {
    if (!submitBtn) return;
    submitBtn.textContent = text;
    clearTimeout(btnFlashTimer);
    btnFlashTimer = setTimeout(function () { submitBtn.textContent = defaultSubmitLabel(); }, 1500);
  }

  /* Критик-мобайл (форма заказа, 8/10): поля и их подписи ошибок — списком,
     чтобы ошибка чистилась при вводе и вешался aria (баги 5,6).
     A-b3 (1): узел ошибки может отсутствовать в разметке — создаём из JS
     (span.order-form__error под полем), всем — aria-live="polite",
     чтобы скринридер слышал подсветку, а не только видел её. */
  const FIELD_PAIRS = [[nameInput, nameError], [phoneInput, phoneError], [emailInput, emailError],
    [deliveryAddressInput, deliveryAddressError], [pdConsentInput, pdConsentError],
    [recPhoneInput, recPhoneError]];

  function ensureErrorNode(inputEl) {
    let errEl = document.getElementById(inputEl.id + 'Error');
    if (errEl) return errEl;
    errEl = document.createElement('span');
    errEl.className = 'order-form__error';
    errEl.id = inputEl.id + 'Error';
    const field = inputEl.closest('.order-form__field') || inputEl.parentNode;
    if (field && field === inputEl.parentNode) field.appendChild(errEl);
    else if (inputEl.parentNode) inputEl.parentNode.appendChild(errEl);
    return errEl;
  }

  FIELD_PAIRS.forEach(function (pair) {
    if (!pair[0]) return;
    pair[1] = ensureErrorNode(pair[0]) || pair[1];
    if (pair[1]) pair[1].setAttribute('aria-live', 'polite');
  });

  function markInvalid(inputEl, errEl, msg) {
    if (!errEl) errEl = ensureErrorNode(inputEl);
    errEl.textContent = msg;
    if (inputEl) {
      inputEl.classList.add('order-form__input--error');
      inputEl.setAttribute('aria-invalid', 'true');
      inputEl.setAttribute('aria-describedby', errEl.id);
    }
  }
  function clearField(inputEl, errEl) {
    if (!errEl) errEl = ensureErrorNode(inputEl);
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

  /* ---------- A-b3 (6): paymentMethod — новое имя + легаси ---------- */
  /* DOM может дать radio/hidden name="paymentMethod" (появится из PHP) или
     легаси name="payment_method". Приоритет: отмеченный radio (любое имя) →
     hidden-инпут → 'cash'. Значения online|cash, прочее — 'cash'. */
  function chosenPaymentMethod() {
    const checked = form.querySelector('input[name="paymentMethod"]:checked')
      || form.querySelector('input[name="payment_method"]:checked');
    if (checked) return checked.value === 'online' ? 'online' : 'cash';
    const hidden = form.querySelector('input[type="hidden"][name="paymentMethod"], input[type="hidden"][name="payment_method"]');
    if (hidden && hidden.value === 'online') return 'online';
    return 'cash';
  }
  function wantsOnlinePayment() {
    return chosenPaymentMethod() === 'online';
  }

  function validate() {
    let valid = true;
    const firstInvalid = [];
    const name = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    const email = emailInput.value.trim();
    const body = phoneBody(phone);

    if (name.length < 2) {
      markInvalid(nameInput, nameError, 'Введите имя (минимум 2 символа)');
      firstInvalid.push(nameInput); valid = false;
    }

    /* A-b3 (1): телефон обязателен ВСЕГДА (email остаётся необязательным
       дополнением — для письма/чека). Было: «телефон ИЛИ email». */
    if (body.length === 0) {
      markInvalid(phoneInput, phoneError, 'Укажите телефон — мы позвоним для подтверждения заказа');
      firstInvalid.push(phoneInput); valid = false;
    } else if (body.length !== 10) {
      markInvalid(phoneInput, phoneError, 'Введите телефон полностью — например, +7 (999) 123-45-67');
      firstInvalid.push(phoneInput); valid = false;
    }

    if (email && !EMAIL_RE.test(email)) {
      markInvalid(emailInput, emailError, 'Введите корректный email');
      firstInvalid.push(emailInput); valid = false;
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
       должны привести покупателя к первой ошибке.
       C-c4: скролл — всегда, с offset sticky-хедера + фокус; N>1 ошибок —
       кнопка 1.5с несёт «Заполните N полей» + тихая aria-live сводка. */
    if (!valid && firstInvalid.length) {
      setStatus('', 'err');
      scrollToField(firstInvalid[0], { focus: true });
      if (firstInvalid.length > 1) {
        flashSubmitLabel('Заполните ' + firstInvalid.length + ' ' + pluralFields(firstInvalid.length));
      }
      announceInvalid(firstInvalid.length);
    }
    return valid;
  }

  /* Показ/обязательность поля адреса следует за выбором зоны; подсказка самовывоза —
     живёт только при самовывозе (критик-мобайл, баг 3: при доставке она противоречила).
     W106-E1 (Нильсен P0, три волны жалоб «адрес скрыт до выбора района»): поле
     адреса ВИДИМО ВСЕГДА — при самовывозе ПРИГЛУШЕНО (disabled + aria-disabled +
     плейсхолдер «Для самовывоза адрес не нужен», класс .order-form__field--muted),
     при выборе района — активно (плейсхолдер «Улица, дом, квартира»).
     Disabled-инпут не уходит на сервер — адрес самовывоза не «прилипает» к заказу.
     C-c4 (CRO P0): при ВЫБОРЕ района раньше поле ПОЯВЛЯЛОСЬ ниже вьюпорта —
     мягкий скролл к нему; теперь оно всегда на экране рядом с селектом — скролл
     нужен только если выскроллено за край. */
  function syncDeliveryAddressRequirement() {
    if (!deliveryAddressField) return;
    const delivery = wantsDelivery();
    if (deliveryAddressInput) {
      deliveryAddressInput.disabled = !delivery;
      deliveryAddressInput.required = delivery;
      deliveryAddressInput.setAttribute('aria-disabled', delivery ? 'false' : 'true');
      deliveryAddressInput.placeholder = delivery
        ? 'Улица, дом, квартира'
        : 'Для самовывоза адрес не нужен';
      if (!delivery && deliveryAddressInput.value) deliveryAddressInput.value = '';
    }
    deliveryAddressField.classList.toggle('order-form__field--muted', !delivery);
    const pickupHint = document.getElementById('orderPickupAddr');
    if (pickupHint) pickupHint.hidden = delivery;
    const dHint = document.getElementById('orderDeliveryHint');
    if (dHint) dHint.hidden = delivery;
  }
  if (deliveryZoneInput) {
    deliveryZoneInput.addEventListener('change', function () {
      const wasOff = deliveryAddressInput ? deliveryAddressInput.disabled : true;
      syncDeliveryAddressRequirement();
      /* поле стало активным, но осталось за краем экрана — мягко доворачиваем */
      if (wasOff && deliveryAddressInput && !deliveryAddressInput.disabled) {
        requestAnimationFrame(function () {
          var r = deliveryAddressField.getBoundingClientRect();
          if (r.top < 0 || r.bottom > (window.innerHeight || 0)) {
            scrollToField(deliveryAddressField, { focus: false });
          } else {
            try { deliveryAddressInput.focus({ preventScroll: true }); } catch (e) { /* инпут без фокус-API */ }
          }
        });
      }
    });
    deliveryZoneInput.addEventListener('change', renderTotal);
  }
  syncDeliveryAddressRequirement();

  /* ---------- C-c4 (CRO P1) → D2 (покупатель 55+ P0): чипы «Сегодня/Завтра» ----------
     90% доставок — сегодня или завтра; нативный пикер для этого не нужен.
     Чипы ставятся JS-вставкой (index.php — чужая зона): между label и input,
     стиль — глобальный .fc-chip (44px тач-зона, is-active). min/max поля
     уважаются (вне диапазона — disabled + title-подсказка); повторный клик
     по активному чипу снимает дату (она необязательна — паттерн ценовых
     чипов каталога).
     D2 (жалоба 55+ «первый клик по Завтра молча не сработал»), три защиты:
     1) idempotent-вставка — guard по id #orderDeliveryChips (повторный прогон
        модуля больше не плодит второй ряд чипов поверх первого);
     2) видимый отклик в ТОМ ЖЕ ТИКЕ, что и клик: активное состояние чипа —
        инлайн-стилями (bg/color/border), не только классом .is-active из
        five.css — файл правят параллельные агенты, inline гарантирует
        заливку независимо от внешнего CSS; дата в поле ставится ДО dispatch;
     3) второй сигнал — пульс рамки поля даты (1с): покупатель видит связь
        «клик → поле» даже если чип вне угла зрения.
     Плюс touch-action:manipulation — гасит 300мс double-tap-задержку iOS. */
  (function dateQuickChips() {
    const dateInput = document.getElementById('orderDeliveryDate');
    if (!dateInput || !dateInput.getAttribute('min')) return;
    const field = dateInput.closest('.order-form__field');
    const label = field ? field.querySelector('label') : null;
    if (!field || !label) return;
    if (document.getElementById('orderDeliveryChips')) return; /* idempotent- guard */

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
    const now = new Date();
    const tmr = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
    const days = [
      { label: 'Сегодня', val: ymd(now) },
      { label: 'Завтра', val: ymd(tmr) },
    ];
    const min = dateInput.getAttribute('min') || '';
    const max = dateInput.getAttribute('max') || '';

    const box = document.createElement('div');
    box.id = 'orderDeliveryChips';
    box.className = 'order-form__datechips';
    box.setAttribute('role', 'group');
    box.setAttribute('aria-label', 'Быстрая дата доставки');
    box.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap';

    const chipEls = [];
    days.forEach(function (d) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'fc-chip';
      /* база .fc-chip (five.css §W104-6) — каталожная ПЛИТКА 72px/радиус 20;
         в форме чип — компактная пилюля: переопределяем геометрию инлайном
         (редактируемого CSS на главной у этого агента нет), тач-зона 44px */
      b.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;'
        + 'flex:none;min-height:44px;padding:0 16px;border-radius:999px;'
        + 'touch-action:manipulation;'
        + 'transition:background .15s ease,color .15s ease,border-color .15s ease';
      b.textContent = d.label;
      b.setAttribute('aria-pressed', 'false');
      const outOfRange = (min && d.val < min) || (max && d.val > max);
      if (outOfRange) {
        b.disabled = true;
        /* 55+ P0 «молчаливый» клик по disabled-кнопке — хоть какая-то
           подсказка, почему чип не работает */
        b.setAttribute('title', 'Эта дата недоступна — выберите другую в календаре');
        b.style.opacity = '.45';
        b.style.cursor = 'not-allowed';
      } else {
        b.addEventListener('click', function () {
          /* дата — в поле НЕМЕДЛЕННО (до dispatch); повторный клик по активному
             чипу снимает дату — каждый клик меняет состояние видимо */
          const turnOn = dateInput.value !== d.val;
          dateInput.value = turnOn ? d.val : '';
          applyChipState(); /* активная заливка — в том же тике, что и клик */
          dateInput.dispatchEvent(new Event('input', { bubbles: true }));
          dateInput.dispatchEvent(new Event('change', { bubbles: true }));
          pulseDateField();
        });
      }
      chipEls.push({ btn: b, val: d.val });
      box.appendChild(b);
    });

    function applyChipState() {
      chipEls.forEach(function (c) {
        const on = dateInput.value === c.val;
        c.btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        c.btn.classList.toggle('is-active', on);
        /* инлайн-гарантия видимости активного чипа — поверх любых правок
           .fc-chip во внешних CSS (five.css правят параллельно) */
        c.btn.style.background = on ? 'var(--ink,#1c1a1e)' : '';
        c.btn.style.color = on ? '#fff' : '';
        c.btn.style.borderColor = on ? 'var(--ink,#1c1a1e)' : '';
      });
    }
    /* второй видимый сигнал: рамка поля даты вспыхивает на 1с (rose-токен
       ошибок формы — уже знакомый покупателю «взгляд сюда») */
    let pulseTimer = 0;
    function pulseDateField() {
      dateInput.style.transition = 'box-shadow .3s cubic-bezier(.4,0,.2,1)';
      dateInput.style.boxShadow = '0 0 0 3px rgba(174,74,113,.35)';
      clearTimeout(pulseTimer);
      pulseTimer = setTimeout(function () { dateInput.style.boxShadow = ''; }, 1000);
    }
    dateInput.addEventListener('input', applyChipState);
    dateInput.addEventListener('change', applyChipState);
    /* form.reset() (после успешного заказа) не шлёт input/change — чистим
       активный чип сами; reset-событие стреляет ДО очистки значений → rAF */
    form.addEventListener('reset', function () { requestAnimationFrame(applyChipState); });
    applyChipState();
    field.insertBefore(box, dateInput);
  })();

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

  /* ---------- A-b3 (2): модальная панель успеха (вместо тишины) ----------
     Рендер полностью из JS (index.php не трогаем): номер заказа из ответа
     API, обещание звонка, «На главную», тихая ссылка на детальную страницу
     /order-thanks (существующий замысел — сводка/шаги/отслеживание).
     a11y: role="dialog" + aria-modal, фокус-трап, Escape закрывает,
     фокус возвращается на кнопку отправки. prefers-reduced-motion — без
     анимаций (петал-бёрст гасится внутри NF_BURST.petals). */
  function showSuccessModal(order, opts) {
    opts = opts || {};
    const prevFocus = document.activeElement;
    const hasId = order && Number(order.id) > 0;
    const payFailed = !!opts.payFailed;

    const overlay = document.createElement('div');
    overlay.id = 'orderSuccessOverlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:150;display:grid;place-items:center;'
      + 'padding:20px;background:rgba(28,26,30,.55)';
    const dialog = document.createElement('div');
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', 'orderSuccessTitle');
    dialog.style.cssText = 'background:#fff;border:1.5px solid var(--line,#e7e5ea);border-radius:24px;'
      + 'max-width:440px;width:100%;padding:34px 30px 28px;text-align:center;'
      + 'box-shadow:0 24px 60px -34px rgba(28,26,30,.32);max-height:calc(100vh - 40px);overflow:auto';

    /* Галочка — визуальный язык order-thanks (.thanks-check): тёплый круг + розовый штрих */
    const check = document.createElement('span');
    check.setAttribute('aria-hidden', 'true');
    check.style.cssText = 'display:inline-grid;place-items:center;width:76px;height:76px;'
      + 'border-radius:50%;background:var(--surface-warm,#fbf5f8);margin-bottom:16px';
    check.innerHTML = '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" '
      + 'stroke="var(--pink,#ff4ea2)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">'
      + '<path d="M20 6L9 17l-5-5"/></svg>';
    dialog.appendChild(check);

    /* Заголовок: Playfair + курсив-акцент «Спасибо!» (паттерн order-thanks) */
    const title = document.createElement('h2');
    title.id = 'orderSuccessTitle';
    title.style.cssText = 'font-family:var(--font-display,Georgia,serif);font-weight:500;'
      + 'font-size:clamp(1.45rem,4vw,1.7rem);line-height:1.15;letter-spacing:-.015em;'
      + 'color:var(--ink,#1c1a1e);margin:0 0 10px';
    const accent = document.createElement('em');
    accent.style.cssText = 'font-style:italic;font-weight:600;color:var(--pink-deep,#cc2f7a)';
    accent.textContent = 'Спасибо!';
    title.appendChild(accent);
    title.appendChild(document.createTextNode(hasId
      ? ' Заказ № ' + order.id + ' принят'
      : ' Заказ принят')); /* honeypot-ответ API приходит без номера */
    dialog.appendChild(title);

    const promise = document.createElement('p');
    promise.style.cssText = 'font-size:1.05rem;margin:0 0 6px;color:var(--ink,#1c1a1e)';
    const strong = document.createElement('strong');
    strong.textContent = 'Мы позвоним в течение 15 минут для подтверждения';
    promise.appendChild(strong);
    dialog.appendChild(promise);

    if (payFailed) {
      const note = document.createElement('p');
      note.style.cssText = 'font-size:.9rem;margin:8px 0 0;color:var(--err,#C43A3A)';
      note.textContent = 'Не удалось перейти к онлайн-оплате'
        + (paymentError ? ': ' + paymentError : '') + '. Мы поможем оплатить по телефону.';
      dialog.appendChild(note);
    }

    const homeBtn = document.createElement('button');
    homeBtn.type = 'button';
    homeBtn.className = 'btn btn--accent';
    homeBtn.textContent = 'На главную';
    homeBtn.style.cssText = 'min-height:48px;width:100%;margin-top:22px';
    dialog.appendChild(homeBtn);

    /* Тихая ссылка на детальную страницу (существующий замысел order-thanks:
       сводка заказа, «Что дальше», отслеживание). Токен одноразовый — как и
       в прежнем редиректе, ссылка знает только создатель заказа. */
    let detailsLink = null;
    if (hasId) {
      detailsLink = document.createElement('a');
      detailsLink.href = '/order-thanks?id=' + encodeURIComponent(order.id)
        + (order.paymentToken ? '&t=' + encodeURIComponent(order.paymentToken) : '');
      detailsLink.textContent = 'Посмотреть детали заказа';
      detailsLink.style.cssText = 'display:inline-block;margin-top:14px;font-size:.875rem;'
        + 'color:var(--ink-muted,#6e6a72);text-underline-offset:3px';
      dialog.appendChild(detailsLink);
    }

    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    document.body.classList.add('no-scroll');
    document.documentElement.classList.add('no-scroll');

    /* Плавный вход — только без prefers-reduced-motion (статика при reduce) */
    if (!reducedMotion() && typeof overlay.animate === 'function') {
      overlay.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 180, easing: 'ease-out' });
      dialog.animate(
        [{ transform: 'translateY(14px)', opacity: 0 }, { transform: 'translateY(0)', opacity: 1 }],
        { duration: 260, easing: 'cubic-bezier(.22,1,.36,1)' }
      );
    }

    /* Петал-бёрст — «третье касание сигнатуры» (как на order-thanks);
       NF_BURST сам гасится при prefers-reduced-motion. */
    setTimeout(function () {
      if (!overlay.isConnected || !window.NF_BURST || typeof window.NF_BURST.petals !== 'function') return;
      const r = check.getBoundingClientRect();
      window.NF_BURST.petals({
        x: Math.round(r.left + r.width / 2),
        y: Math.round(r.top + r.height / 2),
        count: 10 + Math.round(Math.random() * 4),
        dist: [60, 150],
        fall: [120, 240],
        dur: [1100, 1400],
      });
    }, 500);

    function focusables() {
      const nodes = dialog.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])');
      return Array.prototype.filter.call(nodes, function (n) {
        return !n.disabled && n.offsetParent !== null;
      });
    }
    function closeModal() {
      document.removeEventListener('keydown', onKey, true);
      overlay.remove();
      document.body.classList.remove('no-scroll');
      document.documentElement.classList.remove('no-scroll');
      if (prevFocus && typeof prevFocus.focus === 'function') {
        try { prevFocus.focus({ preventScroll: true }); } catch (e) { prevFocus.focus(); }
      }
      /* C-c4 (CRO P1): после успеха — наверх и без якоря #order в URL:
         форма уже пуста (reset), «дозаполнить» её внизу — путаница;
         hash убираем replaceState'ом (без записи в историю) */
      if (window.location.hash) {
        try { history.replaceState(null, '', window.location.pathname + window.location.search); } catch (e) { /* file:// и пр. */ }
      }
      requestAnimationFrame(function () {
        const lenis = window.NF_LENIS;
        if (lenis && !lenis.isStopped && typeof lenis.scrollTo === 'function') {
          lenis.scrollTo(0);
        } else {
          window.scrollTo({ top: 0, behavior: scrollBehavior() });
        }
      });
    }
    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
      if (e.key !== 'Tab') return;
      const list = focusables();
      if (!list.length) return;
      const first = list[0], last = list[list.length - 1];
      const active = document.activeElement;
      if (!dialog.contains(active)) {
        e.preventDefault(); first.focus();
      } else if (e.shiftKey && active === first) {
        e.preventDefault(); last.focus();
      } else if (!e.shiftKey && active === last) {
        e.preventDefault(); first.focus();
      }
    }
    document.addEventListener('keydown', onKey, true);

    /* «На главную»: со вторичных страниц — переход на /; с главной (форма
       живёт здесь) — closeModal() уже поднимает страницу наверх и чистит
       hash (C-c4) — здесь остаётся только закрыть. */
    homeBtn.addEventListener('click', function () {
      const path = window.location.pathname;
      if (path !== '/' && path !== '/index.php') {
        window.location.assign('/');
        return;
      }
      closeModal();
    });

    requestAnimationFrame(function () { homeBtn.focus(); });
    return { close: closeModal };
  }

  /* Итоговая сумма к оплате (букеты + выбранная зона доставки) */
  const totalEl = document.getElementById('orderTotal');

  function formatRub(value) {
    /* W98-fixF (F6): тысячи — неразрывным пробелом U+00A0 (как PHP formatPrice) */
    return String(Math.round(value)).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');
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
        ? 'Добавьте ещё ' + formatRub(th - items) + '\u00A0₽ — и доставка станет бесплатной!'
        : hintEl.dataset.origText;
    }
    totalEl.textContent = delivery
      ? 'К оплате: ' + formatRub(items + delivery) + '\u00A0₽ (букеты ' + formatRub(items) + '\u00A0₽ + доставка ' + formatRub(delivery) + '\u00A0₽)'
      : 'К оплате: ' + formatRub(items) + '\u00A0₽' + (byThreshold ? ' — доставка бесплатная 🎉' : '');
    /* W97-fixA (A7): анонс «К оплате» в общий live-регион #nfSrLive
       (создаёт cart-ui.js; здесь — короткая версия без разбивки).
       Пишем только при изменении суммы — не спамим. */
    if (typeof window.nfAnnounce === 'function') {
      window.nfAnnounce('orderTotal', 'К оплате: ' + formatRub(items + delivery) + '\u00A0₽');
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

  /* W106-E1 (корректор P0): подпись Email — нейтральная строка без
     противоречия «обязательно для онлайн-оплаты» (оплаты нет). При реально
     включённой ЮKassa — текст о чеке + required. База — разметка partials/
     order-form.php (видимая строка), здесь — только синхронизация под способ
     оплаты; скрытая звёздочка #orderEmailReq удалена вместе с легаси-текстом. */
  const emailHint = document.getElementById('orderEmailHint');
  const EMAIL_HINT_NEUTRAL = 'Необязательно — пришлём фото букета и статус доставки';

  function syncEmailRequirement() {
    const online = wantsOnlinePayment();
    emailInput.required = online;
    if (emailHint) {
      emailHint.hidden = false;
      emailHint.textContent = online
        ? 'Обязателен для онлайн-оплаты — на этот адрес придёт чек'
        : EMAIL_HINT_NEUTRAL;
    }
    /* fix R2 + A-b3 (1): подсказка контактов не противоречит способу оплаты
       и обязательному телефону */
    const ch = document.getElementById('contactHint');
    if (ch) ch.textContent = online
      ? 'Телефон — для подтверждения заказа, email — для чека об онлайн-оплате'
      : 'Телефон обязателен — подтвердим заказ звонком; email — по желанию, пришлём фото букета и статус доставки';
  }

  /* A-b3 (6): слушаем смену способа оплаты по обоим именам инпутов */
  form.querySelectorAll('input[name="payment_method"], input[name="paymentMethod"]').forEach(function (el) {
    el.addEventListener('change', function () {
      submitBtn.textContent = defaultSubmitLabel();
      syncEmailRequirement();
    });
  });
  submitBtn.textContent = defaultSubmitLabel();
  syncEmailRequirement();

  /* C-c4 (CRO P0-2 «гарантия-возврат»): тихая строка гарантии сразу под
     кнопкой «Отправить заказ» — обещание, снимающее страх сабмита (не забирайте/
     соберём заново/вернём деньги). JS-вставка (index.php — чужая зона),
     без aria-live (не событие — постоянный контекст), мелкий muted = .order-form__hint. */
  (function guaranteeNote() {
    if (!submitBtn || document.getElementById('orderGuaranteeNote')) return;
    const p = document.createElement('p');
    p.className = 'order-form__hint';
    p.id = 'orderGuaranteeNote';
    p.style.cssText = 'margin:12px 0 0;max-width:60ch';
    p.textContent = 'Если букет не понравится при получении — не забирайте: соберём заново или вернём деньги. Замены согласуем до отправки.';
    submitBtn.insertAdjacentElement('afterend', p);
  })();

  /* C-c4 (редактор P1): honeypot «Сайт компании» — контейнер .hp-field уже
     несёт aria-hidden+inert, input — tabindex=-1+autocomplete=off (index.php,
     проверено вживую). Здесь — страховка: атрибуты переустанавливаются из JS,
     чтобы гарантия «label не читается скринридером» не зависела от правок
     разметки (aria-hidden на РОДИТЕЛЕ глушит всё поддерево). */
  (function hardenHoneypot() {
    const hp = form.querySelector('.hp-field');
    if (!hp) return;
    hp.setAttribute('aria-hidden', 'true');
    try { hp.inert = true; } catch (e) { /* старые браузеры без inert — хватит aria-hidden */ }
    const hpInput = hp.querySelector('input');
    if (hpInput) {
      hpInput.setAttribute('tabindex', '-1');
      hpInput.setAttribute('autocomplete', 'off');
    }
  })();

  /* W98-fixF (F4): begin_checkout — первое фокусирование формы (фокус любого
     поля, включая автофокус #orderName из drawer «Оформить заказ»). Один раз
     за сессию: флаг в sessionStorage (приват-режим → флаг в памяти страницы). */
  var beginCheckoutDone = false;
  try { beginCheckoutDone = sessionStorage.getItem('nfGoalBeginCheckout') === '1'; } catch (e) { /* sessionStorage недоступен */ }
  if (!beginCheckoutDone) {
    form.addEventListener('focusin', function () {
      if (beginCheckoutDone) return;
      beginCheckoutDone = true;
      try { sessionStorage.setItem('nfGoalBeginCheckout', '1'); } catch (e) { /* приват-режим: только память */ }
      nfGoal('begin_checkout');
    });
  }

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
          payment_method: chosenPaymentMethod(),
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
        /* Цели Яндекс.Метрики: отправка заказа (после согласия в cookie-баннере).
           ORDER_SUBMIT — историческая цель (сохранена); purchase — новая цель
           воронки W98-fixF (F4). Считаем ДО cart.clear(); параметры — только сумма.
           W100 (CRO-критик): выручка цели — как серверный total: букеты + доставка − промо. */
        const __promoDiscount = (window.PROMO_STATE && Number(window.PROMO_STATE.discount) > 0)
          ? Number(window.PROMO_STATE.discount) : 0;
        const orderTotal = Math.max(0, (window.cart && typeof window.cart.getTotal === 'function'
          ? window.cart.getTotal() : 0) + (selectedDeliveryPrice()) - __promoDiscount);
        if (window.ym && window.YM_COUNTER_ID) {
          try { ym(window.YM_COUNTER_ID, 'reachGoal', 'ORDER_SUBMIT', { order_price: orderTotal, currency: 'RUB' }); } catch (err) { /* метрика не критична */ }
        }
        nfGoal('purchase', { order_price: orderTotal, currency: 'RUB' });
        if (window.cart) window.cart.clear();
        /* Промокод одноразовый — после успешного заказа сбрасываем (server инкрементнул used) */
        if (window.PROMO_STATE) { window.PROMO_STATE.code = ''; window.PROMO_STATE.discount = 0; }
        form.reset();
        syncDeliveryAddressRequirement();
        syncEmailRequirement();

        /* Заказ уже принят и не потеряется — что бы дальше ни случилось
           с платежом, флорист его увидит и позвонит.
           Онлайн-оплата (существующий замысел): сначала платёж — ответ API
           может дать redirectUrl сразу, иначе создаём через /api/payment/create
           и уходим на ЮKassa. Оплата при получении: модальная панель успеха —
           номер заказа, обещание звонка, «На главную» + ссылка на детальную
           страницу /order-thanks (fix критика-покупателя: тишина после
           «Отправить заказ»). */
        if (wantsOnline) {
          if (created && created.redirectUrl) {
            window.location.assign(created.redirectUrl);
            return;
          }
          const redirected = await startPayment(created);
          if (redirected) return;
          /* Платёж не создался, но заказ принят — модалка с пометкой:
             поможем оплатить по телефону (раньше — только строка статуса). */
          try { showSuccessModal(created, { payFailed: true }); return; }
          catch (mErr) { /* страховка старых браузеров — прежний канал */ }
          setStatus('Заказ принят, но перейти к оплате не удалось' + (paymentError ? ': ' + paymentError + '.' : '.') + ' Мы свяжемся с вами и поможем оплатить.', 'err');
          return;
        }

        /* W106-E1 (Нильсен P0): на /checkout.php после успеха — РЕДИРЕКТ на
           /order-thanks с номером и токеном (токен уже в логике ответа API) —
           у чекаута своя страница подтверждения; на главной — прежняя
           модалка (покупатель остаётся на витрине). */
        const onCheckoutPage = /^\/checkout(\.php)?/i.test(window.location.pathname);
        if (onCheckoutPage && created && created.id) {
          window.location.assign('/order-thanks?id=' + encodeURIComponent(created.id)
            + (created.paymentToken ? '&t=' + encodeURIComponent(created.paymentToken) : ''));
          return;
        }

        try {
          showSuccessModal(created, {});
        } catch (mErr) {
          /* страховка: модалка не собралась — хотя бы статус-строка */
          setStatus('Заказ принят! Мы свяжемся с вами в ближайшее время.', 'ok');
        }
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
            contact_required: 'Укажите телефон — мы позвоним для подтверждения заказа.',
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
