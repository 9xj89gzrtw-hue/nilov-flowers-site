/* Галерея на странице товара: переключение по свайпу (нативный
   scroll-snap) и по клику на миниатюру. Без автоплея. */
(function () {
  const track = document.getElementById('productGalleryTrack');
  if (!track) return;

  const slides = Array.from(track.querySelectorAll('.product-gallery__slide'));
  const thumbs = Array.from(
    document.querySelectorAll('#productGalleryThumbs .product-gallery__thumb')
  );
  const counter = document.getElementById('productGalleryCounter');
  if (slides.length < 1) return;

  function setActive(index) {
    if (counter) counter.textContent = index + 1 + ' / ' + slides.length;
    thumbs.forEach(function (thumb, i) {
      if (i === index) thumb.setAttribute('aria-current', 'true');
      else thumb.removeAttribute('aria-current');
    });
  }

  function currentIndexFromScroll() {
    const slideWidth = track.clientWidth;
    if (!slideWidth) return 0;
    return Math.round(track.scrollLeft / slideWidth);
  }

  let scrollTimer = null;
  track.addEventListener('scroll', function () {
    if (scrollTimer) clearTimeout(scrollTimer);
    scrollTimer = setTimeout(function () {
      setActive(currentIndexFromScroll());
    }, 100);
  });

  thumbs.forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      const index = Number(thumb.dataset.index);
      track.scrollTo({ left: index * track.clientWidth, behavior: 'smooth' });
      setActive(index);
    });
  });

  setActive(0);

  /* Визуал-критик W52: desktop-аффорданс — стрелки и колесо мыши листают слайды */
  function go(delta) {
    var idx = Math.min(slides.length - 1, Math.max(0, currentIndexFromScroll() + delta));
    track.scrollTo({ left: idx * track.clientWidth, behavior: 'smooth' });
    setActive(idx);
  }
  document.querySelectorAll('[data-gnav]').forEach(function (btn) {
    btn.addEventListener('click', function () { go(Number(btn.dataset.gnav)); });
  });
  var wheelLock = 0;
  track.addEventListener('wheel', function (e) {
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) return; /* вертикаль страницы не перехватываем */
    e.preventDefault();
    var now = Date.now();
    if (now - wheelLock < 450) return;
    wheelLock = now;
    go(e.deltaX > 0 ? 1 : -1);
  }, { passive: false });
})();
