/* Favicon-badge: число позиций в корзине прямо на иконке вкладки (V6).
   Слушает 'cart:change' (cart.js). При qty=0 — базовая иконка; иначе поверх
   base рисуется розовый бейдж с числом (canvas 64px → data URL).
   Любая ошибка (canvas, CORS на base-иконку) — тихий no-op, вкладка остаётся базовой. */
(function () {
  var link = document.getElementById('faviconLink');
  if (!link) return;
  var base = link.getAttribute('data-base-icon') || link.getAttribute('href');

  function setHref(href) { if (link.getAttribute('href') !== href) link.setAttribute('href', href); }

  function drawBadge(num) {
    var S = 64, c = document.createElement('canvas');
    c.width = S; c.height = S;
    var g = c.getContext('2d');
    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function () { try { g.drawImage(img, 0, 0, S, S); } catch (e) {} render(); };
    img.onerror = render;
    function render() {
      g.beginPath();
      g.arc(S - 17, S - 17, 17, 0, 2 * Math.PI);
      g.fillStyle = '#E2799C'; g.fill();
      g.lineWidth = 5; g.strokeStyle = '#FFFFFF'; g.stroke();
      g.fillStyle = '#FFFFFF';
      g.font = 'bold ' + (num > 9 ? '20' : '26') + 'px sans-serif';
      g.textAlign = 'center'; g.textBaseline = 'middle';
      g.fillText(String(num), S - 17, S - 15);
      try { setHref(c.toDataURL('image/png')); } catch (e) { /* no-op */ }
    }
    img.src = base;
  }

  window.addEventListener('cart:change', function (ev) {
    var items = (ev && ev.detail) || [];
    var qty = items.reduce(function (s, i) { return s + (i.qty || 0); }, 0);
    if (qty === 0) setHref(base); else drawBadge(qty);
  });
})();
