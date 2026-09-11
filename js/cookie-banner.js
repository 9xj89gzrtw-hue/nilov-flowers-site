/* Cookie-баннер — простое одноразовое уведомление. Показывается один раз
   при первом визите, решение запоминается через localStorage. */
(function () {
  var STORAGE_KEY = 'cookieConsent';

  function hasConsent() {
    try {
      return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
      return true;
    }
  }

  function saveConsent() {
    try {
      localStorage.setItem(STORAGE_KEY, '1');
    } catch {
      /* сайт продолжает работать и без сохранения */
    }
  }

  function showBanner() {
    var banner = document.createElement('div');
    banner.className = 'cookie-banner';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'Уведомление об использовании cookie');
    banner.innerHTML =
      '<p class="cookie-banner__text">Сайт использует cookie для корректной работы. Подробнее — в <a href="/policy" target="_blank" rel="noopener">Политике обработки персональных данных</a>.</p>' +
      '<button type="button" class="btn cookie-banner__btn">Понятно</button>';

    document.body.appendChild(banner);

    banner.querySelector('.cookie-banner__btn').addEventListener('click', function () {
      saveConsent();
      banner.remove();
    });
  }

  if (!hasConsent()) {
    showBanner();
  }
})();
