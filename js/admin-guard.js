/* Критик-владелец W36 B2: новичок уходит из админки с несохранённой формой и молча
   теряет правки. Двойная защита: beforeunload (закрытие/переход браузером) + человекочитаемый
   confirm на кликах по внутренним ссылкам админки («Товары», «Назад», logout). */
(function () {
  'use strict';
  var dirty = false;
  function isEditableForm(f) {
    if (!f || f.hasAttribute('data-guard-off')) return false;
    var m = (f.getAttribute('method') || 'get').toLowerCase();
    return m !== 'get'; // GET-фильтры/поиск правок не содержат
  }
  function mark(on) { dirty = on; }
  document.addEventListener('input', function (e) {
    var f = e.target && e.target.closest && e.target.closest('form');
    if (isEditableForm(f)) mark(true);
  }, true);
  document.addEventListener('change', function (e) {
    var f = e.target && e.target.closest && e.target.closest('form');
    if (isEditableForm(f)) mark(true);
  }, true);
  document.addEventListener('submit', function (e) {
    if (e.target && e.target.tagName === 'FORM') mark(false); // сохраняемся — guard снять
  }, true);
  window.addEventListener('beforeunload', function (e) {
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = '';
  });
  document.addEventListener('click', function (e) {
    if (!dirty) return;
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (href === '' || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download')) return;
    if (!window.confirm('У вас есть несохранённые изменения на этой странице.\nВсё равно уйти и потерять их?')) {
      e.preventDefault();
      e.stopImmediatePropagation();
      return;
    }
    mark(false); // подтвердил уход — не спрашивать ещё и beforeunload
  }, true);
})();
