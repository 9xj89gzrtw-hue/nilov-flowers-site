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
