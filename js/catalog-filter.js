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
      t.setAttribute('aria-selected', 'false');
    });
    tab.classList.add('is-active');
    tab.setAttribute('aria-selected', 'true');

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
    var cards = document.querySelectorAll('.product-card[data-price]');
    var visible = 0;
    cards.forEach(function (c) {
      var price = parseInt(c.getAttribute('data-price'), 10) || 0;
      var ok = true;
      if (v === 'u2500') ok = price < 2500;
      else if (v === '2500-4000') ok = price >= 2500 && price <= 4000;
      else if (v === 'o4000') ok = price > 4000;
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
