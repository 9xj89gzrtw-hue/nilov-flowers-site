/* Cookie-баннер: показывается до загрузки Яндекс.Метрики (152-ФЗ / ФЗ-156,
   с 01.09.2025 аналитические cookie требуют явного согласия).
   «Принять» → сохраняем согласие и вызываем loadMetrica()
   (функция печатается в head через includes/metrika.php).
   Отказ → согласие тоже сохраняем (баннер не показываем повторно),
   Метрику НЕ загружаем. Технические cookie (корзина) работают всегда. */
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
      '<p class="cookie-banner__text">Сайт использует cookie и Яндекс.Метрику для работы и анализа трафика. Подробнее — в <a href="/policy" target="_blank" rel="noopener">Политике обработки персональных данных</a>.</p>' +
      '<button type="button" class="btn cookie-banner__btn">Принять</button>' +
      '<button type="button" class="btn cookie-banner__btn cookie-banner__btn--secondary">Только необходимые</button>';

    document.body.appendChild(banner);

    var buttons = banner.querySelectorAll('.cookie-banner__btn');
    var accept = buttons[0];
    var reject = buttons[1];

    if (accept) {
      accept.addEventListener('click', function () {
        saveConsent();
        banner.remove();
        /* Метрика грузится только после явного согласия */
        if (typeof window.loadMetrica === 'function') {
          window.loadMetrica();
        }
      });
    }
    if (reject) {
      reject.addEventListener('click', function () {
        saveConsent();
        banner.remove();
        /* без loadMetrica() — аналитика не загружается */
      });
    }
  }

  if (!hasConsent()) {
    showBanner();
  }
})();
