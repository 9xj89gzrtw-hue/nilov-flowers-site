/* ХВОСТ РЯДА КАТАЛОГА (W105-6fix1, критик-арт P0 «сироты грида»).
   Дозаполняет последний ряд .catalog__grid (главная #catalogGrid и
   #catGrid категорий — один и тот же класс):
     • остаток 1  → последняя ВИДИМАЯ карточка получает .is-tail-feature
       (полноширинная горизонтальная feature-карточка, five.css W103-правила
       переключены с позиционных :nth-child на этот класс — nth-child считает
       и display:none, при фильтрации разметка попадала на скрытую карточку);
     • остаток ≥2 → CTA-плитка [data-grid-tail] («Соберём на заказ», рендерит
       index.php/category.php) занимает ровно недостающие колонки
       (grid-column: span N);
     • остаток 0 / 0 видимых → плитка скрыта.
   Вызовы: window.NfGridTail(grid) из apply() catalog-filter.js (главная) и
   category.js (категории), плюс собственный стартовый проход и resize
   (число колонок меняется брейкпоинтами). Тач-контрактов не касается. */
(function () {
  'use strict';

  function tail(grid) {
    if (!grid) return;
    var filler = grid.querySelector('[data-grid-tail]');
    if (!filler) return;
    /* категории: category.js переставляет карточки appendChild-ом в конец —
       держим плитку-хвост ПОСЛЕДНИМ ребёнком (порядок = порядок ряда) */
    if (grid.lastElementChild !== filler) grid.appendChild(filler);

    /* видимые карточки (инлайн display и .is-hidden пишут фильтры) */
    var cards = [];
    for (var i = 0; i < grid.children.length; i++) {
      var el = grid.children[i];
      if (el === filler || !el.classList.contains('product-card')) continue;
      if (el.classList.contains('is-hidden')) continue;
      if (el.style.display === 'none') continue;
      cards.push(el);
    }

    /* снять прошлую разметку фичи */
    for (var j = 0; j < cards.length; j++) cards[j].classList.remove('is-tail-feature');
    filler.style.display = '';
    filler.style.gridColumn = '';

    if (cards.length === 0) { filler.style.display = 'none'; return; }

    /* фактическое число колонок (сетка живёт брейкпоинтами five.css) */
    var cols = (getComputedStyle(grid).gridTemplateColumns || '').trim().split(/\s+/).filter(Boolean).length;
    if (!cols || cols < 1) return;

    var r = cards.length % cols;
    if (r === 0) {
      /* полные ряды — хвост не нужен */
      filler.style.display = 'none';
    } else if (r === 1) {
      /* классическая «вдова» → полноширинная feature-карточка */
      filler.style.display = 'none';
      cards[cards.length - 1].classList.add('is-tail-feature');
    } else {
      /* дозаполнить ряд CTA-плиткой ровно до края */
      filler.style.gridColumn = 'span ' + Math.max(1, cols - r);
    }
  }

  window.NfGridTail = tail;

  /* стартовый проход (категории: category.js defer — позовёт сам; главная:
     catalog-filter.js синхронный и уже отработал к этому моменту —
     повторный вызов идемпотентен) */
  function scan() {
    document.querySelectorAll('.catalog__grid').forEach(function (g) { tail(g); });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }

  /* смена числа колонок брейкпоинтами (debounce 120ms) */
  var rt = null;
  window.addEventListener('resize', function () {
    clearTimeout(rt);
    rt = setTimeout(scan, 120);
  }, { passive: true });
})();
