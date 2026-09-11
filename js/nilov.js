/* Nilov Flowers — движок витринных анимаций W4.
   1) html.js-ready — ставит reveal.js/safety-net в рабочее состояние.
   2) Title-badge: счётчик корзины в названии вкладки «(2) … — Nilov Flowers».
   Никаких зависимостей. */
(function () {
  'use strict';

  /* 1. Сигнализируем, что JS жив: nilov.css safety-net
        (html:not(.js-ready)) перестаёт принудительно всё показывать. */
  var root = document.documentElement;
  root.classList.add('js-ready');

  /* 2. Title-badge корзины: «(N) Базовый title» */
  var baseTitle = null;
  function badge(n) {
    if (baseTitle === null) baseTitle = document.title;
    var m = baseTitle.match(/^\((\d+)\)\s*/);
    var clean = m ? baseTitle.slice(m[0].length) : baseTitle;
    document.title = n > 0 ? '(' + n + ') ' + clean : clean;
  }
  function count(items) {
    var n = 0;
    (items || []).forEach(function (i) { n += i.qty || 1; });
    return n;
  }
  function refresh() {
    try { badge(count(window.cart ? window.cart.getItems() : [])); } catch (e) { /* ignore */ }
  }
  window.addEventListener('cart:change', function (ev) { badge(count(ev.detail)); });
  window.addEventListener('pageshow', refresh);
  refresh();
})();
