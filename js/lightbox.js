/* Лайтбокс — увеличение фото товара по тапу/клику. Тот же паттерн
   открытия/закрытия, что и у панели корзины (cart-ui.js): backdrop-клик,
   Escape, блокировка скролла body (переиспользует .no-scroll), возврат
   фокуса на элемент, с которого лайтбокс был открыт. */
(function () {
  const triggers = document.querySelectorAll('[data-lightbox-trigger]');
  if (!triggers.length) return;
  const lightbox = document.createElement('div');
  lightbox.className = 'lightbox';
  lightbox.hidden = true;
  lightbox.setAttribute('role', 'dialog');
  lightbox.setAttribute('aria-modal', 'true');
  lightbox.setAttribute('aria-label', 'Просмотр фото');
  /* <img> без src в разметку не кладём заранее — создаём при открытии и
     удаляем при закрытии. Причина: постоянный пустой <img> (0x0, скрыт
     через .lightbox[hidden]) ложно триггерил проверку "фото заполняет
     контейнер" в scripts/check-visual.js — тот сканирует все <img> на
     странице без учёта намеренно пустых шаблонных элементов (Task 11). */
  lightbox.innerHTML = `
    <div class="lightbox__backdrop" data-lightbox-close></div>
    <figure class="lightbox__figure">
      <button type="button" class="lightbox__close" data-lightbox-close aria-label="Закрыть">&times;</button>
    </figure>`;
  document.body.appendChild(lightbox);

  const figure = lightbox.querySelector('.lightbox__figure');
  let img = null;
  let lastTrigger = null;

  function open(trigger) {
    const src = trigger.dataset.lightboxSrc;
    if (!src) return;
    img = document.createElement('img');
    img.className = 'lightbox__img';
    img.src = src;
    img.alt = trigger.dataset.lightboxAlt || '';
    figure.insertBefore(img, figure.firstChild);
    lastTrigger = trigger;
    lightbox.hidden = false;
    document.body.classList.add('no-scroll');
    lightbox.querySelector('.lightbox__close').focus();
  }

  function close() {
    lightbox.hidden = true;
    document.body.classList.remove('no-scroll');
    if (img) {
      img.remove();
      img = null;
    }
    if (lastTrigger) lastTrigger.focus();
  }

  triggers.forEach(function (trigger) {
    /* W97-fixA (A4): триггеры — div'ы (недостижимы с клавиатуры). Делаем их
       фокусируемыми «кнопками»: tabindex=0 + role=button + говорящий aria-label
       (по data-lightbox-alt, если своего нет). */
    trigger.setAttribute('tabindex', '0');
    trigger.setAttribute('role', 'button');
    if (!trigger.getAttribute('aria-label')) {
      trigger.setAttribute('aria-label', trigger.dataset.lightboxAlt
        ? 'Увеличить фото: ' + trigger.dataset.lightboxAlt
        : 'Увеличить фото');
    }
    trigger.addEventListener('click', function () {
      open(trigger);
    });
  });

  /* W97-fixA (A4): делегированный keydown по документу — Enter/Space на
     сфокусированном [data-lightbox-trigger] открывает лайтбокс.
     Оба гасим preventDefault: Space — чтобы не прокручивать страницу, Enter —
     чтобы браузер НЕ дошлёт синтетический click уже новой цели фокуса:
     open() переводит фокус на .lightbox__close, и без preventDefault «клик»
     Enter прилетает кнопке «Закрыть» — лайтбокс захлопывается в тот же такт
     (клавиатурный Enter «ничего не делал»; поймано при прогоне agent-browser). */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
    const active = document.activeElement;
    const t = active && active.closest ? active.closest('[data-lightbox-trigger]') : null;
    if (!t || !lightbox.hidden) return;
    e.preventDefault();
    open(t);
  });

  lightbox.addEventListener('click', function (e) {
    if (e.target.closest('[data-lightbox-close]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !lightbox.hidden) close();
  });
})();
