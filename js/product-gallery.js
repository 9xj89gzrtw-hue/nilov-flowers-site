/* Галерея на странице товара: переключение по свайпу (нативный
   scroll-snap) и по клику на миниатюру. Без автоплея.
   W97-fixB3b (B3b-2f): zoom-слайд не несёт background-image в HTML — .jpg
   грузился сразу вторым дублем вместе с LCP-фото. Фон (webp-URL из
   data-gallery-zoom, печатает product.php) подставляем лениво — только
   когда этот слайд стал активным (стрелки/свайп/миниатюра).
   W99-fixG2: H4 — стрелки дизейблятся на краях (первый слайд → prev
   disabled, последний → next disabled); H12 — визуально-скрытый счётчик
   aria-live=polite «Слайд N из M» (в DOM счётчика нет — PHP не трогаем,
   создаём из JS и обновляем при смене слайда). */
(function () {
  const track = document.getElementById('productGalleryTrack');
  if (!track) return;

  const slides = Array.from(track.querySelectorAll('.product-gallery__slide'));
  const thumbs = Array.from(
    document.querySelectorAll('#productGalleryThumbs .product-gallery__thumb')
  );
  const counter = document.getElementById('productGalleryCounter');
  const navButtons = Array.from(document.querySelectorAll('[data-gnav]'));
  if (slides.length < 1) return;

  function activateZoomBg(slide) {
    var zoom = slide ? slide.querySelector('.product-gallery__zoom') : null;
    if (!zoom || zoom.style.backgroundImage) return;
    var src = zoom.getAttribute('data-gallery-zoom');
    if (src) zoom.style.backgroundImage = 'url("' + src + '")';
  }

  /* H12 (W99-fixG2): живой счётчик для скринридера. #productGalleryCounter в
     разметке нет (product.php вне волны) — создаём визуально-скрытый span
     aria-live=polite рядом с галереей и анонсируем «Слайд N из M». */
  let srCounter = document.getElementById('productGallerySrCounter');
  if (!srCounter) {
    srCounter = document.createElement('span');
    srCounter.id = 'productGallerySrCounter';
    srCounter.setAttribute('aria-live', 'polite');
    srCounter.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap';
    (track.parentNode || document.body).appendChild(srCounter);
  }

  /* H4 (W99-fixG2): стрелки на краях — disabled (первый слайд → «предыдущий»,
     последний → «следующий»); визуал в five.css (opacity .35 + cursor default). */
  function syncNav(idx) {
    navButtons.forEach(function (btn) {
      var dir = Number(btn.getAttribute('data-gnav'));
      btn.disabled = (dir < 0 && idx <= 0) || (dir > 0 && idx >= slides.length - 1);
    });
  }

  function setActive(index) {
    if (counter) counter.textContent = index + 1 + ' / ' + slides.length;
    srCounter.textContent = 'Слайд ' + (index + 1) + ' из ' + slides.length;
    syncNav(index);
    thumbs.forEach(function (thumb, i) {
      if (i === index) thumb.setAttribute('aria-current', 'true');
      else thumb.removeAttribute('aria-current');
    });
    activateZoomBg(slides[index]);
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
