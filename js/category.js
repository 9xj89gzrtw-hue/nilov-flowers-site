/* Фильтр по цене + сортировка на страницах категорий (W103 / F2).
   P0 двух критиков: «на /category нет ни фильтров, ни сортировки».

   Паттерн — как js/catalog-filter.js главной (НЕ редактируем его): клиентская
   работа по готовому DOM грида #catGrid. Карточки несут data-price (фактическая
   цена с учётом sale_price — рендерит render_product_card в category.php),
   data-hit (1 = «Хит»), класс .reveal (IntersectionObserver в reveal.js
   переживает перестановку appendChild — наблюдение живёт на элементе).

   Состояние: price = all|low|mid|high, sort = pop|asc|desc.
     • Диапазоны чипов — те же пороги, что у чипов главной (chips_price_low /
       chips_price_high): data-min ИСКЛЮЧИТЕЛЬНО («от M» = price > M),
       data-max ВКЛЮЧИТЕЛЬНО («до N» = price ≤ N) — семантика chipMatch()
       catalog-filter.js, диапазоны не пересекаются.
     • sort=pop — is_hit DESC, затем исходный порядок сервера (ORDER BY sort,id):
       стабильная сортировка по хранимому базовому индексу DOM.
     • URL-синхронизация ?price=…&sort=… (history.replaceState — без записей
       в истории) для шаринга/SEO-ссылок; при загрузке применяется из URL.
       Canonical страницы чистый (без query) — фильтрные URL не плодят дубли.
   Видимость карточек — класс .is-hidden (глобальный .product-card.is-hidden
   {display:none} из style.css, как у вкладок каталога главной). Слушатели
   cart-cta.js (прямые на кнопках «+») и nilov.js (сердечки) переживают
   appendChild: узлы перемещаются внутри того же #catGrid. */
(function () {
  'use strict';

  var toolbar = document.getElementById('catToolbar');
  var grid = document.getElementById('catGrid');
  if (!toolbar || !grid) return;

  var chipsBox = toolbar.querySelector('.cat-chips');
  var chips = Array.prototype.slice.call(toolbar.querySelectorAll('.cat-chip'));
  var sortBtns = Array.prototype.slice.call(toolbar.querySelectorAll('.cat-sort__btn'));
  var countEl = document.getElementById('catCount');
  var resetBtn = document.getElementById('catReset');
  var emptyBox = document.getElementById('catEmpty');
  var emptyResetBtn = document.getElementById('catEmptyReset');

  var cards = Array.prototype.slice.call(grid.querySelectorAll('.product-card'));
  if (!cards.length || !chips.length || !sortBtns.length) return;

  /* Базовый индекс = серверный порядок (sort, id) — тай-брейк всех сортировок */
  var baseIndex = new Map();
  cards.forEach(function (card, i) { baseIndex.set(card, i); });

  var PRICE_VALUES = ['all', 'low', 'mid', 'high'];
  var SORT_VALUES = ['pop', 'asc', 'desc'];
  var priceMode = 'all';
  var sortMode = 'pop';

  function plural(n) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return 'букет';
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'букета';
    return 'букетов';
  }

  function cardPrice(card) {
    return parseInt(card.getAttribute('data-price'), 10) || 0;
  }

  function cardHit(card) {
    return card.getAttribute('data-hit') === '1' ? 1 : 0;
  }

  function setPrice(v) {
    priceMode = PRICE_VALUES.indexOf(v) !== -1 ? v : 'all';
    chips.forEach(function (c) {
      var on = c.getAttribute('data-chip') === priceMode;
      c.classList.toggle('is-active', on);
      c.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function setSort(v) {
    sortMode = SORT_VALUES.indexOf(v) !== -1 ? v : 'pop';
    sortBtns.forEach(function (b) {
      var on = b.getAttribute('data-sort') === sortMode;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  /* URL: пишем только нестандартные значения, остальные query-параметры
     страницы (utm и пр.) не трогаем; hash сохраняем */
  function syncUrl() {
    try {
      var url = new URL(window.location.href);
      if (priceMode === 'all') url.searchParams.delete('price');
      else url.searchParams.set('price', priceMode);
      if (sortMode === 'pop') url.searchParams.delete('sort');
      else url.searchParams.set('sort', sortMode);
      window.history.replaceState(window.history.state, '', url.href);
    } catch (err) { /* старые браузеры — просто без шаринга */ }
  }

  function apply() {
    /* 1. Диапазон активного чипа (min — исключительно, max — включительно) */
    var chip = null;
    for (var i = 0; i < chips.length; i++) {
      if (chips[i].classList.contains('is-active')) { chip = chips[i]; break; }
    }
    var min = null, max = null;
    if (chip) {
      if (chip.hasAttribute('data-min')) min = parseInt(chip.getAttribute('data-min'), 10);
      if (chip.hasAttribute('data-max')) max = parseInt(chip.getAttribute('data-max'), 10);
    }

    /* 2. Сортировка ВСЕГО набора (скрытые тоже переставляем — порядок
          видимoй последовательности всегда детерминирован) */
    var ordered = cards.slice().sort(function (a, b) {
      var r = 0;
      if (sortMode === 'asc' || sortMode === 'desc') {
        r = sortMode === 'asc' ? cardPrice(a) - cardPrice(b) : cardPrice(b) - cardPrice(a);
      } else {
        r = cardHit(b) - cardHit(a); /* pop: сначала «Хиты» */
      }
      if (r !== 0) return r;
      return baseIndex.get(a) - baseIndex.get(b); /* стабильность: порядок сервера */
    });
    var fragment = document.createDocumentFragment();
    ordered.forEach(function (card) { fragment.appendChild(card); });
    grid.appendChild(fragment);

    /* 3. Видимость + счёт */
    var visible = 0;
    ordered.forEach(function (card) {
      var p = cardPrice(card);
      var ok = (min === null || p > min) && (max === null || p <= max);
      card.classList.toggle('is-hidden', !ok);
      if (ok) visible++;
    });

    /* «Показано N из M» — только при активном фильтре (сущность согласована
       с M; без фильтра — простой «M букетов») */
    if (countEl) {
      countEl.textContent = priceMode !== 'all'
        ? 'Показано ' + visible + ' из ' + cards.length
        : cards.length + ' ' + plural(cards.length);
    }

    /* Кнопка «Сбросить» — при любом нестандартном состоянии (цена/сортировка) */
    if (resetBtn) resetBtn.hidden = priceMode === 'all' && sortMode === 'pop';

    /* Пустое состояние фильтра */
    if (emptyBox) emptyBox.hidden = visible > 0;
    return visible;
  }

  function update() {
    apply();
    syncUrl();
  }

  function resetAll() {
    setPrice('all');
    setSort('pop');
    update();
  }

  /* ---- Чипы: активация; клик по активному диапазону — снятие (как на главной) ---- */
  if (chipsBox) {
    chipsBox.addEventListener('click', function (e) {
      var chip = e.target && e.target.closest ? e.target.closest('.cat-chip') : null;
      if (!chip) return;
      var v = chip.getAttribute('data-chip');
      if (v === priceMode && v !== 'all') v = 'all';
      setPrice(v);
      update();
    });
  }

  /* ---- Сортировка: радио-поведение ---- */
  sortBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      setSort(b.getAttribute('data-sort'));
      update();
    });
  });

  /* ---- Сброс (тулбар + пустое состояние) ---- */
  if (resetBtn) resetBtn.addEventListener('click', resetAll);
  if (emptyResetBtn) emptyResetBtn.addEventListener('click', resetAll);

  /* ---- Стартовое состояние из URL (?price=low|mid|high&sort=pop|asc|desc) ---- */
  var params = new URLSearchParams(window.location.search);
  setPrice(params.get('price'));
  setSort(params.get('sort'));
  apply();
})();
