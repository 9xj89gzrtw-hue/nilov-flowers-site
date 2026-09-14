/* Кнопка «+» / «Добавить в корзину» — добавляет товар в корзину по
   data-атрибутам кнопки data-order-cta.
   Fix (критик-покупатель, FRICTION): панель НЕ открываем автоматически —
   её backdrop перекрывал форму заказа. Счётчик на иконке корзины даёт
   достаточно обратной связи; панель открывается кликом по корзине. */
(function () {
  if (!window.cart) return;

  document.querySelectorAll('[data-order-cta]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const { productId, productName, productPriceRaw, productImage } = btn.dataset;
      if (!productId) return;
      window.cart.add(productId, 1, {
        name: productName || '',
        price: Number(productPriceRaw) || 0,
        image: productImage || '',
      });
      /* Мягкая обратная связь без перекрытия: короткая анимация самой кнопки */
      btn.animate(
        [
          { transform: 'scale(1)', background: '' },
          { transform: 'scale(1.18)', offset: 0.4 },
          { transform: 'scale(1)' },
        ],
        { duration: 320, easing: 'cubic-bezier(.22,1,.36,1)' }
      );
      /* Awwwards-usability: SR-анонс добавления (drawer закрыт — qty-aria-live не виден) */
      let sr = document.getElementById('cartSrAnnounce');
      if (!sr) {
        sr = document.createElement('div');
        sr.id = 'cartSrAnnounce';
        sr.setAttribute('aria-live', 'polite');
        sr.setAttribute('role', 'status');
        sr.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap';
        document.body.appendChild(sr);
      }
      const items = window.cart.getItems ? window.cart.getItems() : [];
      let c = 0; items.forEach(function (it) { c += (it.qty || 1); });
      /* Awwwards-usability -0.2: «1 товар(ов)» → русское склонение (совпадает с itemsWord в cart-ui) */
      var w = c % 10 === 1 && c % 100 !== 11 ? 'товар' : (c % 10 >= 2 && c % 10 <= 4 && (c % 100 < 12 || c % 100 > 14) ? 'товара' : 'товаров');
      sr.textContent = (productName || 'Букет') + ' добавлен' + (c ? ', в корзине ' + c + ' ' + w : '');
    });
  });
})();
