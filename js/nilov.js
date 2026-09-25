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

  /* 1d. PWA-подсказка установки (fix R2 → W97-fixA A1).
         iOS: честный текст-хинт на мобиле; Android: beforeinstallprompt → кнопка.
         W97-fixA (A1), по валидированным дефектам критиков:
           (б) показываем ТОЛЬКО со второй сессии — при первом визите лишь ставим
               localStorage 'pwaHintVisited' (sessionStorage не переживает
               перезагрузку вкладки/«Назад», поэтому именно localStorage);
           (а) закрытие — «×» (aria-label «Закрыть подсказку») или «Понятно»;
           (в) dismissal запоминается навсегда — 'nfInstallHintClosed'
               (ключ отдельный от pwaHintVisited);
           (г) на страницах с фиксированной нижней CTA товара
               (.product-page__cta--sticky, mobile ≤820px) баннер ставится
               ВЫШЕ кнопки «В корзину» (bottom = ctaTop + 10px), не перекрывая;
               иначе — над таббаром, как раньше;
           (д) пока на экране cookie-баннер — подсказку не показываем вовсе
               (ждём решения: cookie-banner.js снимает .cookie-visible
               и шлёт 'nf:cookie-done'). */
  (function installHint() {
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    /* W99-fixG2 (H11): на /order-thanks подсказку установки НЕ показываем —
       сразу после заказа не время агитировать за приложение (покупатель
       ждёт подтверждения; и локальный стенд, и прод-роуты дают pathname
       /order-thanks или /order-thanks.php). */
    if (/^\/order-thanks(\.php)?$/i.test(window.location.pathname)) return;
    try {
      /* (в) dismissal навсегда: ключ 'pwaHintDismissed' (по ТЗ W97-fixA);
         'nfInstallHintClosed' — легаси-ключ прошлых волн: кто уже закрыл подсказку,
         ту её больше не увидит. */
      if (localStorage.getItem('pwaHintDismissed') === '1'
          || localStorage.getItem('nfInstallHintClosed') === '1') return;
      if (localStorage.getItem('pwaHintVisited') !== '1') {
        /* первая сессия: запоминаем визит, подсказку НЕ показываем */
        localStorage.setItem('pwaHintVisited', '1');
        return;
      }
    } catch (e) { return; /* приват-режим без localStorage — без подсказки */ }
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var isMobile = window.matchMedia('(max-width: 820px)').matches;
    if (!isMobile && !isIOS) return;
    var el = document.createElement('div');
    el.style.cssText = 'position:fixed;bottom:64px;left:12px;right:12px;z-index:95;background:#fff;border:1px solid rgba(43,45,47,.14);border-radius:14px;padding:12px 14px;font-size:.85rem;box-shadow:0 14px 40px -18px rgba(43,45,47,.4);display:flex;gap:10px;align-items:center';
    /* axe region-fix: плавающий баннер вне landmark'ов → делаем его явной region-областью */
    el.setAttribute('role', 'region');
    el.setAttribute('aria-label', 'Установка приложения');
    var hintText = null; /* K11 (W101): span с текстом подсказки — фраза про кнопку
       «Установить справа» вставляется ТОЛЬКО по событию beforeinstallprompt
       (в Firefox события нет — текст не обещает несуществующую кнопку). */
    el.innerHTML = '<span style="flex:1">' + (isIOS
      ? 'Добавьте «Nilov Flowers» на главный экран — откройте меню «Поделиться» и выберите «На экран Домой».'
      : 'Установите «Nilov Flowers» как приложение — быстрый доступ к букетам с главного экрана.') + '</span>'
      + '<button type="button" data-hint-action style="border:none;background:var(--rose-deep,#E2799C);color:#fff;border-radius:999px;padding:8px 14px;font:600 .8rem sans-serif;cursor:pointer;flex:none">Понятно</button>'
      + '<button type="button" data-hint-close aria-label="Закрыть подсказку" title="Закрыть подсказку" style="border:none;background:transparent;color:#6e6a72;border-radius:999px;padding:8px 6px;font:600 1.05rem/1 sans-serif;cursor:pointer;flex:none;align-self:flex-start">×</button>';
    function dismiss() {
      try { localStorage.setItem('pwaHintDismissed', '1'); } catch (e) {}
      el.remove();
    }
    hintText = el.querySelector('span');
    el.querySelector('[data-hint-close]').addEventListener('click', dismiss);
    var actionBtn = el.querySelector('[data-hint-action]');
    actionBtn.addEventListener('click', dismiss);
    if (!isIOS && 'onbeforeinstallprompt' in window) {
      window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        /* K11 (W101): событие пришло — кнопка «Установить» реально появится,
          упоминаем её в тексте (до этого текст про кнопку не обещал ничего). */
        if (hintText) hintText.textContent = 'Установите «Nilov Flowers» как приложение — кнопка «Установить» справа.';
        actionBtn.textContent = 'Установить';
        actionBtn.onclick = function () { e.prompt(); dismiss(); };
      });
    }
    /* (г) позиция: ВЫШЕ фиксированной нижней CTA товара, если она есть;
       перерасчёт при resize и при скрытии/показе CTA (IntersectionObserver
       product.php тогглит .product-page__cta--hidden у футера). */
    function positionHint() {
      var bottom = 64; /* по умолчанию — над таббаром (mnav ≈ 61px + зазор) */
      var cta = document.querySelector('.product-page__cta--sticky');
      if (cta) {
        var st = getComputedStyle(cta);
        if (st.position === 'fixed' && st.display !== 'none'
            && !cta.classList.contains('product-page__cta--hidden')) {
          var r = cta.getBoundingClientRect();
          if (r.height > 0) {
            /* верх CTA от низа вьюпорта + отступ 10px */
            bottom = Math.max(bottom, Math.round(window.innerHeight - r.top + 10));
          }
        }
      }
      el.style.bottom = bottom + 'px';
    }
    window.addEventListener('resize', positionHint);
    if (window.MutationObserver) {
      var ctaEl = document.querySelector('.product-page__cta--sticky');
      if (ctaEl) {
        new MutationObserver(positionHint).observe(ctaEl, { attributes: true, attributeFilter: ['class'] });
      }
    }
    /* (д) cookie-баннер на экране → подсказку не показываем вовсе;
       повторяем попытку после решения (nf:cookie-done от cookie-banner.js). */
    var shown = false;
    function tryShow() {
      if (shown) return;
      if (document.body.classList.contains('cookie-visible')) {
        document.addEventListener('nf:cookie-done', tryShow, { once: true });
        return;
      }
      shown = true;
      setTimeout(function () {
        document.body.appendChild(el);
        positionHint();
      }, 2500);
    }
    tryShow();
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
    /* Логика-критик W34: в 08:00–09:00 магазин ещё закрыт (shop_hours с 9:00), но таймер
       уже орал «осталось 12 ч до 20:00», когда позвонить и согласовать нельзя.
       Ночное окно теперь [дедлайн .. открытие], а не [дедлайн .. 08:00]. */
    var oh = (cfg.openHour != null ? parseInt(cfg.openHour, 10) : 9);
    if (h >= hh || h < oh) {
      var night = h < oh;
      el.textContent = '🌙 ' + (night
        ? (cfg.nightText || 'Ночь. Заказ примем сейчас — доставим сегодня после 9:00')
        : (cfg.closedText || 'Приём заказов на сегодня закрыт — доставим завтра с 9:00'));
      el.dataset.state = 'closed';
      return;
    }
    var diff = deadline - now;
    var H = Math.floor(diff / 3600000);
    var M = Math.ceil((diff % 3600000) / 60000);
    if (M === 60) { H += 1; M = 0; }
    /* Секунды убраны (критерий 27): они мерцают и не несут решения — только ч/м. */
    var t = H > 0 ? (H + ' ч' + (M ? ' ' + M + ' мин' : '')) : (M + ' мин');
    var tpl = cfg.countdownText || 'Успейте заказать сегодня — осталось {T} до {D}';
    /* Логика-критик W34: подстрока '20:00' в кастомном тексте молча съедалась при смене
       дедлайна, а без литерала дедлайн исчезал вовсе. Канон — токен {D}; старый литерал — фолбэк. */
    el.textContent = '⏱ ' + tpl.replace('{T}', t).replace('{D}', label).replace('20:00', label);
    el.dataset.state = 'open';
  }
  tick();
  setInterval(tick, 30000); /* минута — достаточная точность без секундных перерисовок */
})();

/* 4. Избранное (критерий 13, Русский Букет-паттерн): сердечки + localStorage.
     W97-fixA (A2): скрытие карточек БОЛЬШЕ не живёт здесь — единственный источник
     истины видимости каталога window.NfCatalogApply() в catalog-filter.js (учитывает
     вкладку/чип/цену/поиск/избранное разом). Сердечко здесь только: обновляет
     localStorage, выставляет карточке data-fav и зовёт общий re-apply — раньше
     style.display от fav-фильтра затирался ценовым apply() и «Избранное» ломалось.
     W97-fixB3b (B3b-4): тот же стор работает и на вторичных страницах — клики
     по сердечкам related-карточек /occasion и /product ловятся той же делегацией,
     а кнопка рядом с CTA товара ([data-fav-toggle]) синхронизирует состояние
     (aria-pressed/♥/♡/aria-label); на страницах без каталог-грида refilter — noop,
     но localStorage общий с главной — лайк на товаре активирует сердечко в /#catalog. */
(function () {
  var KEY = 'nilov_favs';
  function get() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
  function set(a) { try { localStorage.setItem(KEY, JSON.stringify(a)); } catch (e) {} }
  /* общий re-apply фильтров каталога (catalog-filter.js; на страницах без каталога — noop) */
  function refilter() {
    if (typeof window.NfCatalogApply === 'function') window.NfCatalogApply();
  }
  function sync() {
    var favs = get();
    document.querySelectorAll('.product-card__fav').forEach(function (b) {
      var on = favs.indexOf(b.getAttribute('data-fav-id')) !== -1;
      b.classList.toggle('is-active', on);
      b.textContent = on ? '♥' : '♡';
      /* a11y-критик S3: сердечко — тумблер, состояние должно озвучиваться */
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      /* A2: data-fav на карточке — маркер для фильтра «Избранное» (NfCatalogApply) */
      var card = b.closest('.product-card');
      if (card) {
        if (on) card.setAttribute('data-fav', '1');
        else card.removeAttribute('data-fav');
      }
    });
    /* B3b-4: кнопка-иконка рядом с CTA на странице товара — состояние + визуал
       (класса в CSS для неё нет — волна не трогает css/, красим инлайном) */
    document.querySelectorAll('[data-fav-toggle]').forEach(function (b) {
      var on = favs.indexOf(b.getAttribute('data-fav-id')) !== -1;
      b.classList.toggle('is-active', on);
      b.textContent = on ? '♥' : '♡';
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.setAttribute('aria-label', on ? 'Убрать из избранного' : 'В избранное');
      if (b.hasAttribute('data-fav-inline')) {
        var pink = 'var(--pink,#ff4ea2)';
        b.style.color = on ? pink : 'var(--ink,#1c1a1e)';
        b.style.borderColor = on ? pink : 'var(--ink,#1c1a1e)';
      }
    });
    var t = document.getElementById('favToggle');
    if (t) {
      t.classList.toggle('is-active', favs.length > 0);
      t.textContent = favs.length > 0 ? '♥ ' + favs.length : '♡ Избранное';
    }
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.product-card__fav,[data-fav-toggle]');
    if (b) {
      var id = b.getAttribute('data-fav-id');
      var favs = get();
      var i = favs.indexOf(id);
      if (i === -1) favs.push(id); else favs.splice(i, 1);
      set(favs); sync(); refilter();
      e.preventDefault(); e.stopPropagation();
    }
    var t = e.target.closest('#favToggle');
    if (t) {
      /* визуальное состояние тумблера; карточки фильтрует NfCatalogApply
         (читает aria-pressed + data-fav) — смена цены больше не ломает фильтр */
      t.classList.toggle('is-on');
      t.setAttribute('aria-pressed', t.classList.contains('is-on') ? 'true' : 'false');
      refilter();
    }
  });
  sync();
  /* сердечки из localStorage восстановлены — пересчитываем каталог/счётчики */
  refilter();
})();

/* 5. Проверка зоны доставки (критерий 13, Семицветик-паттерн): живой мэтч по вводу.
    Пишет тариф в каталоге и подставляет найденную зону в селект чекаута. */
(function () {
  var inp = document.getElementById('zoneCheckInput');
  var out = document.getElementById('zoneCheckResult');
  var sel = document.getElementById('orderDeliveryZone');
  if (!inp || !out) return;
  /* W99-fixG2 (H12): aria-label поля синхронизирован с видимым лейблом
     «Район:» — было «Узнать стоимость доставки в ваш район» (задаётся в
     index.php, файл вне разрешённых волны; синхронизируем отсюда). */
  (function () {
    var zl = document.querySelector('label[for="zoneCheckInput"]');
    var base = zl ? (zl.textContent || '').trim().replace(/:\s*$/, '') : 'Район';
    if (base) inp.setAttribute('aria-label', base + ': узнайте стоимость доставки');
  })();
  function norm(s) { return (s || '').toLowerCase().replace(/район|ра[йё]он/g, '').replace(/[^a-zа-яё0-9]/gi, '').trim(); }
  function match(v) {
    var opts = sel ? sel.querySelectorAll('option[data-price]') : [];
    var nv = norm(v);
    if (!nv) return null;
    /* Логика-критик: чистые цифры («3», «500») матчились с текстами опций → ложные попадания;
       «петродворец» ⊄ «петродворцовый» → fallback при существующей зоне.
       Теперь: только буквы (K1: минимум 2 — «юж» → Южный), ищем максимум
       по длине совпавшего префикса (раньше побеждал последний). */
    if (!/[а-яa-z]{2,}/i.test(nv)) return null;
    var best = null, bestLen = 0;
    opts.forEach(function (o) {
      var t = norm(o.textContent);
      var m = t.match(/[а-яa-z]{3,}/i);
      if (!m) return;
      var word = m[0]; // «центральный», «петроградский»…
      var L = 0;
      for (var i = Math.min(nv.length, word.length); i >= 2; i--) {
        if (nv.slice(0, i) === word.slice(0, i)) { L = i; break; }
      }
      /* K1 (W101): короткий общий префикс давал ложные попадания — «петро» (5)
         матчило «Петроградский» и молча подставляло его в чекаут. Засчитываем
         совпадение, только если префикс покрывает хотя бы «полслова зоны без
         последней буквы» — порог floor((len-1)/2) (все вводы из ТЗ сходятся
         именно на нём), минимум 2 символа: «петро»(5) < 6 → честный fallback
         «район не найден»; «центр»(5) ≥ 5 → Центральный; «юж»(2) ≥ 2 → Южный
         (единственная зона с порогом <3 — более короткие вводы «ю»/«юг» не
         проходят); «север»(5) ≥ 3 → Северный; «моск»(4) ≥ 4 → Московский;
         «примор»(6) ≥ 4 → Приморский. Полное название — точное попадание,
         «петродвор»(L=5 < 6) — fallback, как и было задумано. Пороги остальных
         зон (центральный 5, северный 3, петроградский 6, василеостровский 7,
         московский 4, невский 3, приморский 4) ≥3 — 2-буквенный ввод им не
         матчится в принципе. */
      if (L >= Math.max(2, Math.floor((word.length - 1) / 2)) && L > bestLen) { best = o; bestLen = L; }
    });
    return best;
  }
  var lastMatched = undefined;
  function apply() {
    var v = inp.value;
    if (!v.trim()) { out.textContent = ''; lastMatched = undefined; return; }
    var m = match(v);
    if (m) {
      var price = parseInt(m.getAttribute('data-price'), 10) || 0;
      out.style.color = 'var(--ink)';
      out.textContent = price === 0 ? '✓ 0 ₽' : price + ' ₽';
      if (sel) sel.value = m.value; // подстановка в чекаут
      lastMatched = m.value;
    } else {
      out.style.color = '#b3261e';
      out.textContent = inp.getAttribute('data-fallback') || 'не нашли — уточним по телефону';
      /* W97 (UX-критик): при нераспознанном районе селект чекаута молча держал прежнюю зону —
         честно анонсируем это (SR + понятность состояния), когда до этого был успешный мэтч. */
      if (lastMatched !== undefined && sel && window.nfAnnounce) {
        window.nfAnnounce('Район не распознан — зона доставки в заказе не изменилась. Выберите район из списка.');
      }
      lastMatched = null;
    }
  }
  inp.addEventListener('input', apply);
})();
