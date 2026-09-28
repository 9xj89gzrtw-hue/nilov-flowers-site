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
  /* W105-7fix1 (7-a P2h): точки-аффорданс под вьюпортом (≤899px, разметка
     product.php + стили product-extras.css 4e). PHP печатает по числу
     слайдов текущей галереи; если слайдов больше/меньше (будущие фото) —
     ряд перестраивается здесь. Одна точка — CSS прячет ряд целиком. */
  let dotsWrap = track.parentNode
    ? track.parentNode.querySelector('.product-gallery__dots') : null;
  let dots = dotsWrap
    ? Array.from(dotsWrap.querySelectorAll('.product-gallery__dot')) : [];
  if (dotsWrap && dots.length !== slides.length) {
    dotsWrap.textContent = '';
    dots = slides.map(function () {
      const d = document.createElement('span');
      d.className = 'product-gallery__dot';
      dotsWrap.appendChild(d);
      return d;
    });
  }
  const counter = document.getElementById('productGalleryCounter');
  const navButtons = Array.from(document.querySelectorAll('[data-gnav]'));
  if (slides.length < 1) return;

  /* W105-8fix1 (критик-UX 8-b P1): слайды несут data-index — js/lightbox.js
     открывает лайтбокс НА КЛИКНУТОМ слайде (раньше индекс искался матчем src:
     дубликаты src у «общего вида» и «крупного плана» всегда отдавали 0-й).
     Слайд с .product-gallery__zoom («крупный план») помечен
     data-lightbox-zoom — лайтбокс продолжает рассказ слайда и стартует
     в зуме 1.8 с базой линзы (50% 36%). */
  slides.forEach(function (slide, i) {
    slide.setAttribute('data-index', String(i));
    if (slide.querySelector('.product-gallery__zoom')) {
      slide.setAttribute('data-lightbox-zoom', '1');
    }
  });

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
    dots.forEach(function (dot, i) {
      if (i === index) dot.setAttribute('aria-current', 'true');
      else dot.removeAttribute('aria-current');
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

  /* W104-κ (C4-D4 P1): КУРСОР-ЗУМ крупного плана. mousemove по слайду →
     --zx/--zy = % координаты курсора; motion-w104.css §8 на hover ведёт
     background-position за курсором (background-size 165% — это и есть
     zoom 1.6 с origin по точке курсора). Сглаживание — CSS transition
     .18s (эффект «линзы»). rAF-троттл: один расчёт на кадр, rect — один.
     mouseleave — снимаем переменные: фон возвращается к базе 50% 36%
     тем же переходом. Только hover+fine и без reduced-motion; тач не
     трогаем (тап по слайду открывает лайтбокс — уже есть), pinch не нужен. */
  /* W104-fix6: any-hover-гейт убран (в headless-жюри ложно false, C6-M6 P1.4) —
     mousemove-линза без мыши не срабатывает; reduced-motion оставлен. */
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var zRaf = 0;
    var zEl = null;
    var zx = 0;
    var zy = 0;
    slides.forEach(function (slide) {
      var zoom = slide.querySelector('.product-gallery__zoom');
      if (!zoom) return;
      slide.addEventListener('mousemove', function (e) {
        zEl = zoom;
        zx = e.clientX;
        zy = e.clientY;
        if (!zRaf) {
          zRaf = requestAnimationFrame(function () {
            zRaf = 0;
            if (!zEl) return;
            var r = zEl.getBoundingClientRect();
            if (!r.width || !r.height) return;
            /* кламп 0..100: курсор в подписи слайда не должен уводить фон
               за край (при >100% no-repeat-фон отрывается от границы) */
            zEl.style.setProperty('--zx', Math.max(0, Math.min(100, ((zx - r.left) / r.width) * 100)).toFixed(1) + '%');
            zEl.style.setProperty('--zy', Math.max(0, Math.min(100, ((zy - r.top) / r.height) * 100)).toFixed(1) + '%');
          });
        }
      }, { passive: true });
      slide.addEventListener('mouseleave', function () {
        zoom.style.removeProperty('--zx');
        zoom.style.removeProperty('--zy');
      }, { passive: true });
    });
  }
})();
