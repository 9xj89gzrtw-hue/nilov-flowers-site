/* Reveal-анимация появления секций при скролле */
(function () {
  const targets = document.querySelectorAll('.reveal');
  if (!targets.length) return;

  const cssView = typeof CSS !== 'undefined' && CSS.supports
    && CSS.supports('animation-timeline', 'view()');
  if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    targets.forEach(function (el) { el.classList.add('reveal--visible'); });
    return;
  }
  /* W104-fix6 (C6-M6 P1.3): полный режим — scroll-driven CSS сам анимирует .reveal;
     IO-фолбэк здесь давал снап (класс добавлялся до/поверх таймлайна). IO остаётся
     только для сред без animation-timeline (старые браузеры, lite-режим снимает
     таймлайны классом html.motion-lite — тогда IO подхватывает, см. ниже). */
  if (cssView && !document.documentElement.classList.contains('motion-lite')) {
    return;
  }

  const io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) {
        e.target.classList.add('reveal--visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: .12 });

  targets.forEach(function (el) { io.observe(el); });
})();
