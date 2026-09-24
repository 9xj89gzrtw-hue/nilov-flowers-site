/* five.js — витринная логика редизайна 5cv (W96 / T2-b).
   1) Город-бар: «Да, верно»/крестик → localStorage + скрытие.
   2) Карусели .fc-carousel: стрелки + disabled-состояния по скроллу.
   3) Чипы цен + поиск #fcSearch: фильтрация каталога ПОВЕРХ catalog-filter.js.
   Vanilla JS, без зависимостей. НЕ дублирует catalog-filter.js (вкладки категорий
   и select цены) — работает поверх него: скрытие через атрибут
   data-fc-filtered + CSS-правило [data-fc-filtered]{display:none!important},
   поэтому фильтрации комбинируются (категория И цена И чип И поиск) и не воюют
   за style.display. */
(function () {
  'use strict';

  /* Инжектим CSS-хук видимости (css/* трогать нельзя — это зона T2-a) */
  var css = document.createElement('style');
  css.textContent = '[data-fc-filtered="1"]{display:none!important}';
  document.head.appendChild(css);

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

  /* ---------- 3. Чипы цен + поиск: комбинированный фильтр каталога ---------- */
  function catalogFilters() {
    var grid = document.getElementById('catalogGrid');
    var form = document.querySelector('.fc-search');
    var chipsBox = document.querySelector('.fc-chips');
    if (!grid) {
      /* Вторичные страницы: каталога нет — поиск ведёт на главную к #catalog */
      if (form) form.action = '/#catalog';
      return;
    }
    var chips = chipsBox ? Array.prototype.slice.call(chipsBox.querySelectorAll('.fc-chip')) : [];
    var countEl = chipsBox ? chipsBox.querySelector('.fc-chips__count') : null;
    var emptyBox = document.getElementById('catalogEmpty');
    var emptyTitle = emptyBox ? emptyBox.querySelector('p') : null; /* первый <p> — заголовок empty */
    var inp = document.getElementById('fcSearch');
    var state = { chip: null, query: '' };

    /* Чип задаёт диапазон: data-min — исключительно («от M»), data-max — включительно («до N»).
       kind=hit/premium — по флагам карточки (data-hit / data-premium). */
    function chipMatch(card, chip) {
      var kind = chip.getAttribute('data-chip');
      if (kind === 'hit') return card.getAttribute('data-hit') === '1';
      if (kind === 'premium') return card.getAttribute('data-premium') === '1';
      var price = parseInt(card.getAttribute('data-price'), 10) || 0;
      var min = chip.hasAttribute('data-min') ? parseInt(chip.getAttribute('data-min'), 10) : null;
      var max = chip.hasAttribute('data-max') ? parseInt(chip.getAttribute('data-max'), 10) : null;
      return (min === null || price > min) && (max === null || price <= max);
    }

    /* Итоговая видимость карточки с учётом ВСЕХ фильтров страницы
       (категория is-hidden от catalog-filter.js, цена-select, чип, поиск, избранное). */
    function visibleCount() {
      var n = 0;
      grid.querySelectorAll('.product-card').forEach(function (c) {
        if (!c.hasAttribute('data-fc-filtered') && !c.classList.contains('is-hidden')
            && getComputedStyle(c).display !== 'none') n++;
      });
      return n;
    }

    function apply(scroll) {
      var q = state.query.trim().toLowerCase();
      var chipVisible = 0;
      grid.querySelectorAll('.product-card').forEach(function (card) {
        var ok = true;
        if (state.chip) {
          ok = chipMatch(card, state.chip);
          if (ok) chipVisible++;
        }
        if (ok && q) {
          var name = card.querySelector('.product-card__name');
          ok = !!name && name.textContent.toLowerCase().indexOf(q) !== -1;
        }
        if (ok) card.removeAttribute('data-fc-filtered');
        else card.setAttribute('data-fc-filtered', '1');
      });
      var total = visibleCount();
      /* Empty-state: 0 видимых карточек → подсказка + сброс */
      if (emptyBox) emptyBox.hidden = total > 0;
      /* «По запросу … не нашлось»: временная подмена заголовка, оригинал — в data-атрибуте */
      if (emptyTitle) {
        if (!emptyTitle.dataset.origTitle) emptyTitle.dataset.origTitle = emptyTitle.textContent;
        if (q && total === 0) {
          emptyTitle.textContent = 'По запросу «' + state.query.trim() + '» не нашлось';
        } else {
          emptyTitle.textContent = emptyTitle.dataset.origTitle;
        }
      }
      /* Счётчик у чипов: «N букетов» по активному чипу */
      if (countEl) countEl.textContent = state.chip ? chipVisible + ' ' + pluralBuket(chipVisible) : '';
      /* Плавный скролл к каталогу — только когда фильтруют чипом/поиском, не по сбросу */
      if (scroll) {
        var cat = document.getElementById('catalog');
        if (cat && cat.scrollIntoView) {
          cat.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth' });
        }
      }
    }

    /* Чипы: единственный активный; повторный клик — снять */
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var wasActive = chip.classList.contains('is-active');
        chips.forEach(function (c) {
          c.classList.remove('is-active');
          c.setAttribute('aria-pressed', 'false');
        });
        if (wasActive) {
          state.chip = null;
        } else {
          chip.classList.add('is-active');
          chip.setAttribute('aria-pressed', 'true');
          state.chip = chip;
        }
        apply(true);
      });
    });

    /* Поиск: Enter (submit) — фильтр по подстроке имени; пустое значение — сброс */
    if (form && inp) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        state.query = inp.value || '';
        apply(true);
      });
      /* Очистка крестиком type=search — сбрасываем сразу */
      inp.addEventListener('input', function () {
        if (inp.value === '' && state.query !== '') {
          state.query = '';
          apply(false);
        }
      });
    }

    /* Сброс фильтров (кнопка в empty-state): снимаем чип и поиск тоже
       (price/tabs/избранное сбрасывает catalog-filter.js своим обработчиком) */
    var reset = document.getElementById('catalogEmptyReset');
    if (reset) {
      reset.addEventListener('click', function () {
        state.chip = null;
        state.query = '';
        chips.forEach(function (c) {
          c.classList.remove('is-active');
          c.setAttribute('aria-pressed', 'false');
        });
        if (inp) inp.value = '';
        apply(false);
      });
    }
  }

  /* ---------- 4. «Смотреть все» у категорийных каруселей ----------
     data-tab={id}: включаем соответствующую вкладку каталога (её клик
     обработает catalog-filter.js), якорь #catalog срабатывает сам. */
  function rowLinks() {
    document.querySelectorAll('.fc-row__link[data-tab]').forEach(function (a) {
      a.addEventListener('click', function () {
        var tab = document.querySelector('.catalog-tabs__tab[data-category-id="' + a.getAttribute('data-tab') + '"]');
        if (tab) tab.click();
      });
    });
  }
})();
