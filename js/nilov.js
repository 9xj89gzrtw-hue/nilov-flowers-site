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

  /* 1b. WOW-интерактив: курсор-параллакс лепестков hero.
         --mx/--my в -1..1; CSS двигает .nv-petal--a/b. Троттл rAF. */
  var petals = document.querySelector('.nv-petals');
  if (petals && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var rafP = null;
    document.addEventListener('mousemove', function (e) {
      if (rafP) return;
      rafP = requestAnimationFrame(function () {
        rafP = null;
        petals.style.setProperty('--mx', ((e.clientX / window.innerWidth) * 2 - 1).toFixed(3));
        petals.style.setProperty('--my', ((e.clientY / window.innerHeight) * 2 - 1).toFixed(3));
      });
    }, { passive: true });
  }

  /* 1c. Скролл-параллакс букв бренда: --sy 0..1 (затухает к низу hero),
         .is-scrolling включает transform-смещение (см. nilov.css). */
  var brand = document.getElementById('nvBrand');
  if (brand && 'IntersectionObserver' in window) {
    var syRaf = null;
    var updateSy = function () {
      var r = brand.getBoundingClientRect();
      var heroH = window.innerHeight;
      var sy = Math.max(0, Math.min(1, (heroH - r.top) / heroH));
      brand.style.setProperty('--sy', sy.toFixed(3));
      if (sy > 0 && sy < 1) brand.classList.add('is-scrolling');
      else brand.classList.remove('is-scrolling');
    };
    window.addEventListener('scroll', function () {
      if (syRaf) return;
      syRaf = requestAnimationFrame(function () { syRaf = null; updateSy(); });
    }, { passive: true });
    updateSy();
  }

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
