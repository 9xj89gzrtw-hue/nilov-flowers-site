/* Cookie-баннер: показывается до загрузки Яндекс.Метрики (152-ФЗ / ФЗ-156,
   с 01.09.2025 аналитические cookie требуют явного согласия).
   «Принять» → сохраняем согласие и вызываем loadMetrica()
   (функция печатается в head через includes/metrika.php).
   Отказ → согласие тоже сохраняем (баннер не показываем повторно),
   Метрику НЕ загружаем. Технические cookie (корзина) работают всегда. */
(function () {
  var STORAGE_KEY = 'cookieConsent';

  function readState() {
    /* Legal-критик: granular-консент — храним 'accept' | 'necessary', не булеву «1» */
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }
  function saveState(v) {
    try { localStorage.setItem(STORAGE_KEY, v); } catch (e) { /* сайт работает и без сохранения */ }
  }
  function dismissBanner() {
    var el = document.querySelector('.cookie-banner');
    if (el) el.remove();
    document.body.classList.remove('cookie-visible');
    /* W99-fixG2 (H1б/в): второй маркер открытого баннера — CSS поднимает
       sticky-CTA товара выше компактного мобильного баннера; снимается здесь. */
    document.body.classList.remove('nf-cookie-open');
    /* nilov.js install-hint: показывать только после решения по cookie (критик layout W35) */
    document.dispatchEvent(new Event('nf:cookie-done'));
  }
  function startAnalytics() {
    if (typeof window.loadMetrica === 'function') window.loadMetrica();
  }

  function showBanner() {
    var banner = document.createElement('div');
    banner.className = 'cookie-banner';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'Уведомление об использовании cookie');
    /* Тексты баннера редактируются (критерий 16): window.COOKIE_BANNER_CONFIG из PHP */
    var cfg = window.COOKIE_BANNER_CONFIG || {};
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var text = cfg.text || 'Сайт использует cookie и Яндекс.Метрику для работы и анализа трафика. Подробнее — в <a href="/policy" target="_blank" rel="noopener">Политике обработки персональных данных</a>.';
    banner.innerHTML =
      '<p class="cookie-banner__text">' + text + '</p>' +
      '<span class="cookie-banner__actions">' +
      '<button type="button" class="btn cookie-banner__btn">' + esc(cfg.accept || 'Принять') + '</button>' +
      '<button type="button" class="btn cookie-banner__btn cookie-banner__btn--secondary">' + esc(cfg.reject || 'Только необходимые') + '</button>' +
      '<button type="button" class="btn cookie-banner__btn cookie-banner__btn--ghost" id="cookieSettingsBtn">Настройки</button>' +
      '</span>';

    document.body.appendChild(banner);
    /* баг 8 (критик-мобайл): запас снизу, чтобы кнопка «Отправить» не лежала под баннером.
       W99-fixG2 (H1б): nf-cookie-open — маркер для CSS-подъёма sticky-CTA товара
       над компактным мобильным баннером (≤899px), снимается в dismissBanner(). */
    document.body.classList.add('cookie-visible');
    document.body.classList.add('nf-cookie-open');

    var buttons = banner.querySelectorAll('.cookie-banner__btn');
    var accept = buttons[0];
    var reject = buttons[1];
    var settingsBtn = document.getElementById('cookieSettingsBtn');

    /* W99-fixG2 (H1а): компактная мобильная версия (≤899px) — кнопки идут одним
       рядом «Принять · Настройки · Отклонить»: длинную дефолтную подпись отказа
       «Только необходимые» на мобиле укорачиваем до «Отклонить» (кастомный текст
       владелька не трогаем — CSS ограничит переполнение многоточием). */
    if (window.matchMedia && window.matchMedia('(max-width:899px)').matches
        && reject && (cfg.reject || 'Только необходимые') === 'Только необходимые') {
      reject.textContent = 'Отклонить';
    }

    if (accept) {
      accept.addEventListener('click', function () {
        saveState('accept');
        dismissBanner();
        startAnalytics();
      });
    }
    if (reject) {
      reject.addEventListener('click', function () {
        saveState('necessary');
        dismissBanner();
        /* без loadMetrica() — аналитика не загружается */
      });
    }
    /* Legal-критик: кнопка «Настройки cookie» — granular-выбор с чекбоксом аналитики */
    if (settingsBtn) {
      settingsBtn.addEventListener('click', function () {
        if (document.getElementById('cookieSettingsModal')) return;
        var modal = document.createElement('div');
        modal.id = 'cookieSettingsModal';
        modal.className = 'cookie-settings';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Настройки cookie');
        modal.innerHTML =
          '<div class="cookie-settings__panel">' +
          '<h3 style="margin:0 0 8px;font-size:1rem">Настройки cookie</h3>' +
          '<p style="margin:0 0 10px;font-size:.85rem;color:inherit;opacity:.8">Технические cookie (корзина, согласие) работают всегда — без них сайт не может. Аналитика включается только с вашего согласия.</p>' +
          '<label class="cookie-settings__row"><input type="checkbox" id="ckAnalytics"> Яндекс.Метрика (аналитика посещений)</label>' +
          '<div class="cookie-settings__btns">' +
          '<button type="button" class="btn cookie-banner__btn" id="ckSave">Сохранить</button>' +
          '<button type="button" class="btn cookie-banner__btn cookie-banner__btn--secondary" id="ckClose">Закрыть</button>' +
          '</div></div>';
        document.body.appendChild(modal);
        /* W85 (a11y-критик polish-2) → W97-fixA (A5): фокус-менеджмент role=dialog —
           перенос фокуса, возврат на триггер, Escape; фон — inert (Tab не выходит
           за пределы диалога: шапка/витрина/футер/таббар недоступны, пока открыты
           настройки; сам диалог — body-child вне списка). */
        var opener = document.activeElement;
        var inerted = [];
        Array.prototype.forEach.call(document.body.children, function (ch) {
          if (ch !== modal && !ch.inert) { ch.inert = true; inerted.push(ch); }
        });
        /* A5: полноценная Tab-ловушка. inert глушит фон, но Chrome на последнем
           элементе диалога может выкинуть фокус на <body> (транзит) — перехватываем
           Tab на уровне документа и возвращаем фокус внутрь диалога (цикл first↔last). */
        var trapTab = function (ev) {
          if (ev.key !== 'Tab') return;
          var nodes = modal.querySelectorAll('button, input, select, textarea, a[href], [tabindex]:not([tabindex="-1"])');
          var list = Array.prototype.filter.call(nodes, function (n) {
            return !n.disabled && (n.offsetParent !== null || n === document.activeElement);
          });
          if (!list.length) return;
          var first = list[0], last = list[list.length - 1];
          var active = document.activeElement;
          if (!modal.contains(active)) {
            ev.preventDefault();
            (ev.shiftKey ? last : first).focus();
          } else if (ev.shiftKey && active === first) {
            ev.preventDefault();
            last.focus();
          } else if (!ev.shiftKey && active === last) {
            ev.preventDefault();
            first.focus();
          }
        };
        document.addEventListener('keydown', trapTab);
        var closeSettings = function () {
          document.removeEventListener('keydown', onKey);
          document.removeEventListener('keydown', trapTab);
          inerted.forEach(function (ch) { ch.inert = false; });
          modal.remove();
          if (opener && opener.focus) opener.focus();
        };
        var onKey = function (ev) { if (ev.key === 'Escape') closeSettings(); };
        document.addEventListener('keydown', onKey);
        document.getElementById('ckSave').focus();
        var box = document.getElementById('ckAnalytics');
        document.getElementById('ckSave').addEventListener('click', function () {
          saveState(box.checked ? 'accept' : 'necessary');
          dismissBanner();
          closeSettings();
          if (box.checked) startAnalytics();
        });
        document.getElementById('ckClose').addEventListener('click', function () { closeSettings(); });
        modal.addEventListener('click', function (ev) { if (ev.target === modal) closeSettings(); });
      });
    }
  }

  /* «Настройки cookie» доступны и после выбора — маленькая ссылка в футере баннера
     не нужна: добавляем плавающую кнопку-переспрос через public API (window.cookieSettings) */
  window.cookieSettings = function () {
    saveState(null);
    var ex = document.querySelector('.cookie-banner');
    if (ex) ex.remove();
    showBanner();
  };

  var st = readState();
  if (st === '1') { saveState('accept'); st = 'accept'; } /* миграция старого формата */
  if (st === 'accept') { startAnalytics(); }
  if (st !== 'accept' && st !== 'necessary') {
    showBanner();
  }
})();
