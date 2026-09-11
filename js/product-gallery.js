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
})();
