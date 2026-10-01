/* S3 (v2026.3): «Купить в 1 клик» — модалка с двумя полями (имя + телефон),
   отправка заказа через POST /api/orders без перезагрузки. Работает с
   кнопками [data-oneclick] (карточки каталога/каруселей и PDP).
   КОНТРАКТ: /api/orders {name, phone, items:[{product_id, qty}],
   pd_consent:true, comment} → 201 {id}. Ошибки полей — inline под полями. */
(function () {
  'use strict';

  var modal = null, lastFocus = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function fmtPrice(v) {
    return String(Math.round(Number(v) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0') + '\u00A0₽';
  }

  function ensureModal() {
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'oneclick';
    modal.id = 'oneclickModal';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="oneclick__backdrop" data-oneclick-close></div>' +
      '<div class="oneclick__dialog" role="dialog" aria-modal="true" aria-labelledby="oneclickTitle">' +
      '  <button type="button" class="oneclick__close" data-oneclick-close aria-label="Закрыть">&times;</button>' +
      '  <p class="oneclick__eyebrow">Быстрый заказ</p>' +
      '  <p class="oneclick__title" id="oneclickTitle"></p>' +
      '  <div class="oneclick__product">' +
      '    <img class="oneclick__photo" alt="" width="72" height="96">' +
      '    <div class="oneclick__product-info"><span class="oneclick__name"></span><span class="oneclick__price"></span></div>' +
      '  </div>' +
      '  <p class="oneclick__note"></p>' +
      '  <form class="oneclick__form" id="oneclickForm" novalidate>' +
      '    <label><span>Ваше имя *</span><input type="text" name="name" maxlength="120" autocomplete="name" required></label>' +
      '    <label><span>Телефон *</span><input type="tel" name="phone" inputmode="tel" autocomplete="tel" placeholder="+7 (___) ___-__-__" required></label>' +
      '    <span class="oneclick__error" role="alert" aria-live="polite"></span>' +
      '    <button type="submit" class="btn btn--accent oneclick__submit"></button>' +
      '  </form>' +
      '  <div class="oneclick__success" hidden>' +
      '    <p class="oneclick__success-emoji" aria-hidden="true">🌸</p>' +
      '    <p class="oneclick__success-title">Заказ принят!</p>' +
      '    <p class="oneclick__success-text"></p>' +
      '    <button type="button" class="btn btn--outline" data-oneclick-close>Отлично</button>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(modal);

    modal.addEventListener('click', function (e) {
      if (e.target.closest('[data-oneclick-close]')) { close(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) { close(); }
    });
    modal.querySelector('#oneclickForm').addEventListener('submit', submit);
    return modal;
  }

  var current = null;

  function open(btn) {
    var m = ensureModal();
    current = {
      id: btn.getAttribute('data-product-id') || '',
      name: btn.getAttribute('data-product-name') || 'Букет',
      price: Number(btn.getAttribute('data-product-price-raw')) || 0,
      image: btn.getAttribute('data-product-image') || ''
    };
    m.querySelector('.oneclick__title').textContent = (window.NF_ONECLICK_TITLE || 'Купить в 1 клик');
    m.querySelector('.oneclick__name').textContent = current.name;
    m.querySelector('.oneclick__price').textContent = fmtPrice(current.price);
    var photo = m.querySelector('.oneclick__photo');
    if (current.image) { photo.src = current.image; photo.style.display = ''; }
    else { photo.style.display = 'none'; }
    m.querySelector('.oneclick__note').textContent = (window.NF_ONECLICK_NOTE || 'Перезвоним в течение 15 минут, подтвердим букет и доставку');
    m.querySelector('.oneclick__submit').textContent = (window.NF_ONECLICK_BTN || 'Оформить быстрый заказ');
    var form = m.querySelector('#oneclickForm');
    form.hidden = false;
    form.reset();
    m.querySelector('.oneclick__error').textContent = '';
    m.querySelector('.oneclick__success').hidden = true;
    m.hidden = false;
    document.body.classList.add('no-scroll');
    lastFocus = document.activeElement;
    setTimeout(function () { form.querySelector('input[name="name"]').focus(); }, 40);
  }

  function close() {
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('no-scroll');
    if (lastFocus && typeof lastFocus.focus === 'function') { lastFocus.focus(); }
    lastFocus = null;
  }

  function submit(e) {
    e.preventDefault();
    var err = modal.querySelector('.oneclick__error');
    err.textContent = '';
    var name = modal.querySelector('input[name="name"]').value.trim();
    var phone = modal.querySelector('input[name="phone"]').value.trim();
    if (name.length < 2) { err.textContent = 'Укажите имя — как к вам обращаться'; return; }
    if (!/^\+?[\d\s\-().]{7,20}$/.test(phone)) { err.textContent = 'Проверьте телефон — мы позвоним на него'; return; }
    if (!current || !current.id) { err.textContent = 'Букет не найден — обновите страницу'; return; }

    var btn = modal.querySelector('.oneclick__submit');
    btn.disabled = true;
    btn.textContent = 'Отправляем…';

    fetch('/api/orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: name,
        phone: phone,
        items: [{ product_id: Number(current.id), qty: 1 }],
        pd_consent: true,
        comment: 'Быстрый заказ в 1 клик — детали подтверждает флорист по телефону'
      })
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        btn.disabled = false;
        btn.textContent = (window.NF_ONECLICK_BTN || 'Оформить быстрый заказ');
        if (!res.ok || !res.j || !res.j.id) {
          var code = res.j && res.j.errors && res.j.errors[0] || 'error';
          err.textContent = code === 'rate_limited' ? 'Слишком много запросов — попробуйте через минуту или позвоните нам'
            : code === 'item' || code === 'price_invalid' ? 'Букет только что закончился — выберите другой в каталоге'
            : 'Не получилось отправить — проверьте связь или позвоните нам';
          return;
        }
        modal.querySelector('#oneclickForm').hidden = true;
        var suc = modal.querySelector('.oneclick__success');
        suc.hidden = false;
        suc.querySelector('.oneclick__success-text').textContent = 'Заказ №' + res.j.id + ' · ' + current.name + ' · ' + fmtPrice(current.price) + '. Перезвоним в течение 15 минут и всё подтвердим.';
        if (window.ym && window.__nfYmId) { try { ym(window.__nfYmId, 'reachGoal', 'oneclick_order'); } catch (er) {} }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = (window.NF_ONECLICK_BTN || 'Оформить быстрый заказ');
        err.textContent = 'Сеть недоступна — позвоните нам, примем заказ по телефону';
      });
  }

  /* Делегирование: карточки и карусели рендерит сервер, но PDP-кнопка и
     будущие динамические вставки тоже попадут. */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-oneclick]');
    if (btn && !btn.disabled) { e.preventDefault(); open(btn); }
  });
})();
