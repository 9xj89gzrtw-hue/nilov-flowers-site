/* Кнопка «+» / «Добавить в корзину» — добавляет товар в корзину по
   data-атрибутам кнопки data-order-cta, затем открывает drawer. */
(function () {
  if (!window.cart) return;

  const toggle = document.getElementById('cartToggle');
  const cartMode = (toggle && toggle.dataset.cartMode) || 'drawer';

  document.querySelectorAll('[data-order-cta]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const { productId, productName, productPriceRaw, productImage } = btn.dataset;
      if (!productId) return;
      window.cart.add(productId, 1, {
        name: productName || '',
        price: Number(productPriceRaw) || 0,
        image: productImage || '',
      });
      if (cartMode === 'drawer' && window.cartUI) window.cartUI.open();
    });
  });
})();
