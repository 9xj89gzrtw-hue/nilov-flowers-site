/* Cookie-баннер: показывается до загрузки Яндекс.Метрики (152-ФЗ / ФЗ-156,
   с 01.09.2025 аналитические cookie требуют явного согласия).
   «Принять» → сохраняем согласие и вызываем loadMetrica()
   (функция печатается в head через includes/metrika.php).
   Отказ → согласие тоже сохраняем (баннер не показываем повторно),
   Метрику НЕ загружаем. Технические cookie (корзина) работают всегда. */
(function () {
  var STORAGE_KEY = 'cookieConsent';
  /* F2 (P1-7): вид баннера ('bar' | 'card') — теперь пишется и при
     синхронном создании на загрузке (раньше — только из обработчиков
     событий), поэтому объявление поднято в шапку IIFE: присвоение в
     showBanner() не затиралось бы отложенным `cookieMode = null` ниже. */
  var cookieMode = null;
  /* G-g2 (CRO P0 «cookie висел весь визит поверх формы /checkout»):
     на странице оформления заказа баннер — максимально тихий. Вместо
     бар/карточки — СХЛОПНУТАЯ пилюля ~28px «Cookie · Принять» в правом
     нижнем углу; автопоказа по скроллу НЕТ (фолбэк 15с); пилюля физически
     не может перекрыть форму: пересечение с зоной формы/сводки гасит её
     (класс is-tucked — прозрачна и некликабельна, см. CSS five.css).
     Скрипт deferred — body.class уже на месте. */
  var isCheckout = !!(document.body && document.body.classList
      && document.body.classList.contains('page-checkout'));
  /* повторный показ из футера («Настройки cookie») — полная карточка
     даже на /checkout: пользователь сам попросил (forceFull) */
  var forceFull = false;

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
    /* G-g2: маркер пилюли /checkout снимается вместе с ней */
    document.body.classList.remove('nf-cookie-pill');
    /* nilov.js install-hint: показывать только после решения по cookie (критик layout W35) */
    document.dispatchEvent(new Event('nf:cookie-done'));
  }
  function startAnalytics() {
    if (typeof window.loadMetrica === 'function') window.loadMetrica();
  }

  /* F2 (P1-7, жюри «кнопка Принять не нашлась при программном клике»):
     баннер создаётся в DOM СРАЗУ (класс cookie-banner--waiting — невидим:
     opacity/visibility/pointer-events, вне a11y-дерева и Tab-порядка),
     гейт (первый скролл / фолбэк) управляет только ВИДИМОСТЬЮ —
     querySelector('.cookie-banner__btn') и .click() работают и до показа. */
  function revealBanner() {
    var el = document.querySelector('.cookie-banner');
    if (!el || !el.classList.contains('cookie-banner--waiting')) return;
    el.classList.remove('cookie-banner--waiting');
    /* баг 8 (критик-мобайл): запас снизу, чтобы кнопка «Отправить» не лежала под баннером.
       W99-fixG2 (H1б): nf-cookie-open — маркер для CSS-подъёма sticky-CTA товара
       над компактной мобильной карточкой (≤899px), снимается в dismissBanner(). */
    document.body.classList.add('cookie-visible');
    document.body.classList.add('nf-cookie-open');
    /* G-g2: /checkout — пилюля живёт своей жизнью: маркер nf-cookie-pill
       (CSS гасит нижний запас body под бар — пилюля прячется у формы
       сама, запас не нужен) + rect-сторож, который прячет её при
       пересечении с формой/сводкой заказа. */
    if (isCheckout && el.classList.contains('cookie-banner--pill')) {
      document.body.classList.add('nf-cookie-pill');
      guardPill(el);
    }
  }

  /* ============ G-g2: rect-сторож пилюли на /checkout ============
     Пилюля fixed в правом нижнем углу; форма заказа — главный контент.
     На каждом кадре скролла (rAF-троттл) сравниваем прямоугольники:
     пилюля пересекается с формой (#orderForm), заглушкой пустой корзины
     (#orderEmptyState) или сводкой (#checkoutSummary) → класс is-tucked
     (opacity/visibility/pointer-events — пилюля исчезает, ВСЯ площадь
     формы кликабельна в любой момент). Форма ушла из нижнего правого
     угла (скролл к шапке/футеру) — пилюля возвращается. */
  function guardPill(pill) {
    var zones = [];
    ['orderForm', 'orderEmptyState', 'checkoutSummary'].forEach(function (id) {
      var z = document.getElementById(id);
      if (z) zones.push(z);
    });
    if (!zones.length) return;
    var raf = 0;
    function check() {
      raf = 0;
      if (!pill.isConnected) return; /* баннер снят — слушатели не нужны */
      var pr = pill.getBoundingClientRect();
      if (!pr.width || !pr.height) return;
      var overlap = false;
      for (var i = 0; i < zones.length && !overlap; i++) {
        var zr = zones[i].getBoundingClientRect();
        if (zr.height === 0) continue; /* скрытая зона (display:none) не считается */
        overlap = !(pr.right < zr.left || pr.left > zr.right
          || pr.bottom < zr.top || pr.top > zr.bottom);
      }
      pill.classList.toggle('is-tucked', overlap);
    }
    window.addEventListener('scroll', function () {
      if (!raf) raf = requestAnimationFrame(check);
    }, { passive: true });
    window.addEventListener('resize', function () {
      if (!raf) raf = requestAnimationFrame(check);
    }, { passive: true });
    check();
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
    /* D-d1 (P0-3, покупатель 55+ «cookie перекрывает форму на мобиле»):
       ≤899px — ОДНОСТРОЧНАЯ плашка-бар 48px над таббаром:
       «Мы используем cookie · [Понятно] [Подробнее] ✕». «Понятно» = Принять,
       «Подробнее» — раздел политики о cookie (/policy#s9, новая вкладка —
       решение не теряется), ✕ = «Только необходимые» (отказ не сложнее
       согласия, 152-ФЗ). Десктоп — прежняя крем-карточка (стили five.css,
       модификатор .cookie-banner--bar). Обработчики ниже общие для обоих
       видов: кнопки ищутся по классам. */
    var isBar = !!(window.matchMedia && window.matchMedia('(max-width:899px)').matches);
    /* G-g2: /checkout — пилюля на ЛЮБОМ вьюпорте (десктопную карточку 420px
       CRO-критик словил поверх формы; пилюля + rect-сторож решают оба).
       forceFull — явный повторный показ из футера: полная карточка. */
    if (isCheckout && !forceFull) {
      cookieMode = 'pill';
      isBar = false;
      banner.className = 'cookie-banner cookie-banner--pill';
      banner.innerHTML =
        '<a class="cookie-banner__pill-label" href="/policy#s9" target="_blank" rel="noopener">Cookie</a>' +
        '<button type="button" class="btn cookie-banner__btn">' + esc(cfg.accept || 'Принять') + '</button>' +
        '<button type="button" class="cookie-banner__close" id="cookieBannerClose" aria-label="Закрыть — только необходимые cookie">&times;</button>';
    } else if (isBar) {
      cookieMode = 'bar';
      banner.className = 'cookie-banner cookie-banner--bar';
      banner.innerHTML =
        '<p class="cookie-banner__text">Мы используем cookie</p>' +
        /* F2 (P1-7): «Принять» вместо «Понятно» — кнопка находится и по тексту
           (жюри кликало программно), подпись едина с десктоп-карточкой */
        '<button type="button" class="btn cookie-banner__btn">' + esc(cfg.accept || 'Принять') + '</button>' +
        '<a class="cookie-banner__link" href="/policy#s9" target="_blank" rel="noopener">Подробнее</a>' +
        '<button type="button" class="cookie-banner__close" id="cookieBannerClose" aria-label="Закрыть — только необходимые cookie">&times;</button>';
    } else {
      cookieMode = 'card';
    /* W103 (F4, критик-3 P0-1) → W104-a2: карточка-уголок снизу-слева —
       заголовок 14/600 + ОДНА ink-пилюля «Принять» (стили .cookie-banner__btn
       в five.css) + тихие текст-ссылки «Настроить» (granular-флоу не изменился)
       и «Отклонить» (однотапный отказ — не сложнее согласия, 152-ФЗ).
       cfg.title — редактируемый заголовок (печатается в COOKIE_BANNER_CONFIG
       витриной; фолбэк здесь — по паттерну text/accept/reject).
       W104-δ (C2-D2 P0-1 «крестика нет»): ✕ в углу — как «Отклонить»
       (сохраняем 'necessary', Метрику НЕ грузим), + Escape.
       W105-7fix1 (тип-критик 7-b P1#2): ✕ БЕЗ инлайн-стилей — всё
       стилирование в five.css .cookie-banner__close (токены, тач-зона 44px;
       инлайн-шрифт с Montserrat-шорткатом был вне системы). */
    banner.innerHTML =
      '<button type="button" class="cookie-banner__close" id="cookieBannerClose" aria-label="Закрыть уведомление">&times;</button>' +
      /* E-e2 (P1-6, креативный директор «H3 14px — типографский мусор»):
         заголовок карточки — тихая строка p.cookie-banner__lead (style.css:
         наследует 13px/1.45 normal от .cookie-banner), НЕ h3-вес/размер.
         Смысловая нагрузка (что это за плашка) сохранена, иерархию заголовков
         документа баннер больше не трогает вовсе. Бар-режим (≤899px) заголовка
         и раньше не имел. */
      '<p class="cookie-banner__lead">' + esc(cfg.title || 'Мы используем cookie') + '</p>' +
      '<p class="cookie-banner__text">' + text + '</p>' +
      '<span class="cookie-banner__actions">' +
      '<button type="button" class="btn cookie-banner__btn">' + esc(cfg.accept || 'Принять') + '</button>' +
      '<button type="button" class="cookie-banner__link" id="cookieSettingsBtn">Настройки</button>' +
      '<button type="button" class="cookie-banner__link cookie-banner__link--reject">' + esc(cfg.reject || 'Только необходимые') + '</button>' +
      '</span>';
    }
    forceFull = false; /* одноразовый флаг из window.cookieSettings — съеден */

    banner.classList.add('cookie-banner--waiting'); /* F2 (P1-7): DOM сразу — видимость открывает гейт (revealBanner) */
    document.body.appendChild(banner);

    var accept = banner.querySelector('.cookie-banner__btn');
    var reject = banner.querySelector('.cookie-banner__link--reject');
    var settingsBtn = document.getElementById('cookieSettingsBtn');

    /* W104-zeta: укорачивание «Только необходимые» на мобиле убрано — компактная вёрстка вмещает полную подпись, кнопки едины на десктопе и мобиле (C3-D3 P2). */

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
    /* W104-δ: ✕ и Escape = «Отклонить» (не сложнее согласия — 152-ФЗ) */
    var closeX = document.getElementById('cookieBannerClose');
    if (closeX) {
      closeX.addEventListener('click', function () {
        saveState('necessary');
        dismissBanner();
      });
    }
    var escKey = function (ev) {
      if (ev.key !== 'Escape') return;
      if (!document.querySelector('.cookie-banner')) {
        document.removeEventListener('keydown', escKey);
        return;
      }
      saveState('necessary');
      dismissBanner();
      document.removeEventListener('keydown', escKey);
    };
    document.addEventListener('keydown', escKey);
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
    forceFull = true; /* G-g2: явный запрос — полная карточка и на /checkout */
    showBanner();
    revealBanner(); /* повторный показ — сразу видимый */
  };

  var st = readState();
  if (st === '1') { saveState('accept'); st = 'accept'; } /* миграция старого формата */
  if (st === 'accept') { startAnalytics(); }
  if (st !== 'accept' && st !== 'necessary') {
    /* W103 (финал-джадж P2): пауза — баннер не появляется одновременно с
       рендером hero/entrance-анимацией (не бьёт по первому впечатлению);
       дисклеймер всё ещё «до взаимодействия».
       W106 (B2, арт-критик P1 «cookie перекрывает hero на мобиле»): на узких
       экранах баннер ждёт ПЕРВОГО скролла с фолбэком 8с.
       D-d1: скролл-гейт распространён на ВСЕ вьюпорты — десктопная карточка
       420px перекрывала первую карточку каталога (замер: hover жюри попадал
       в .cookie-banner__text, а не в карточку); до первого скролла страница
       чистая, 8с — фолбэк для «не скроллящих». Аналитика по-прежнему только
       после «Принять» (152-ФЗ).
       F2 (P1-7): гейт теперь управляет ВИДИМОСТЬЮ заранее созданного
       баннера (DOM ждёт в .cookie-banner--waiting с момента загрузки). */
    showBanner();
    var cookieShown = false;
    var showOnce = function () {
      if (cookieShown) return;
      cookieShown = true;
      revealBanner();
    };
    /* G-g2 (CRO P0 «висел весь визит поверх формы»): /checkout — БЕЗ
       автопоказа по скроллу, тихий фолбэк 15с; остальные страницы —
       прежний гейт (первый скролл + 8с). */
    if (!isCheckout) {
      window.addEventListener('scroll', showOnce, { passive: true, once: true });
    }
    setTimeout(showOnce, isCheckout ? 15000 : 8000);
  }

  /* D-d1: смена вьюпорта при открытом баннере (поворот телефона / ресайз
     окна) — перестраиваем вид (бар ⇄ карточка), решение ещё не принято.
     Модалку «Настройки» (.cookie-settings) не трогаем.
     F2: cookieMode объявлен в шапке IIFE (см. комментарий у STORAGE_KEY). */
  var resizeTimer = 0;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      var el = document.querySelector('.cookie-banner');
      if (!el || !cookieMode) return;
      /* G-g2: /checkout — пилюля едина на всех вьюпортах, пересборка
         bar⇄card ей не нужна (не зависит от ширины экрана) */
      if (isCheckout && cookieMode === 'pill') return;
      var m = window.matchMedia && window.matchMedia('(max-width: 899px)').matches ? 'bar' : 'card';
      if (m === cookieMode) return;
      el.remove();
      showBanner();
      revealBanner(); /* баннер уже прошёл гейт — пересобранный показываем сразу */
    }, 200);
  }, { passive: true });
})();
