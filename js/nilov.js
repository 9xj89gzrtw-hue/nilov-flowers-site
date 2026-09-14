/* Nilov Flowers — движок витринных анимаций W4.
   1) html.js-ready — ставит reveal.js/safety-net в рабочее состояние.
   2) Title-badge: счётчик корзины в названии вкладки «(2) … — Nilov Flowers».
   Никаких зависимостей. */
(function () {
  'use strict';

  /* 1. Сигнализируем, что JS жив: nilov.css safety-net
        (html:not(.js-ready)) перестаёт принудительно всё показывать. */
  var root = document.documentElement;
  root.classList.add('js-ready');

  /* 1b. WOW-интерактив: курсор-параллакс лепестков hero.
         --mx/--my в -1..1; CSS двигает .nv-petal--a/b. Троттл rAF. */
  var petals = document.querySelector('.nv-petals');
  if (petals && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var rafP = null;
    document.addEventListener('mousemove', function (e) {
      if (rafP) return;
      rafP = requestAnimationFrame(function () {
        rafP = null;
        petals.style.setProperty('--mx', ((e.clientX / window.innerWidth) * 2 - 1).toFixed(3));
        petals.style.setProperty('--my', ((e.clientY / window.innerHeight) * 2 - 1).toFixed(3));
      });
    }, { passive: true });
  }

  /* 1c. Скролл-параллакс букв бренда: --sy 0..1 (затухает к низу hero),
         .is-scrolling включает transform-смещение (см. nilov.css). */
  var brand = document.getElementById('nvBrand');
  if (brand && 'IntersectionObserver' in window) {
    var syRaf = null;
    var updateSy = function () {
      var r = brand.getBoundingClientRect();
      var heroH = window.innerHeight;
      var sy = Math.max(0, Math.min(1, (heroH - r.top) / heroH));
      brand.style.setProperty('--sy', sy.toFixed(3));
      if (sy > 0 && sy < 1) brand.classList.add('is-scrolling');
      else brand.classList.remove('is-scrolling');
    };
    window.addEventListener('scroll', function () {
      if (syRaf) return;
      syRaf = requestAnimationFrame(function () { syRaf = null; updateSy(); });
    }, { passive: true });
    updateSy();
  }

  /* 1d. PWA-подсказка установки (fix R2: «нет подсказки установить приложение»).
         iOS: честный текст-хинт на мобиле; Android: beforeinstallprompt → кнопка.
         Один показ на устройство (localStorage), закрыт — больше не показываем. */
  (function installHint() {
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (localStorage.getItem('nfInstallHintClosed') === '1') return;
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var isMobile = window.matchMedia('(max-width: 820px)').matches;
    if (!isMobile && !isIOS) return;
    var el = document.createElement('div');
    el.style.cssText = 'position:fixed;bottom:64px;left:12px;right:12px;z-index:95;background:#fff;border:1px solid rgba(43,45,47,.14);border-radius:14px;padding:12px 14px;font-size:.85rem;box-shadow:0 14px 40px -18px rgba(43,45,47,.4);display:flex;gap:10px;align-items:center';
    /* axe region-fix: плавающий баннер вне landmark'ов → делаем его явной region-областью */
    el.setAttribute('role', 'region');
    el.setAttribute('aria-label', 'Установка приложения');
    el.innerHTML = '<span style="flex:1">' + (isIOS
      ? 'Добавьте «Nilov Flowers» на главный экран — откройте меню «Поделиться» и выберите «На экран Домой».'
      : 'Установите «Nilov Flowers» как приложение — кнопка «Установить» справа.') + '</span>'
      + '<button type="button" style="border:none;background:var(--rose-deep,#E2799C);color:#fff;border-radius:999px;padding:8px 14px;font:600 .8rem sans-serif;cursor:pointer">Понятно</button>';
    el.querySelector('button').addEventListener('click', function () {
      localStorage.setItem('nfInstallHintClosed', '1');
      el.remove();
    });
    if (!isIOS && 'onbeforeinstallprompt' in window) {
      window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        var b = el.querySelector('button');
        b.textContent = 'Установить';
        b.onclick = function () { e.prompt(); localStorage.setItem('nfInstallHintClosed', '1'); el.remove(); };
      });
    }
    setTimeout(function () { document.body.appendChild(el); }, 2500);
  })();

  /* 2. Title-badge корзины — ВЫКЛЮЧЕН на витрине (владельцу не нравится «(1)» во вкладке).
     Счётчик остаётся только на бейдже иконки корзины + в админке (заказы). */
  function refresh() {}
  window.addEventListener('pageshow', refresh);
})();

/* 3. Таймер «до 20:00» в hero (критерий 13, конкурентная фишка EXPRESS/маркетплейсов).
     Показывает сколько часов-минут осталось до дедлайна заказа-сегодня. */
(function () {
  var el = document.querySelector('.hero__deadline');
  if (!el) return;
  var cfg = window.NILOV_CONFIG || {};
  var TZ = cfg.tz || 'Europe/Moscow';
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  /* Стеновые часы магазина в его таймзоне (критерий 26): раньше считали по локальному
     времени браузера — у клиента из другого города таймер врал (ночью показывал «сегодня»). */
  function wallNow() { return new Date(new Date().toLocaleString('en-US', { timeZone: TZ })); }
  function tick() {
    var now = wallNow();
    var hh = (cfg.deadlineHour ?? 20);
    var mm = (cfg.deadlineMinute ?? 0);
    var label = pad(hh) + ':' + pad(mm);
    var deadline = new Date(now);
    deadline.setHours(hh, mm, 0, 0);
    var h = now.getHours();
    /* Ночное окно (с дедлайна и до 08:00) — «осталось N часов до 20:00» бессмысленно
       и пугает: человек засыпает, а таймер орёт про «сегодня». (критерий 26) */
    if (h >= hh || h < 8) {
      var night = h < 8;
      el.textContent = '🌙 ' + (night
        ? (cfg.nightText || 'Сейчас ночь — заказы принимаем, доставим сегодня с 9:00')
        : (cfg.closedText || 'Сегодня заказы уже закрыты — доставим завтра с утра'));
      el.dataset.state = 'closed';
      return;
    }
    var diff = deadline - now;
    var H = Math.floor(diff / 3600000);
    var M = Math.ceil((diff % 3600000) / 60000);
    if (M === 60) { H += 1; M = 0; }
    /* Секунды убраны (критерий 27): они мерцают и не несут решения — только ч/м. */
    var t = H > 0 ? (H + ' ч' + (M ? ' ' + M + ' мин' : '')) : (M + ' мин');
    var tpl = cfg.countdownText || 'Успейте заказать сегодня — осталось {T} до 20:00';
    el.textContent = '⏱ ' + tpl.replace('{T}', t).replace('20:00', label);
    el.dataset.state = 'open';
  }
  tick();
  setInterval(tick, 30000); /* минута — достаточная точность без секундных перерисовок */
})();

/* 4. Избранное (критерий 13, Русский Букет-паттерн): сердечки + localStorage + фильтр «только избранное». */
(function () {
  var KEY = 'nilov_favs';
  function get() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
  function set(a) { try { localStorage.setItem(KEY, JSON.stringify(a)); } catch (e) {} }
  function sync() {
    var favs = get();
    document.querySelectorAll('.product-card__fav').forEach(function (b) {
      b.classList.toggle('is-active', favs.indexOf(b.getAttribute('data-fav-id')) !== -1);
      b.textContent = b.classList.contains('is-active') ? '♥' : '♡';
    });
    var t = document.getElementById('favToggle');
    if (t) {
      t.classList.toggle('is-active', favs.length > 0);
      t.textContent = favs.length > 0 ? '♥ ' + favs.length : '♡ Избранное';
    }
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.product-card__fav');
    if (b) {
      var id = b.getAttribute('data-fav-id');
      var favs = get();
      var i = favs.indexOf(id);
      if (i === -1) favs.push(id); else favs.splice(i, 1);
      set(favs); sync();
      e.preventDefault(); e.stopPropagation();
    }
    var t = e.target.closest('#favToggle');
    if (t) {
      t.classList.toggle('is-on');
      t.setAttribute('aria-pressed', t.classList.contains('is-on') ? 'true' : 'false');
      var on = t.classList.contains('is-on');
      var favs2 = get();
      document.querySelectorAll('.product-card').forEach(function (c) {
        var id = (c.querySelector('.product-card__fav') || {}).getAttribute ? c.querySelector('.product-card__fav').getAttribute('data-fav-id') : null;
        if (id === null) return;
        if (on && favs2.indexOf(id) === -1) c.style.display = 'none';
        else if (on) c.style.display = '';
      });
      if (!on) {
        document.querySelectorAll('.product-card').forEach(function (c) { c.style.display = ''; });
        var evt = new Event('change', { bubbles: true });
        var sel = document.getElementById('priceFilter');
        if (sel) sel.dispatchEvent(evt);
      }
    }
  });
  sync();
})();

/* 5. Проверка зоны доставки (критерий 13, Семицветик-паттерн): живой мэтч по вводу.
    Пишет тариф в каталоге и подставляет найденную зону в селект чекаута. */
(function () {
  var inp = document.getElementById('zoneCheckInput');
  var out = document.getElementById('zoneCheckResult');
  var sel = document.getElementById('orderDeliveryZone');
  if (!inp || !out) return;
  function norm(s) { return (s || '').toLowerCase().replace(/район|ра[йё]он/g, '').replace(/[^a-zа-яё0-9]/gi, '').trim(); }
  function match(v) {
    var opts = sel ? sel.querySelectorAll('option[data-price]') : [];
    var nv = norm(v);
    if (!nv) return null;
    var best = null;
    opts.forEach(function (o) {
      var t = norm(o.textContent);
      // совпадение ключевых подстрок (напр. «примор» → Приморский)
      if (t.indexOf(nv) !== -1 || nv.indexOf(t.slice(0, 6)) !== -1) best = o;
    });
    return best;
  }
  function apply() {
    var v = inp.value;
    if (!v.trim()) { out.textContent = ''; return; }
    var m = match(v);
    if (m) {
      var price = parseInt(m.getAttribute('data-price'), 10) || 0;
      out.style.color = 'var(--ink)';
      out.textContent = price === 0 ? '✓ 0 ₽' : price + ' ₽';
      if (sel) sel.value = m.value; // подстановка в чекаут
    } else {
      out.style.color = '#b3261e';
      out.textContent = inp.getAttribute('data-fallback') || 'не нашли — уточним по телефону';
    }
  }
  inp.addEventListener('input', apply);
})();
