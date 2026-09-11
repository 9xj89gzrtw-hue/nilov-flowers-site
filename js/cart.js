/* Корзина — клиентское состояние покупок в localStorage.
   Ключ: 'flowerCart'. Лимиты qty 1..99 и позиций 1..20 дублируют
   серверный контракт POST /api/orders — некорректный ввод отсекается
   на клиенте, а не только 400-м ответом сервера.
   createCart(storage) — фабрика с внедряемым storage (тестируемость). */

const CART_STORAGE_KEY = 'flowerCart';
const CART_MAX_QTY = 99;
const CART_MAX_ITEMS = 20;

function createCart(storage) {
  function read() {
    if (!storage) return [];
    try {
      const raw = storage.getItem(CART_STORAGE_KEY);
      const items = raw ? JSON.parse(raw) : [];
      return Array.isArray(items) ? items : [];
    } catch {
      return [];
    }
  }

  function write(items) {
    if (storage) storage.setItem(CART_STORAGE_KEY, JSON.stringify(items));
    /* Единая точка мутации — рассылаем событие, чтобы cart-ui.js
       перерисовался, откуда бы ни пришло изменение. */
    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
      window.dispatchEvent(new CustomEvent('cart:change', { detail: items }));
    }
  }

  function clampQty(qty) {
    const n = Math.trunc(Number(qty));
    if (!Number.isFinite(n)) return 1;
    return Math.min(Math.max(n, 1), CART_MAX_QTY);
  }

  function add(productId, qty, meta) {
    if (!productId) return read();
    const items = read();
    const existing = items.find((i) => i.product_id === productId);
    if (existing) {
      existing.qty = clampQty(existing.qty + (qty || 1));
      if (meta) Object.assign(existing, meta);
    } else if (items.length < CART_MAX_ITEMS) {
      items.push(
        Object.assign({ product_id: productId, qty: clampQty(qty || 1) }, meta || {})
      );
    }
    write(items);
    return items;
  }

  function remove(productId) {
    const items = read().filter((i) => i.product_id !== productId);
    write(items);
    return items;
  }

  function updateQty(productId, qty) {
    const items = read();
    const item = items.find((i) => i.product_id === productId);
    if (!item) return items;
    item.qty = clampQty(qty);
    write(items);
    return items;
  }

  function getItems() {
    return read();
  }

  function getTotal() {
    return read().reduce((sum, i) => sum + (Number(i.price) || 0) * i.qty, 0);
  }

  function clear() {
    write([]);
    return [];
  }

  return { add, remove, updateQty, getItems, getTotal, clear };
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = createCart;
} else {
  window.cart = createCart(window.localStorage);
}
