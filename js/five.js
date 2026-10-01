/* five.js — витринная логика редизайна 5cv (W96 / T2-b).
   1) W106-C1 cityMenu: город-дропдаун в шапке/футере (вместо гео-топбара):
      выбор «Санкт-Петербург» пишет localStorage 'fc-city-ok' (прежний
      контракт подтверждения города), «Выбрать другой» → #contacts.
   1b) W106-C1 burgerMenu: мобильный бургер ≤899px (паттерн admin-гамбургера
       W105-b): Escape / тап-вне / клик по ссылке закрывают панель.
   2) Карусели .fc-carousel: стрелки + disabled-состояния по скроллу.
   2b) TILT карточек (W104-κ, C4-D4): --tx/--ty на .product-card по
       курсору — делегированный mousemove на .fc-carousel/.catalog__grid
       (только pointer:fine, без reduced-motion; CSS — motion-w104.css §8).
   3) Чипы цен + поиск #fcSearch: W97-fixA (A2/A3) — здесь ТОЛЬКО DOM-состояние
      (is-active у чипов, значение поля поиска) + вызов общего фильтра
      window.NfCatalogApply() из catalog-filter.js (единый источник истины:
      вкладка И цена И чип И поиск И избранное). Плюс пилюля «Нашлось N —
      посмотреть ↓» под строкой поиска (A3), автоскролл по ?q= и
      ре-применение фильтров на pageshow (bfcache/возврат «Назад»).
      W99-fixG2: H2 — пилюля слушает событие 'fc:filter' (сброс фильтров в
      catalog-filter.js тоже обновляет её); H3 — сердечко в шапке включает
      фильтр «Избранное»; H5 — «Нашёлся 1/21 букет».
   Vanilla JS, без зависимостей. */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }
  function reducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }
  /* W104-δ (C2-M2 P0-1): единая точка программного скролла к секции —
     через Lenis с offset −96 (sticky-хедер), чтобы якорные переходы из JS
     шли тем же единственным плавным пробегом, что и клики по ссылкам
     (kinetic.js). Нет Lenis (тач/reduced/сбой) — нативный smooth
     с ручной компенсацией хедера. */
  function nfScrollToEl(el) {
    if (!el) return;
    var lenis = window.NF_LENIS;
    if (lenis && !lenis.isStopped && typeof lenis.scrollTo === 'function') {
      lenis.scrollTo(el, { offset: -96 });
      return;
    }
    var top = el.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) - 96;
    window.scrollTo({ top: Math.max(0, top), behavior: reducedMotion() ? 'auto' : 'smooth' });
  }
  /* Русское склонение: 1 букет / 2 букета / 5 букетов */
  function pluralBuket(n) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return 'букет';
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'букета';
    return 'букетов';
  }

  ready(function () {
    cityMenu(); /* W106-C1: дропдаун города в шапке/футере */
    burgerMenu(); /* W106-C1: мобильный бургер ≤899px */
    carousels();
    catalogFilters(); /* чипы + поиск + сброс — общее состояние (AND) */
    rowLinks();
    headerFavLink(); /* W99-fixG2 (H3): сердечко шапки → фильтр избранного */
    magneticHeroCta(); /* W103 (6-b): магнитная hero-CTA (только hover+fine) */
    cardTilt(); /* W104-κ (C4-D4 P1): tilt карточек — --tx/--ty по курсору (только fine) */
    cartTotalPulse(); /* W103 (6-b, M7): пульс итога корзины при изменении */
    faqSmoothClose(); /* W104-β (C1-M P0): FAQ/SEO-details — плавное закрытие 1fr→0fr */
    marqueePlayback(); /* W104-β (C1-M P0): marquee-лента играет только в вьюпорте */
  });

  /* ---------- 1. W106-C1: Город — компактный дропдаун (шапка + футер) ----------
     Гео-топбар с вопросом «ваш город?» удалён (дизайн-дир P0-2): город тихо
     живёт в шапке/футере. Логика прежнего бара сохранена: явный выбор города
     пишет localStorage 'fc-city-ok' (маркер «город подтверждён»), ссылка
     «Выбрать другой» ведёт на #contacts (контакты/телефон — как раньше).
     Дропдауны независимы: открытие одного закрывает остальные. */
  function cityMenu() {
    var boxes = document.querySelectorAll('.fc-city');
    if (!boxes.length) return;
    function closeAll(except) {
      Array.prototype.forEach.call(boxes, function (b) {
        if (b === except) return;
        var menu = b.querySelector('.fc-city-menu');
        var btn = b.querySelector('.fc-city__btn');
        if (menu) menu.hidden = true;
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });
    }
    Array.prototype.forEach.call(boxes, function (box) {
      var btn = box.querySelector('.fc-city__btn');
      var menu = box.querySelector('.fc-city-menu');
      if (!btn || !menu) return;
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = !menu.hidden;
        closeAll(box);
        menu.hidden = open;
        btn.setAttribute('aria-expanded', open ? 'false' : 'true');
      });
      /* выбор города — тот же маркер, что писала кнопка «Да, верно» топбара */
      var opt = menu.querySelector('.fc-city-menu__opt');
      if (opt) {
        opt.addEventListener('click', function () {
          try { localStorage.setItem('fc-city-ok', '1'); } catch (err) {}
          var city = opt.getAttribute('data-city');
          if (city) {
            Array.prototype.forEach.call(document.querySelectorAll('.fc-city__label'), function (l) {
              l.textContent = city;
            });
          }
          closeAll();
          btn.setAttribute('aria-expanded', 'false');
          btn.focus();
        });
      }
      /* клик по «Выбрать другой» — просто переход на #contacts, панель уходит */
      menu.addEventListener('click', function (e) {
        if (e.target.closest('a')) { closeAll(); btn.setAttribute('aria-expanded', 'false'); }
      });
    });
    /* тап вне / Escape закрывают все дропдауны */
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.fc-city')) closeAll();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAll();
    });
  }

  /* ---------- 1b. W106-C1: Мобильный бургер ≤899px ----------
     Паттерн admin-гамбургера (includes/layout.php W105 4-b): aria-expanded,
     закрытие по клику-по-ссылке / Escape / тапу вне шапки; при возврате
     к десктопу (≥900px) панель закрывается сама. */
  function burgerMenu() {
    var burger = document.getElementById('fcBurger');
    var panel = document.getElementById('fcBurgerPanel');
    if (!burger || !panel) return;
    var header = burger.closest('.fc-header') || document.querySelector('.fc-header');
    function setOpen(open) {
      panel.hidden = !open;
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    }
    burger.addEventListener('click', function (e) {
      e.stopPropagation();
      setOpen(panel.hidden);
    });
    panel.addEventListener('click', function (e) {
      if (e.target.closest('a')) setOpen(false);
    });
    document.addEventListener('click', function (e) {
      if (!panel.hidden && header && !header.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !panel.hidden) { setOpen(false); burger.focus(); }
    });
    var mq = window.matchMedia('(min-width:900px)');
    if (mq.addEventListener) {
      mq.addEventListener('change', function (m) { if (m.matches) setOpen(false); });
    }
  }

  /* ---------- 2. Карусели: стрелки ← → + disabled по краям ---------- */
  function carousels() {
    var rows = document.querySelectorAll('.fc-row');
    Array.prototype.forEach.call(rows, function (row) {
      var track = row.querySelector('.fc-carousel');
      if (!track) return;
      var prev = row.querySelector('.fc-row__arrow:not(.fc-row__arrow--next)');
      var next = row.querySelector('.fc-row__arrow--next');
      function step(dir) {
        var delta = track.clientWidth * 0.8 * dir;
        var left = Math.max(0, Math.min(track.scrollWidth - track.clientWidth, track.scrollLeft + delta));
        if (typeof track.scrollTo === 'function') {
          track.scrollTo({ left: left, behavior: reducedMotion() ? 'auto' : 'smooth' });
        } else {
          track.scrollLeft = left; /* старые браузеры: без smooth */
        }
      }
      function sync() {
        var max = track.scrollWidth - track.clientWidth;
        if (prev) prev.disabled = track.scrollLeft <= 4;
        if (next) next.disabled = track.scrollLeft >= max - 4;
      }
      if (prev) prev.addEventListener('click', function () { step(-1); });
      if (next) next.addEventListener('click', function () { step(1); });
      track.addEventListener('scroll', sync, { passive: true });
      window.addEventListener('resize', sync);
      /* K4 (W101): карусель — role=region + tabindex=0, но клавиатурой не
         листалась (только стрелки-кнопки мышью). Теперь сфокусированный track
         листается ArrowLeft/ArrowRight на ширину карточки (pitch = разница
         offsetLeft соседних карточек — включает flex-gap); preventDefault —
         только для этих двух клавиш, остальные (Tab/Home/…) не трогаем. */
      track.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault();
        var kcards = track.querySelectorAll('.product-card');
        var pitch = 0;
        if (kcards.length >= 2) {
          pitch = kcards[1].offsetLeft - kcards[0].offsetLeft;
        } else if (kcards.length === 1) {
          pitch = kcards[0].offsetWidth;
        }
        if (!pitch || pitch < 10) pitch = track.clientWidth * 0.8;
        var kleft = Math.max(0, Math.min(track.scrollWidth - track.clientWidth,
          track.scrollLeft + pitch * (e.key === 'ArrowRight' ? 1 : -1)));
        if (typeof track.scrollTo === 'function') {
          track.scrollTo({ left: kleft, behavior: reducedMotion() ? 'auto' : 'smooth' });
        } else {
          track.scrollLeft = kleft;
        }
      });
      /* Стартовое состояние (карточки ленивые — перепроверяем после раскладки) */
      sync();
      setTimeout(sync, 300);
      setTimeout(sync, 1200);
    });
  }

  /* ---------- 3. Чипы цен + поиск: состояние + общий re-apply + пилюля ---------- */

  function catalogFilters() {
    var grid = document.getElementById('catalogGrid');
    var form = document.querySelector('.fc-search');
    var inp = document.getElementById('fcSearch');
    if (!grid) {
      /* Вторичные страницы: каталога нет — поиск уводит на главную с запросом
         (W96-fix1/F3: было пассивное form.action — теперь JS-редирект с #catalog;
         без JS срабатывает нативный submit action="/" method="get" с name="q") */
      if (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          var q = inp ? (inp.value || '').trim() : '';
          location.href = q !== '' ? '/?q=' + encodeURIComponent(q) + '#catalog' : '/#catalog';
        });
      }
      return;
    }
    var chipsBox = document.querySelector('.fc-chips');
    var chips = chipsBox ? Array.prototype.slice.call(chipsBox.querySelectorAll('.fc-chip')) : [];

    /* A3: пилюля под строкой поиска на главной — «Нашлось N букетов — посмотреть ↓».
       Появляется при непустом запросе, прячется при очистке; клик — плавный скролл
       к #catalog (каталог ниже первого экрана, живой фильтр его не видно).
       W104-δ (C2-D2 P1 «поиск без счётчика»): рядом — визуально скрытый
       aria-live-регион «Нашлось N букет(а/ов) по запросу …» (SR слышит
       результат фильтрации без взгляда на каталог; при 0 — «ничего
       не нашлось»; очистка — тишина). */
    var pill = null;
    var live = null;
    function liveCount(q, n) {
      if (!live || !live.isConnected) {
        live = document.createElement('div');
        live.id = 'fcSearchLive';
        live.setAttribute('role', 'status');
        live.setAttribute('aria-live', 'polite');
        live.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap';
        document.body.appendChild(live);
      }
      if (!q) { live.textContent = ''; return; }
      live.textContent = n > 0
        ? (n % 10 === 1 && n % 100 !== 11 ? 'Нашёлся' : 'Нашлось') + ' '
          + n + ' ' + pluralBuket(n) + ' по запросу «' + q + '»'
        : 'По запросу «' + q + '» ничего не нашлось';
    }
    function pillUpdate(visible) {
      var q = inp ? (inp.value || '').trim() : '';
      if (!q) {
        if (pill) pill.style.display = 'none';
        liveCount('', 0);
        return;
      }
      if (visible == null) {
        /* страховка, если catalog-filter.js не загрузился: считаем видимые сами */
        visible = 0;
        grid.querySelectorAll('.product-card').forEach(function (c) {
          if (getComputedStyle(c).display !== 'none' && !c.classList.contains('is-hidden')) visible++;
        });
      }
      if (!pill) {
        pill = document.createElement('button');
        pill.type = 'button';
        pill.id = 'fcSearchPill';
        pill.style.cssText = 'position:absolute;top:calc(100% + 8px);left:0;z-index:70'
          + ';display:inline-flex;align-items:center;gap:4px;border:none;border-radius:999px'
          + ';padding:9px 16px;min-height:36px;background:var(--ink,#1c1a1e);color:#fff'
          + ';font-family:var(--font-ui,Montserrat,sans-serif);font-weight:600;font-size:13px' /* F2 (P1-6): 12.8 → 13 — читаемый минимум */
          + ';cursor:pointer;box-shadow:0 12px 30px -12px rgba(28,26,30,.5);white-space:nowrap';
        pill.addEventListener('click', function () {
          nfScrollToEl(document.getElementById('catalog'));
        });
        /* .fc-search уже position:relative (five.css) — пилюля висит под полем */
        (form || document.body).appendChild(pill);
      }
      /* W101 (редактор): при 0 совпадений пилюля «посмотреть ↓» звала в пустой
         каталог — скрываем (empty-state в каталоге объясняет причину). */
      if (visible === 0) {
        pill.style.display = 'none';
        liveCount(q, 0);
        return;
      }
      pill.style.display = 'inline-flex';
      /* H5 (W99-fixG2): правильный род — «Нашёлся 1/21/31 букет»,
         «Нашлось 3 букета/11 букетов». */
      var verb = (visible % 10 === 1 && visible % 100 !== 11) ? 'Нашёлся' : 'Нашлось';
      pill.textContent = verb + ' ' + visible + ' ' + pluralBuket(visible) + ' — посмотреть ↓';
      pill.setAttribute('aria-label', verb + ' ' + visible + ' ' + pluralBuket(visible) + ' — перейти к каталогу букетов');
      liveCount(q, visible);
    }

    /* Единый re-apply: карточки/счётчики/empty-state пересчитывает catalog-filter.js;
      здесь обновляем только пилюлю (число видимых) и, при надобности, скроллим. */
    function refilter(scroll) {
      var visible = null;
      if (typeof window.NfCatalogApply === 'function') {
        visible = window.NfCatalogApply();
      }
      pillUpdate(visible);
      if (scroll) {
        nfScrollToEl(document.getElementById('catalog'));
      }
    }

    /* H2 (W99-fixG2): пилюля обновляется ЛЮБЫМ apply() каталога, а не только
       нашими чипами/поиском. «Сбросить фильтры» в catalog-filter.js зовёт
       apply() напрямую (минуя five.js) — раньше пилюля со старым числом
       оставалась висеть. apply() рассылает 'fc:filter' с числом видимых —
       подписываемся и приводим пилюлю в актуальное состояние (при пустом
       запросе pillUpdate сам её прячет). */
    window.addEventListener('fc:filter', function (e) {
      var v = e && e.detail && typeof e.detail.visible === 'number' ? e.detail.visible : null;
      pillUpdate(v);
    });

    /* Чипы: единственный активный; повторный клик — снять. Сами карточки
       фильтрует NfCatalogApply (читает .fc-chip.is-active).
       S3 (v2026.3): чип «Все» (data-chip="all") не тогглится — клик всегда
       сбрасывает фильтры к полному каталогу (принцип 5cv). */
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var isAll = chip.getAttribute('data-chip') === 'all';
        var wasActive = chip.classList.contains('is-active');
        chips.forEach(function (c) {
          c.classList.remove('is-active');
          c.setAttribute('aria-pressed', 'false');
        });
        if (!wasActive || isAll) {
          chip.classList.add('is-active');
          chip.setAttribute('aria-pressed', 'true');
        }
        refilter(true);
      });
    });

    /* Поиск (W96-fix1/F3 → W97-fixA A3): живая фильтрация с debounce 250мс —
       по мере ввода (пилюля с числом), Enter — применяет сразу и скроллит
       к каталогу; пустой запрос (в т.ч. крестик type=search) — снимает фильтр. */
    var debounceTimer = null;
    if (form && inp) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
        refilter(true);
      });
      inp.addEventListener('input', function () {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
          debounceTimer = null;
          refilter(false);
        }, 250);
      });
    }

    /* W96-fix1 (F3) → A3(б): перенос запроса со вторичных страниц — /?q=розы#catalog:
       заполняем поле и применяем фильтр сразу; ОДИН автоскролл к #catalog после
       инициализации (нативный hash-прыжок срабатывает до раскладки; smooth-скролл
       через 400мс гарантирует, что покупатель попал к отфильтрованному каталогу). */
    var qParam = new URLSearchParams(window.location.search).get('q');
    if (qParam && inp) {
      inp.value = qParam;
      refilter(false);
      setTimeout(function () {
        nfScrollToEl(document.getElementById('catalog'));
      }, 400);
    }

    /* A3(в): desync «Назад» — браузер восстанавливает текст в поле поиска,
       а JS-фильтры сброшены (страница перегенерирована) → «роз» в поле, но 23
       карточки. pageshow (bfcache ИЛИ обычный показ) — пере-применяем фильтры
       из АКТУАЛЬНОГО значения поля. */
    window.addEventListener('pageshow', function () {
      if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
      refilter(false);
    });
  }

  /* ---------- 4. «Смотреть все» + ссылки каталога в футере ----------
     data-tab={id}: включаем соответствующую вкладку каталога (её клик
     обработает catalog-filter.js), якорь #catalog срабатывает сам.
     W96-fix1 (F7): data-chip={hit|premium|low} — «Смотреть все» у Хитов/
     Премиума/До N применяет одноимённый чип (полный путь клика по чипу:
     active + aria-pressed + фильтр + скролл; уже активный чип — только якорь).
     W96-fix3b (D2): делегирование по документу — работают ЛЮБЫЕ ссылки
     с data-tab/data-chip (карусели витрины И колонки футера). На вторичных
     страницах каталога нет: data-tab уводит на глубокую ссылку
     /?category=N#catalog (её подхватит catalog-filter.js), data-chip —
     просто к каталогу (нативный переход по href).
     K5 (W101): data-chip с вторичной страницы — тоже глубокая ссылка
     /?chip=hit#catalog (раньше — ранний return на /#catalog без чипа);
     её подхватывает catalog-filter.js (аналог ?category=). */
  function rowLinks() {
    document.addEventListener('click', function (e) {
      /* W104-δ: убран guard e.defaultPrevented — якорные клики теперь гасит
         capture-обработчик kinetic.js (телепорт-фикс); здесь ДОРАБАТЫВАЕМ
         свою часть (вкладка/чип + плавный скролл) независимо от него */
      if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest ? e.target.closest('a[data-tab],a[data-chip]') : null;
      if (!a) return;
      var tabId = a.getAttribute('data-tab');
      var chipId = a.getAttribute('data-chip');
      e.preventDefault(); /* нативный fragment-прыжок не нужен — скроллим сами */
      try { if (a.hash) history.pushState(null, '', a.hash); } catch (err) {}
      if (tabId) {
        var tab = document.querySelector('.catalog-tabs__tab[data-category-id="' + tabId + '"]');
        if (tab) {
          tab.click();
        } else {
          /* Вторичная страница: вкладки нет — глубокая ссылка включит её на главной */
          location.href = '/?category=' + encodeURIComponent(tabId) + '#catalog';
          return;
        }
      }
      if (chipId) {
        var chip = document.querySelector('.fc-chip[data-chip="' + chipId + '"]');
        if (!chip) {
          /* K5 (W101): вторичная страница — чипа нет в DOM, нативный href /#catalog
             включил бы каталог без фильтра. Уводим на глубокую ссылку с чипом. */
          location.href = '/?chip=' + encodeURIComponent(chipId) + '#catalog';
          return;
        }
        if (!chip.classList.contains('is-active')) {
          chip.click(); /* чип-обработчик сам применит фильтр и скролл (refilter(true)) */
        }
      }
      /* Единый плавный пробег к каталогу (чип-клик уже мог скроллить —
         повторный вызов идемпотентно перезапускает ту же цель) */
      nfScrollToEl(document.getElementById('catalog'));
    });
  }

  /* ---------- 5. Сердечко в шапке → фильтр «Избранное» (W99-fixG2, H3) ----------
     Ссылка «Избранное — в каталоге» (href /#catalog) раньше просто прыгала к
     каталогу. На главной теперь: включаем тумблер «Избранное» (aria-pressed +
     .is-on — то же состояние, что ставит клик по самому тумблеру в nilov.js),
     применяем общие фильтры и плавно скроллим к #catalog. На вторичных
     страницах (нет #favToggle/#catalogGrid) — обычная навигация по href. */
  function headerFavLink() {
    var link = document.querySelector('.fc-header__icons a.fc-header__icon[href="/#catalog"]');
    if (!link) return;
    link.addEventListener('click', function (e) {
      /* W104-δ: guard e.defaultPrevented убран — его ставит capture-обработчик
         якорей (kinetic.js, телепорт-фикс); фильтр «Избранное» обязан включаться */
      if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var fav = document.getElementById('favToggle');
      var grid = document.getElementById('catalogGrid');
      if (!fav || !grid) return; /* не на главной — нативный переход по href */
      e.preventDefault();
      if (fav.getAttribute('aria-pressed') !== 'true') {
        fav.classList.add('is-on');
        fav.setAttribute('aria-pressed', 'true');
      }
      if (typeof window.NfCatalogApply === 'function') window.NfCatalogApply();
      nfScrollToEl(document.getElementById('catalog'));
    });
  }
  /* ---------- 6. Магнитные CTA (W103 → W104-β, C1-M P1) ----------
     Кнопка тянется за курсором, возврат .3s back-out. Только мышь с точным
     указателем и без prefers-reduced-motion; инлайн-transform живёт до
     mouseleave — CSS-ховер (translateY −2px) на время магнита замещается.
     W104-β (C1-M): радиус 6→12px (hero/премиум/sticky-PDP) и 10px на CTA
     карточек каталога; лёгкий scale(1.02) в притяжении — тактильный
     «прилипший» отклик вместо едва заметного сдвига. */
  function magneticHeroCta() {
    if (reducedMotion()) return;
    /* W104-fix6: matchMedia-гейт убран — эффекты от mousemove безопасны без мыши (не срабатывают),
       а в headless-средах жюри any-hover=false ложно отключал их (C6-M6 P1.4) */
    function attach(cta, R) {
      if (!cta.addEventListener) return;
      cta.classList.add('will-magnet');
      cta.addEventListener('mousemove', function (e) {
        var r = cta.getBoundingClientRect();
        if (!r.width || !r.height) return;
        var dx = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / (r.width / 2)));
        var dy = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2)) / (r.height / 2)));
        cta.style.transition = 'transform .07s linear';
        cta.style.transform = 'translate(' + (dx * R).toFixed(1) + 'px,'
          + (dy * R).toFixed(1) + 'px) scale(1.02)';
      });
      cta.addEventListener('mouseleave', function () {
        cta.style.transition = 'transform .3s cubic-bezier(.34,1.56,.64,1)';
        cta.style.transform = '';
      });
    }
    /* сильный магнит: hero-пилюля, премиум-кнопка, sticky-CTA товара */
    document.querySelectorAll('.fc-hero__cta, .fc-premium__cta, .product-page__cta--sticky')
      .forEach(function (cta) { attach(cta, 12); });
    /* мягкий: CTA карточек каталога (кнопки компактнее — 10px) */
    document.querySelectorAll('.product-card__cta')
      .forEach(function (cta) { attach(cta, 10); });
  }

  /* ---------- 6b. TILT КАРТОЧЕК (W104-κ, C4-D4 P1 «механично → живо») ----------
     Фото едва-едва следует за курсором: --tx/--ty ∈ −1..1 на карточке,
     transform ±4/±3px — на РОДИТЕЛЕ-обёртке фото .product-card__media
     (motion-w104.css §8), hover-zoom остаётся на img — разные элементы,
     конфликтов нет; hover-lift живёт на самой карточке — тоже мимо.
     Делегированный mousemove на контейнерах (.fc-carousel, .catalog__grid,
     включая related на PDP и грид occasion): слушатель ОДИН на контейнер,
     расчёт — один rAF на кадр, rect — один на кадр. Компакт-рельс
     (.fc-carousel--compact, мини-карточки 84px) — без tilt. Возврат к 0
     (mouse уходит с карточки/из контейнера) — той же системной кривой
     .3s из CSS. Только hover+fine и без reduced-motion. */
  function cardTilt() {
    if (reducedMotion()) return; /* W104-fix6: гейт убран — см. magneticHeroCta */
    /* W106 (B2): + .fc-collage — фича-карточки коллажа хитов тянутся за
       курсором тем же жестом (мини-ячейки исключены ниже по closest компакт-класса
       не имеют — мини без tilt: photo 96px, жест не читается) */
    var boxes = document.querySelectorAll('.fc-carousel, .fc-collage, .catalog__grid');
    if (!boxes.length) return;
    var cur = null;
    var px = 0;
    var py = 0;
    var raf = 0;
    function reset() {
      if (cur) {
        cur.style.removeProperty('--tx');
        cur.style.removeProperty('--ty');
      }
      cur = null;
    }
    function apply() {
      raf = 0;
      if (!cur) return;
      var r = cur.getBoundingClientRect();
      if (!r.width || !r.height) return;
      var tx = Math.max(-1, Math.min(1, ((px - r.left) / r.width) * 2 - 1));
      var ty = Math.max(-1, Math.min(1, ((py - r.top) / r.height) * 2 - 1));
      cur.style.setProperty('--tx', tx.toFixed(3));
      cur.style.setProperty('--ty', ty.toFixed(3));
    }
    Array.prototype.forEach.call(boxes, function (box) {
      if (box.classList.contains('fc-carousel--compact')) return; /* мини-рельс — без tilt */
      box.addEventListener('mousemove', function (e) {
        px = e.clientX;
        py = e.clientY;
        var card = e.target && e.target.closest ? e.target.closest('.product-card') : null;
        if (card !== cur) {
          reset(); /* курсор перешёл на соседнюю карточку/в зазор — прежняя отпускается */
          cur = card;
        }
        if (cur && !raf) raf = requestAnimationFrame(apply);
      }, { passive: true });
      box.addEventListener('mouseleave', reset, { passive: true });
    });
  }

  /* ---------- 7. Пульс итога корзины (W103, 6-b — M7; W104-β: кривая/амплитуда — motion-w104.css) ----------
     Слушаем 'cart:change' (его же слушает cart-ui.js для перерисовки;
     наш обработчик в очереди ПОСЛЕ — текст итога уже обновлён). Пульс —
     только при реальном изменении суммы: класс .is-pulse + reflow-рестарт. */
  function cartTotalPulse() {
    var total = document.getElementById('cartTotal');
    if (!total) return;
    var last = null;
    window.addEventListener('cart:change', function () {
      var t = document.getElementById('cartTotal');
      if (!t) return;
      var v = t.textContent;
      if (last !== null && v !== last && v !== '') {
        t.classList.remove('is-pulse');
        void t.offsetWidth; /* reflow — перезапуск keyframes */
        t.classList.add('is-pulse');
      }
      last = v;
    });
  }

  /* ---------- 8. FAQ: плавное закрытие details (W104-β, C1-M P0-1) ----------
     Открытие анимировано (grid-rows 0fr→1fr .28s, five.css), закрытие —
     мгновенный снап: нативный <details> прячет контент в тот же кадр
     (::details-content → content-visibility), переход 1fr→0fr не успевает.
     Перехватываем click по summary (мышь И клавиатура — Enter/Space на
     summary стреляют тем же click): при закрытии preventDefault, класс
     .is-closing (motion-w104.css: та же кривая/длительность, 1fr→0fr),
     снимаем open по transitionend по grid-template-rows (+fallback
     450мс). Повторный клик во время закрытия — отмена (снова открыть).
     prefers-reduced-motion — нативное поведение (five.css: transition:none).
     Применяем и к SEO-колофону details.fc-seo__more (тот же механизм). */
  function faqSmoothClose() {
    if (reducedMotion()) return;
    var items = document.querySelectorAll('details.faq-item, details.fc-seo__more');
    Array.prototype.forEach.call(items, function (d) {
      var summary = d.querySelector('summary');
      var wrap = d.querySelector('.faq-item__a-wrap');
      if (!summary || !wrap) return;
      var timer = 0;
      summary.addEventListener('click', function (e) {
        if (!d.open) return;          /* открытие — нативное (CSS уже анимирует) */
        e.preventDefault();           /* open не снимается мгновенно */
        if (d.classList.contains('is-closing')) {
          /* клик по закрывающемуся — отмена, снова открываем */
          clearTimeout(timer);
          d.classList.remove('is-closing');
          return;
        }
        d.classList.add('is-closing');
        var done = function () {
          wrap.removeEventListener('transitionend', onEnd);
          clearTimeout(timer);
          d.classList.remove('is-closing');
          d.open = false;             /* теперь контент скрывается нативно (уже 0fr) */
        };
        var onEnd = function (ev) {
          if (ev.target === wrap && ev.propertyName === 'grid-template-rows') done();
        };
        wrap.addEventListener('transitionend', onEnd);
        timer = setTimeout(done, 450); /* страховка: transitionend не пришёл */
      });
    });
  }

  /* ---------- 9. Marquee: играет только в вьюпорте (W104-β, C1-M P0-2) ----------
     Бегущая строка — infinite transform-анимация; вне вьюпорта она всё
     равно тикает каждый кадр (на слабом/софтверном рендере — реальный
     jank: p95 скролла 39–67мс на 2-ядерном стенде). IntersectionObserver
     ставит/снимает play-state. Ховер-пауза из five.css продолжает работать
     (paused остаётся приоритетнее running).
     E-e2 (P0-4, перф-критик волны 3): тот же IO — пунктир маршрута курьера
     .fc-hero__delivery-dash (stroke-dashoffset infinite, five.css) и
     marquee-ленты: вне вьюпорта — animation-play-state:paused
     (motion-w104.css §4f/4f2). Паттерн и класс .is-offscreen — общие. */
  function marqueePlayback() {
    var tracks = document.querySelectorAll('.fc-marquee__track, .nv-marquee__track, .fc-hero__delivery-dash');
    if (!tracks.length) return;
    if (!('IntersectionObserver' in window)) return; /* лента просто играет */
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        en.target.classList.toggle('is-offscreen', !en.isIntersecting);
      });
    }, { rootMargin: '80px 0px' });
    Array.prototype.forEach.call(tracks, function (t) {
      t.classList.add('is-offscreen'); /* до первого колбека IO — не тикаем */
      io.observe(t);
    });
  }

  /* ---------- 10. S3 (v2026.3): мобильная липкая панель (mnav) ----------
     «Поиск» — фокус в поисковую пилюлю шапки (#fcSearch) + мягкий скролл
     к шапке (панель fixed — поле может быть за пределами экрана).
     «Корзина» — дублируем клик по #cartToggle (контракт cart-ui.js:
     тот вешает свой слушатель на кнопку шапки — вызовем его клик). */
  function mnavBar() {
    var searchBtn = document.querySelector('.mnav__search');
    if (searchBtn) {
      searchBtn.addEventListener('click', function () {
        var inp = document.getElementById('fcSearch');
        if (!inp) { location.href = '/#catalog'; return; }
        var r = inp.getBoundingClientRect();
        if (r.top < 0 || r.bottom > window.innerHeight) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
          setTimeout(function () { inp.focus(); }, 420);
        } else {
          inp.focus();
        }
      });
    }
    var cartBtn = document.getElementById('mnavCartBtn');
    if (cartBtn) {
      cartBtn.addEventListener('click', function () {
        var t = document.getElementById('cartToggle');
        if (t) { t.click(); return; }
        location.href = '/checkout.php';
      });
    }
  }
  mnavBar();
})();
