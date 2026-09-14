/* Фильтрация каталога по категории — вкладки над сеткой (#catalogTabs) */
(function () {
  const tabs = document.getElementById('catalogTabs');
  const grid = document.getElementById('catalogGrid');
  if (!tabs || !grid) return;

  const cards = grid.querySelectorAll('.product-card');

  tabs.addEventListener('click', function (e) {
    const tab = e.target.closest('.catalog-tabs__tab');
    if (!tab) return;

    tabs.querySelectorAll('.catalog-tabs__tab').forEach(function (t) {
      t.classList.remove('is-active');
      t.setAttribute('aria-pressed', 'false');
    });
    tab.classList.add('is-active');
    tab.setAttribute('aria-pressed', 'true');

    const categoryId = tab.dataset.categoryId;
    cards.forEach(function (card) {
      const matches = categoryId === 'all' || card.dataset.categoryId === categoryId;
      card.classList.toggle('is-hidden', !matches);
    });
  });

  /* Deep-link: /?category=<id>#catalog */
  const categoryParam = new URLSearchParams(window.location.search).get('category');
  if (categoryParam) {
    const targetTab = Array.from(tabs.querySelectorAll('.catalog-tabs__tab')).find(function (t) {
      return t.dataset.categoryId === categoryParam;
    });
    if (targetTab) targetTab.click();
  }
})();

/* Фильтр по цене (критерий 13): комбинируется с табом категории.
   Читает data-price карточки, скрывает несоответствующие, пишет счётчик. */
(function () {
  var sel = document.getElementById('priceFilter');
  var count = document.getElementById('priceFilterCount');
  if (!sel) return;
  function apply() {
    var v = sel.value;
    var opt = sel.options[sel.selectedIndex];
    var min = opt.hasAttribute('data-min') ? parseInt(opt.getAttribute('data-min'), 10) : null;
    var max = opt.hasAttribute('data-max') ? parseInt(opt.getAttribute('data-max'), 10) : null;
    var cards = document.querySelectorAll('.product-card[data-price]');
    var visible = 0;
    cards.forEach(function (c) {
      var price = parseInt(c.getAttribute('data-price'), 10) || 0;
      /* Диапазоны из data-атрибутов опции (критерий 16: пороги задаются в админке) */
      var ok = (min === null || price >= min) && (max === null || price <= max);
      /* Комбинация с категорийным фильтром: карточка видима, если
         НЕ скрыта категорией (is-hidden от catalog-tabs) И проходит по цене. */
      var show = ok && !c.classList.contains('is-hidden');
      c.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (count) count.textContent = visible + ' ' + plural(visible);
  }
  function plural(n) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return 'букет';
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'букета';
    return 'букетов';
  }
  sel.addEventListener('change', apply);
  /* Пересчёт при смене категории (табы меняют is-hidden) */
  document.getElementById('catalogTabs')?.addEventListener('click', function (e) {
    if (e.target.closest('.catalog-tabs__tab')) setTimeout(apply, 0);
  });
  apply();
})();

/* Empty-state (критик P2): сводный пересчёт видимости после ЛЮБОЙ фильтрации
   (категория, цена, избранное). 0 карточек → #catalogEmpty. */
(function () {
  var emptyBox = document.getElementById('catalogEmpty');
  if (!emptyBox) return;

  function recount() {
    var cards = document.querySelectorAll('#catalogGrid .product-card');
    var visible = 0;
    cards.forEach(function (c) {
      if (getComputedStyle(c).display !== 'none' && !c.classList.contains('is-hidden')) visible++;
    });
    emptyBox.hidden = visible > 0;
  }

  /* Хук на все три источника изменений */
  document.getElementById('catalogTabs')?.addEventListener('click', function (e) {
    if (e.target.closest('.catalog-tabs__tab')) setTimeout(recount, 0);
  });
  var pf = document.getElementById('priceFilter');
  if (pf) pf.addEventListener('change', function () { setTimeout(recount, 50); });
  var ft = document.getElementById('favToggle');
  if (ft) ft.addEventListener('click', function () { setTimeout(recount, 50); });

  /* Кнопка сброса: цена → all, категория → «Все», избранное → выкл */
  document.getElementById('catalogEmptyReset')?.addEventListener('click', function () {
    var pf2 = document.getElementById('priceFilter');
    if (pf2) { pf2.value = 'all'; pf2.dispatchEvent(new Event('change', { bubbles: true })); }
    var allTab = document.querySelector('.catalog-tabs__tab[data-category-id="all"]');
    if (allTab && !allTab.classList.contains('is-active')) allTab.click();
    var ft2 = document.getElementById('favToggle');
    if (ft2 && ft2.classList.contains('is-on')) ft2.click();
    setTimeout(recount, 100);
  });

  /* Стартовый пересчёт после init других фильтров */
  setTimeout(recount, 250);
})();
