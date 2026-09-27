/* glue.js — W105-6fix2 (Task 6-fix2, критик-типограф 6-b, P0):
   типографическая склейка неразрывным пробелом (U+00A0):
     1) цифра + ₽: «2 500 ₽» — знак валюты не отрывается от числа
        (замер критика: 79 из 83 вхождений шли с обычным пробелом);
     2) русские предлоги/союзы 1–2 буквы (в, и, с, к, о, у, на, по, за,
        до, от, из…) не повисают в конце строки: «в␣подарок».
   Скоуп: текстовые узлы внутри h1–h4, p, li, a, button, label, summary,
   figcaption, .product-card__name (атрибуты placeholder/title не трогаем,
   marquee-спаны не входят). Один проход + MutationObserver для поздно
   вставленных/перезаписанных узлов (тост/корзина/апселл/empty-заголовок
   каталога — наблюдатель копит корни в очередь, кадр не теряет ничего).
   Идемпотентно: уже поставленный NBSP не порождает новых замен — повторный
   прогон ничего не меняет. Админка не обрабатывается (includes/layout.php
   не подключает footer.php; guard — страховка на случай переноса вызова).
   No-JS: текст остаётся как есть. Reduced-motion: статичный текст. */
(function () {
  'use strict';

  if (document.querySelector('.admin-top, .admin-nav, body.admin, [data-admin]')) return;

  var SCOPE = 'h1,h2,h3,h4,p,li,a,button,label,summary,figcaption,.product-card__name';
  var RUBLE = /(\d)[ \t\u00A0]+(₽)/g; /* NBSP тоже матчится → замена = тот же NBSP (идемпотентность) */
  var SHORT = /^[а-яёА-ЯЁ]{1,2}$/;

  function glueText(t) {
    if (t.indexOf('₽') !== -1) t = t.replace(RUBLE, '$1\u00A0$2');
    if (!/[а-яёА-ЯЁ]/.test(t)) return t;
    /* Токены «слово/разделитель»: разделитель после 1–2-буквенного слова
       → NBSP. Сплит с захватывающей скобкой сохраняет границы — цепочки
       «и в …» склеиваются целиком, а не через слово. */
    var p = t.split(/(\s+)/);
    for (var i = 0; i < p.length; i += 2) {
      if (SHORT.test(p[i]) && p[i + 1] && p[i + 2]) p[i + 1] = '\u00A0';
    }
    return p.join('');
  }

  function glueNode(node) {
    var t = node.nodeValue;
    if (!t || (t.indexOf('₽') === -1 && !/[а-яёА-ЯЁ]/.test(t))) return;
    var g = glueText(t);
    if (g !== t) node.nodeValue = g;
  }

  function glueRoot(root) {
    if (!root) return;
    if (root.nodeType === 3) { /* characterData-мутация */
      var host = root.parentElement;
      if (host && host.closest && host.closest(SCOPE)) glueNode(root);
      return;
    }
    if (root.nodeType !== 1 || !root.querySelectorAll) return;
    var seen = [];
    var els = root.matches && root.matches(SCOPE) ? [root] : [];
    var list = root.querySelectorAll(SCOPE);
    for (var i = 0; i < list.length; i++) els.push(list[i]);
    for (var j = 0; j < els.length; j++) {
      var w = document.createTreeWalker(els[j], NodeFilter.SHOW_TEXT, null);
      for (var n; (n = w.nextNode());) {
        if (seen.indexOf(n) === -1) { seen.push(n); glueNode(n); }
      }
    }
  }

  function init() {
    glueRoot(document.body);

    if (typeof MutationObserver !== 'function') return;
    /* Очередь КОРНЕЙ, а не флаг: батчи, пришедшие пока rAF pending,
       копятся в pending и обрабатываются тем же кадром — раньше
       ранний return глотал записи навсегда (живой репро 6-fix2:
       третий apply() каталога перезаписывал заголовок пустого
       состояния в том же тике, что и наш стартовый проход →
       его мутация дропалась, текст оставался без NBSP). */
    var pending = [];
    var queued = false;
    new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        var m = muts[i];
        if (m.type === 'characterData') { pending.push(m.target); continue; }
        for (var k = 0; k < m.addedNodes.length; k++) pending.push(m.addedNodes[k]);
      }
      if (queued) return;
      queued = true;
      requestAnimationFrame(function () {
        queued = false;
        var roots = pending; pending = [];
        for (var i = 0; i < roots.length; i++) glueRoot(roots[i]);
      });
    }).observe(document.body, { childList: true, subtree: true, characterData: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
