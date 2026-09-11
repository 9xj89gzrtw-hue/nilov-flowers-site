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

  /* 1d. PWA-подсказка установки (fix R2: «нет подсказки установить приложение»).
         iOS: честный текст-хинт на мобиле; Android: beforeinstallprompt → кнопка.
         Один показ на устройство (localStorage), закрыт — больше не показываем. */
  (function installHint() {
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (localStorage.getItem('nfInstallHintClosed') === '1') return;
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var isMobile = window.matchMedia('(max-width: 820px)').matches;
    if (!isMobile && !isIOS) return;
    var el = document.createElement('div');
    el.style.cssText = 'position:fixed;bottom:64px;left:12px;right:12px;z-index:95;background:#fff;border:1px solid rgba(43,45,47,.14);border-radius:14px;padding:12px 14px;font-size:.85rem;box-shadow:0 14px 40px -18px rgba(43,45,47,.4);display:flex;gap:10px;align-items:center';
    el.innerHTML = '<span style="flex:1">' + (isIOS
      ? 'Добавьте «Nilov Flowers» на главный экран — откройте меню «Поделиться» и выберите «На экран Домой».'
      : 'Установите «Nilov Flowers» как приложение — кнопка «Установить» справа.') + '</span>'
      + '<button type="button" style="border:none;background:var(--rose-deep,#E2799C);color:#fff;border-radius:999px;padding:8px 14px;font:600 .8rem sans-serif;cursor:pointer">Понятно</button>';
    el.querySelector('button').addEventListener('click', function () {
      localStorage.setItem('nfInstallHintClosed', '1');
      el.remove();
    });
    if (!isIOS && 'onbeforeinstallprompt' in window) {
      window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        var b = el.querySelector('button');
        b.textContent = 'Установить';
        b.onclick = function () { e.prompt(); localStorage.setItem('nfInstallHintClosed', '1'); el.remove(); };
      });
    }
    setTimeout(function () { document.body.appendChild(el); }, 2500);
  })();

  /* 2. Title-badge корзины — ВЫКЛЮЧЕН на витрине (владельцу не нравится «(1)» во вкладке).
     Счётчик остаётся только на бейдже иконки корзины + в админке (заказы). */
  function refresh() {}
  window.addEventListener('pageshow', refresh);
})();
