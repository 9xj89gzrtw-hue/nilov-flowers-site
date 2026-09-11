/* Ленивая загрузка фото каталога ниже первого экрана — data-src -> src
   через IntersectionObserver, без библиотек. */
(function () {
  const targets = document.querySelectorAll('img[data-src]');
  if (!targets.length) return;

  function loadImage(img) {
    img.src = img.dataset.src;
    img.removeAttribute('data-src');
  }

  if (!('IntersectionObserver' in window)) {
    targets.forEach(loadImage);
    return;
  }

  const observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          loadImage(entry.target);
          observer.unobserve(entry.target);
        }
      });
    },
    { rootMargin: '200px 0px' }
  );

  targets.forEach(function (img) {
    observer.observe(img);
  });
})();
