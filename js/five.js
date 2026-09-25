/* five.js — витринная логика редизайна 5cv (W96 / T2-b).
   1) Город-бар: «Да, верно»/крестик → localStorage + скрытие.
   2) Карусели .fc-carousel: стрелки + disabled-состояния по скроллу.
   3) Чипы цен + поиск #fcSearch: W97-fixA (A2/A3) — здесь ТОЛЬКО DOM-состояние
      (is-active у чипов, значение поля поиска) + вызов общего фильтра
      window.NfCatalogApply() из catalog-filter.js (единый источник истины:
      вкладка И цена И чип И поиск И избранное). Плюс пилюля «Нашлось N —
      посмотреть ↓» под строкой поиска (A3), автоскролл по ?q= и
      ре-применение фильтров на pageshow (bfcache/возврат «Назад»).
      W99-fixG2: H2 — пилюля слушает событие 'fc:filter' (сброс фильтров в
      catalog-filter.js тоже обновляет её); H3 — сердечко в шапке включает
      фильтр «Избранное»; H5 — «Нашёлся 1/21 букет».
   Vanilla JS, без зависимостей. */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }
  function reducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }
  /* Русское склонение: 1 букет / 2 букета / 5 букетов */
  function pluralBuket(n) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return 'букет';
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'букета';
    return 'букетов';
  }

  ready(function () {
    citybar();
    carousels();
    catalogFilters(); /* чипы + поиск + сброс — общее состояние (AND) */
    rowLinks();
    headerFavLink(); /* W99-fixG2 (H3): сердечко шапки → фильтр избранного */
  });

  /* ---------- 1. Город-бар: подтверждение города ---------- */
  function citybar() {
    var bar = document.getElementById('fcCitybar');
    if (!bar) return;
    function hide() {
      try { localStorage.setItem('fc-city-ok', '1'); } catch (e) { /* приват-режим: просто скрыть */ }
      bar.setAttribute('data-citybar-hidden', '');
      bar.style.display = 'none';
    }
    /* Уже отвечали — прячем сразу (миг на первом заходе допустим) */
    try {
      if (localStorage.getItem('fc-city-ok') === '1') { hide(); return; }
    } catch (e) { /* localStorage недоступен — бар остаётся до крестика */ }
    var yes = document.getElementById('fcCityYes');
    var close = document.getElementById('fcCityClose');
    if (yes) yes.addEventListener('click', hide);
    if (close) close.addEventListener('click', hide);
  }

  /* ---------- 2. Карусели: стрелки ← → + disabled по краям ---------- */
  function carousels() {
    var rows = document.querySelectorAll('.fc-row');
    Array.prototype.forEach.call(rows, function (row) {
      var track = row.querySelector('.fc-carousel');
      if (!track) return;
      var prev = row.querySelector('.fc-row__arrow:not(.fc-row__arrow--next)');
      var next = row.querySelector('.fc-row__arrow--next');
      function step(dir) {
        var delta = track.clientWidth * 0.8 * dir;
        var left = Math.max(0, Math.min(track.scrollWidth - track.clientWidth, track.scrollLeft + delta));
        if (typeof track.scrollTo === 'function') {
          track.scrollTo({ left: left, behavior: reducedMotion() ? 'auto' : 'smooth' });
        } else {
          track.scrollLeft = left; /* старые браузеры: без smooth */
        }
      }
      function sync() {
        var max = track.scrollWidth - track.clientWidth;
        if (prev) prev.disabled = track.scrollLeft <= 4;
        if (next) next.disabled = track.scrollLeft >= max - 4;
      }
      if (prev) prev.addEventListener('click', function () { step(-1); });
      if (next) next.addEventListener('click', function () { step(1); });
      track.addEventListener('scroll', sync, { passive: true });
      window.addEventListener('resize', sync);
      /* Стартовое состояние (карточки ленивые — перепроверяем после раскладки) */
      sync();
      setTimeout(sync, 300);
      setTimeout(sync, 1200);
    });
  }

  /* ---------- 3. Чипы цен + поиск: состояние + общий re-apply + пилюля ---------- */

  function catalogFilters() {
    var grid = document.getElementById('catalogGrid');
    var form = document.querySelector('.fc-search');
    var inp = document.getElementById('fcSearch');
    if (!grid) {
      /* Вторичные страницы: каталога нет — поиск уводит на главную с запросом
         (W96-fix1/F3: было пассивное form.action — теперь JS-редирект с #catalog;
         без JS срабатывает нативный submit action="/" method="get" с name="q") */
      if (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          var q = inp ? (inp.value || '').trim() : '';
          location.href = q !== '' ? '/?q=' + encodeURIComponent(q) + '#catalog' : '/#catalog';
        });
      }
      return;
    }
    var chipsBox = document.querySelector('.fc-chips');
    var chips = chipsBox ? Array.prototype.slice.call(chipsBox.querySelectorAll('.fc-chip')) : [];

    /* A3: пилюля под строкой поиска на главной — «Нашлось N букетов — посмотреть ↓».
       Появляется при непустом запросе, прячется при очистке; клик — плавный скролл
       к #catalog (каталог ниже первого экрана, живой фильтр его не видно). */
    var pill = null;
    function pillUpdate(visible) {
      var q = inp ? (inp.value || '').trim() : '';
      if (!q) {
        if (pill) pill.style.display = 'none';
        return;
      }
      if (visible == null) {
        /* страховка, если catalog-filter.js не загрузился: считаем видимые сами */
        visible = 0;
        grid.querySelectorAll('.product-card').forEach(function (c) {
          if (getComputedStyle(c).display !== 'none' && !c.classList.contains('is-hidden')) visible++;
        });
      }
      if (!pill) {
        pill = document.createElement('button');
        pill.type = 'button';
        pill.id = 'fcSearchPill';
        pill.style.cssText = 'position:absolute;top:calc(100% + 8px);left:0;z-index:70'
          + ';display:inline-flex;align-items:center;gap:4px;border:none;border-radius:999px'
          + ';padding:9px 16px;min-height:36px;background:var(--ink,#1c1a1e);color:#fff'
          + ';font-family:var(--font-ui,Montserrat,sans-serif);font-weight:600;font-size:.8rem'
          + ';cursor:pointer;box-shadow:0 12px 30px -12px rgba(28,26,30,.5);white-space:nowrap';
        pill.addEventListener('click', function () {
          var cat = document.getElementById('catalog');
          if (cat && cat.scrollIntoView) {
            cat.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth' });
          }
        });
        /* .fc-search уже position:relative (five.css) — пилюля висит под полем */
        (form || document.body).appendChild(pill);
      }
      pill.style.display = 'inline-flex';
      /* H5 (W99-fixG2): правильный род — «Нашёлся 1/21/31 букет»,
         «Нашлось 3 букета/11 букетов». */
      var verb = (visible % 10 === 1 && visible % 100 !== 11) ? 'Нашёлся' : 'Нашлось';
      pill.textContent = verb + ' ' + visible + ' ' + pluralBuket(visible) + ' — посмотреть ↓';
      pill.setAttribute('aria-label', verb + ' ' + visible + ' ' + pluralBuket(visible) + ' — перейти к каталогу букетов');
    }

    /* Единый re-apply: карточки/счётчики/empty-state пересчитывает catalog-filter.js;
      здесь обновляем только пилюлю (число видимых) и, при надобности, скроллим. */
    function refilter(scroll) {
      var visible = null;
      if (typeof window.NfCatalogApply === 'function') {
        visible = window.NfCatalogApply();
      }
      pillUpdate(visible);
      if (scroll) {
        var cat = document.getElementById('catalog');
        if (cat && cat.scrollIntoView) {
          cat.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth' });
        }
      }
    }

    /* H2 (W99-fixG2): пилюля обновляется ЛЮБЫМ apply() каталога, а не только
       нашими чипами/поиском. «Сбросить фильтры» в catalog-filter.js зовёт
       apply() напрямую (минуя five.js) — раньше пилюля со старым числом
       оставалась висеть. apply() рассылает 'fc:filter' с числом видимых —
       подписываемся и приводим пилюлю в актуальное состояние (при пустом
       запросе pillUpdate сам её прячет). */
    window.addEventListener('fc:filter', function (e) {
      var v = e && e.detail && typeof e.detail.visible === 'number' ? e.detail.visible : null;
      pillUpdate(v);
    });

    /* Чипы: единственный активный; повторный клик — снять. Сами карточки
       фильтрует NfCatalogApply (читает .fc-chip.is-active). */
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var wasActive = chip.classList.contains('is-active');
        chips.forEach(function (c) {
          c.classList.remove('is-active');
          c.setAttribute('aria-pressed', 'false');
        });
        if (!wasActive) {
          chip.classList.add('is-active');
          chip.setAttribute('aria-pressed', 'true');
        }
        refilter(true);
      });
    });

    /* Поиск (W96-fix1/F3 → W97-fixA A3): живая фильтрация с debounce 250мс —
       по мере ввода (пилюля с числом), Enter — применяет сразу и скроллит
       к каталогу; пустой запрос (в т.ч. крестик type=search) — снимает фильтр. */
    var debounceTimer = null;
    if (form && inp) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
        refilter(true);
      });
      inp.addEventListener('input', function () {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
          debounceTimer = null;
          refilter(false);
        }, 250);
      });
    }

    /* W96-fix1 (F3) → A3(б): перенос запроса со вторичных страниц — /?q=розы#catalog:
       заполняем поле и применяем фильтр сразу; ОДИН автоскролл к #catalog после
       инициализации (нативный hash-прыжок срабатывает до раскладки; smooth-скролл
       через 400мс гарантирует, что покупатель попал к отфильтрованному каталогу). */
    var qParam = new URLSearchParams(window.location.search).get('q');
    if (qParam && inp) {
      inp.value = qParam;
      refilter(false);
      setTimeout(function () {
        var cat = document.getElementById('catalog');
        if (cat && cat.scrollIntoView) {
          cat.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth' });
        }
      }, 400);
    }

    /* A3(в): desync «Назад» — браузер восстанавливает текст в поле поиска,
       а JS-фильтры сброшены (страница перегенерирована) → «роз» в поле, но 23
       карточки. pageshow (bfcache ИЛИ обычный показ) — пере-применяем фильтры
       из АКТУАЛЬНОГО значения поля. */
    window.addEventListener('pageshow', function () {
      if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
      refilter(false);
    });
  }

  /* ---------- 4. «Смотреть все» + ссылки каталога в футере ----------
     data-tab={id}: включаем соответствующую вкладку каталога (её клик
     обработает catalog-filter.js), якорь #catalog срабатывает сам.
     W96-fix1 (F7): data-chip={hit|premium|low} — «Смотреть все» у Хитов/
     Премиума/До N применяет одноимённый чип (полный путь клика по чипу:
     active + aria-pressed + фильтр + скролл; уже активный чип — только якорь).
     W96-fix3b (D2): делегирование по документу — работают ЛЮБЫЕ ссылки
     с data-tab/data-chip (карусели витрины И колонки футера). На вторичных
     страницах каталога нет: data-tab уводит на глубокую ссылку
     /?category=N#catalog (её подхватит catalog-filter.js), data-chip —
     просто к каталогу (нативный переход по href). */
  function rowLinks() {
    document.addEventListener('click', function (e) {
      if (e.button !== 0 || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest ? e.target.closest('a[data-tab],a[data-chip]') : null;
      if (!a) return;
      var tabId = a.getAttribute('data-tab');
      var chipId = a.getAttribute('data-chip');
      if (tabId) {
        var tab = document.querySelector('.catalog-tabs__tab[data-category-id="' + tabId + '"]');
        if (tab) {
          tab.click();
        } else {
          /* Вторичная страница: вкладки нет — глубокая ссылка включит её на главной */
          e.preventDefault();
          location.href = '/?category=' + encodeURIComponent(tabId) + '#catalog';
          return;
        }
      }
      if (chipId) {
        var chip = document.querySelector('.fc-chip[data-chip="' + chipId + '"]');
        if (!chip || chip.classList.contains('is-active')) return;
        chip.click();
      }
    });
  }

  /* ---------- 5. Сердечко в шапке → фильтр «Избранное» (W99-fixG2, H3) ----------
     Ссылка «Избранное — в каталоге» (href /#catalog) раньше просто прыгала к
     каталогу. На главной теперь: включаем тумблер «Избранное» (aria-pressed +
     .is-on — то же состояние, что ставит клик по самому тумблеру в nilov.js),
     применяем общие фильтры и плавно скроллим к #catalog. На вторичных
     страницах (нет #favToggle/#catalogGrid) — обычная навигация по href. */
  function headerFavLink() {
    var link = document.querySelector('.fc-header__icons a.fc-header__icon[href="/#catalog"]');
    if (!link) return;
    link.addEventListener('click', function (e) {
      if (e.button !== 0 || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var fav = document.getElementById('favToggle');
      var grid = document.getElementById('catalogGrid');
      if (!fav || !grid) return; /* не на главной — нативный переход по href */
      e.preventDefault();
      if (fav.getAttribute('aria-pressed') !== 'true') {
        fav.classList.add('is-on');
        fav.setAttribute('aria-pressed', 'true');
      }
      if (typeof window.NfCatalogApply === 'function') window.NfCatalogApply();
      var cat = document.getElementById('catalog');
      if (cat && cat.scrollIntoView) {
        cat.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth' });
      }
    });
  }
})();
