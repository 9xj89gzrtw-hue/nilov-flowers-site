/* S15: Цветочная подписка — модалка (нативный <dialog>): выбор тарифа,
   адрес в СПб, телефон. Сабмит: валидация → WhatsApp-рукопожатие
   (window.NF_SUBSCRIBE.wa из index.php; пусто — просто успех без перехода).
   Состояния: форма / ошибка / успех; повторное открытие сбрасывает на форму. */
(function () {
  var modal = document.getElementById('subscribeModal');
  var openBtn = document.getElementById('subscribeOpen');
  if (!modal || !openBtn || typeof modal.showModal !== 'function') return;

  var closeBtn = document.getElementById('subscribeModalClose');
  var form = document.getElementById('subscribeForm');
  var errEl = document.getElementById('subscribeFormError');
  var okEl = document.getElementById('subscribeOk');

  function reset() {
    if (errEl) errEl.hidden = true;
    if (okEl) okEl.hidden = true;
    if (form) { form.hidden = false; }
  }

  openBtn.addEventListener('click', function () {
    reset();
    try { modal.showModal(); } catch (e) { modal.setAttribute('open', ''); }
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () { modal.close(); });
  }
  modal.addEventListener('close', reset);

  /* клик по «подложке» (вне белой карточки) — закрыть: dialog сам занимает
     весь экран, box — только центр */
  modal.addEventListener('click', function (e) {
    if (e.target === modal) modal.close();
  });

  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(form);
    var tariff = String(fd.get('tariff') || '');
    var address = String(fd.get('address') || '').trim();
    var phone = String(fd.get('phone') || '').trim();
    var digits = phone.replace(/\D/g, '');

    var valid = address.length >= 5 && digits.length >= 10;
    if (errEl) errEl.hidden = valid;
    if (!valid) return;

    /* WhatsApp-рукопожатие: черновик заявки менеджеру */
    var cfg = window.NF_SUBSCRIBE || {};
    if (cfg.wa && /^\d{8,15}$/.test(String(cfg.wa))) {
      var text = 'Заявка на цветочную подписку.\nТариф: ' + tariff +
        '\nАдрес доставки (СПб): ' + address +
        '\nТелефон: ' + phone;
      try {
        window.open('https://wa.me/' + cfg.wa + '?text=' + encodeURIComponent(text), '_blank', 'noopener');
      } catch (e2) { /* блокировщик попапов — успех всё равно показываем */ }
    }

    form.hidden = true;
    if (okEl) okEl.hidden = false;
  });
})();
