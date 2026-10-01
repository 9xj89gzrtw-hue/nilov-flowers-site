/* Фильтрация каталога — ЕДИНЫЙ источник истины видимости карточек (W97-fixA A2).
   window.NfCatalogApply() пересчитывает видимость карточек #catalogGrid по ВСЕМ
   активным фильтрам страницы сразу (AND-комбинация):
     • вкладка категории — активная .catalog-tabs__tab (класс .is-hidden на карточке
       ставит таб-обработчик ниже — CSS .product-card.is-hidden{display:none});
     • чип цен          — .fc-chip.is-active (data-chip=hit/premium или data-min/data-max);
     • select цены      — #priceFilter (data-min/data-max выбранной опции);
     • поиск            — #fcSearch (рус. стемминг, матчит data-search карточки);
     • избранное        — #favToggle[aria-pressed="true"] + data-fav="1" на карточке
       (ставит nilov.js при клике по сердечку).
   Прочие скрипты (five.js — чипы/поиск, nilov.js — сердечки/тумблер) меняют только
   DOM-состояние этих элементов и зовут window.NfCatalogApply(); style.display
   карточек пишет один этот код. Раньше nilov.js прятал НЕ-избранное через
   style.display, а ценовой apply() перезаписывал его для всех прошедших цену —
   «Избранное» молча ломалось при смене цены. */
(function () {
  'use strict';

  /* S7: #catalogTabs больше нет (чипы/плашки бюджета заменяют вкладки) —
     фильтр живёт только при #catalogGrid (оболочка товарных полок).
     Селект полки — .fc-shelf внутри сетки: прячем целиком, когда после
     фильтров в ней не осталось видимых карточек. */
  var tabs = document.getElementById('catalogTabs');
  var grid = document.getElementById('catalogGrid');
  if (!grid) return;

  var cards = grid.querySelectorAll('.product-card');

  if (tabs) {
  tabs.addEventListener('click', function (e) {
    const tab = e.target.closest('.catalog-tabs__tab');
    if (!tab) return;

    tabs.querySelectorAll('.catalog-tabs__tab').forEach(function (t) {
      t.classList.remove('is-active');
      t.setAttribute('aria-pressed', 'false');
    });
    tab.classList.add('is-active');
    tab.setAttribute('aria-pressed', 'true');

    const categoryId = tab.dataset.categoryId;
    if (categoryId === 'all' && searchInp && searchInp.value !== '') {
      searchInp.value = '';
    }
    cards.forEach(function (card) {
      const matches = categoryId === 'all' || card.dataset.categoryId === categoryId;
      card.classList.toggle('is-hidden', !matches);
    });
    apply();
  });

  /* Deep-link: /?category=<id>#catalog */
  var categoryParam = new URLSearchParams(window.location.search).get('category');
  if (categoryParam) {
    const targetTab = Array.from(tabs.querySelectorAll('.catalog-tabs__tab')).find(function (t) {
      return t.dataset.categoryId === categoryParam;
    });
    if (targetTab) targetTab.click();
  }
  }

  /* K5 (W101): deep-link /?chip=hit|premium|low|mid|high#catalog — «Хиты продаж»
     (и прочие data-chip-ссылки) с вторичных страниц ведут на главную с включённым
     чипом (five.js rowLinks строит эту ссылку; аналог ?category= выше).
     Клик откладываем до DOMContentLoaded+microtask: чип-обработчик вешает
     five.js на своём ready — прямой .click() при парсинге сработал бы вхолостую.
     Найденный чип кликаем как руками: is-active + aria-pressed + общий
     re-apply + скролл к #catalog (плюс H9-синхронизация чип↔селект цены). */
  const chipParam = new URLSearchParams(window.location.search).get('chip');
  if (chipParam) {
    const activateChipParam = function () {
      const targetChip = Array.from(document.querySelectorAll('.fc-chip')).find(function (c) {
        return c.getAttribute('data-chip') === chipParam;
      });
      if (targetChip && !targetChip.classList.contains('is-active')) targetChip.click();
    };
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { setTimeout(activateChipParam, 0); });
    } else {
      setTimeout(activateChipParam, 0);
    }
  }

  /* ---------- ЕДИНЫЙ apply(): цена + чип + поиск + избранное + вкладка ---------- */

  const priceSel = document.getElementById('priceFilter');
  const priceCount = document.getElementById('priceFilterCount');
  const searchInp = document.getElementById('fcSearch');
  const favToggle = document.getElementById('favToggle');
  const emptyBox = document.getElementById('catalogEmpty');
  const emptyTitle = emptyBox ? emptyBox.querySelector('p') : null; /* первый <p> — заголовок empty */
  const chipsCount = document.querySelector('.fc-chips__count');

  function plural(n) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return 'букет';
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'букета';
    return 'букетов';
  }

  /* W96-fix1 (F3): базовый стемминг русского запроса — отрезаем типичные окончания,
     чтобы «розы» находило «Букет из роз», «пионы» — «пион», «маме» — «мам».
     W99-fixG2 (H10): стеммы карточки токенизируются в Set.
     A-b3 (4, копирайтер-критик): матчи по НАЧАЛУ СЛОВА (word-prefix): стемма
     запроса должна быть префиксом стеммы какого-то слова карточки —
     «ромашка» больше не находит «Романтику» (ромашк≠романтик…), зато
     «ромаш» находит «ромашки» (раньше точное сравнение стеммов теряло
     недонабранные запросы). + нормализация ё→е в обе стороны. */
  function stemRu(word) {
    var w = word.toLowerCase();
    var m = w.match(/^(.+?)(?:ами|ого|ому|ыми|ими|ая|ые|ое|ее|ой|ым|ых|ов|ей|ий|ый|ом|ем|ам|ах|иях|ях|ии|ие|ия|ью|ья|а|я|ы|и|у|ю|е|о)$/);
    return (m && m[1].length >= 3) ? m[1] : w;
  }
  /* Токены ≥3 символов (буквы/цифры) — «роз» остаётся токеном, а предлоги/«7» отпадают;
     ё→е: запрос «ёлка» и текст «ель»… — «ёлка»==«елка» в одной орфографии */
  function tokenize(text) {
    return String(text || '').toLowerCase().replace(/ё/g, 'е')
      .split(/[^a-zа-яё0-9]+/)
      .filter(function (t) { return t.length >= 3; });
  }
  function queryStems(q) {
    return tokenize(q).map(stemRu);
  }

  /* A-b3 (4): предрасчёт поискового индекса карточки — tokenize+stem дорожки
     data-search один раз на карточку (apply() гоняется на каждый keystroke,
     23 карточки × 500 символов пересчитывать не нужно). Кэш по элементу. */
  var cardStemCache = new Map();
  function cardStems(card) {
    var cached = cardStemCache.get(card);
    if (cached) return cached;
    var nameEl = card.querySelector('.product-card__name');
    var hay = card.getAttribute('data-search') || (nameEl ? nameEl.textContent : '');
    var stems = tokenize(hay).map(stemRu);
    cardStemCache.set(card, stems);
    return stems;
  }
  /* Стемма запроса матчится, если она — начало какой-то стеммы карточки
     (равенство — частный случай префикса). indexOf===0 вместо startsWith —
     на всякий случай для старых WebKit. */
  function stemPrefixMatch(stems, qw) {
    for (var i = 0; i < stems.length; i++) {
      if (stems[i].length >= qw.length && stems[i].indexOf(qw) === 0) return true;
    }
    return false;
  }

  /* Чип задаёт диапазон: data-min — исключительно («от M»), data-max — включительно
     («до N»). kind=hit/premium — по флагам карточки (data-hit / data-premium).
     S3 (v2026.3): kind=all — сброс (матчит всё); kind=tag-* — чип-тег.
     S5: многословный чип матчит ВСЕ слова по стему первых 4 символов
     («В коробках» → «в шляпных коробках», «Подарок» → «подарок девушке»);
     ИДЕНТИЧНО PHP-предподсчёту $nfTagMatch в index.php. */
  function nfTagMatch(chipTag, cardTags) {
    var ct = (chipTag || '').toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim();
    if (!ct) return false;
    var tags = (cardTags || '').toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim();
    if (!tags) return false;
    var chipWords = ct.split(/[\s,]+/);
    var tagWords = tags.split(/[\s,]+/);
    var w, i, j, stem, found;
    for (i = 0; i < chipWords.length; i++) {
      w = chipWords[i];
      if (!w) continue;
      stem = w.slice(0, 4);
      found = false;
      for (j = 0; j < tagWords.length; j++) {
        if (tagWords[j] && tagWords[j].indexOf(stem) === 0) { found = true; break; }
      }
      if (!found) return false;
    }
    return chipWords.length > 0;
  }

  function chipMatch(card, chip) {
    var kind = chip.getAttribute('data-chip');
    if (kind === 'all') return true;
    if (kind === 'hit') return card.getAttribute('data-hit') === '1';
    if (kind === 'premium') return card.getAttribute('data-premium') === '1';
    /* S9: плашки «Монобукеты» / «Хиты и подарки» — тег читается из data-tag
       самого чипа (настройка budget_tile_*_tag из PHP): счётчик на плашке
       и этот матчер всегда согласованы. hitgift = флаг хита ИЛИ тег. */
    if (kind === 'mono') {
      return nfTagMatch(chip.getAttribute('data-tag') || 'монобукеты', card.getAttribute('data-tags') || '');
    }
    if (kind === 'hitgift') {
      return card.getAttribute('data-hit') === '1'
        || nfTagMatch(chip.getAttribute('data-tag') || 'подарки', card.getAttribute('data-tags') || '');
    }
    if (kind && kind.indexOf('tag-') === 0) {
      return nfTagMatch(chip.getAttribute('data-tag') || '', card.getAttribute('data-tags') || '');
    }
    var price = parseInt(card.getAttribute('data-price'), 10) || 0;
    var min = chip.hasAttribute('data-min') ? parseInt(chip.getAttribute('data-min'), 10) : null;
    var max = chip.hasAttribute('data-max') ? parseInt(chip.getAttribute('data-max'), 10) : null;
    return (min === null || price > min) && (max === null || price <= max);
  }

  /* Избранное: data-fav="1" на карточке (ставит nilov.js); до его deferred-инициализации
     фолбэк — активное сердечко внутри карточки. */
  function cardFav(card) {
    if (card.getAttribute('data-fav') === '1') return true;
    var b = card.querySelector('.product-card__fav');
    return !!(b && b.classList.contains('is-active'));
  }

  function apply() {
    var chip = document.querySelector('.fc-chip.is-active');
    /* S3 (v2026.3): ни один чип не активен (сброс пустым кликом/кнопкой
       «Сбросить фильтры») — активируем «Все», пустого состояния чипов
       больше не бывает (принцип 5cv). */
    if (!chip) {
      var allChip = document.querySelector('.fc-chip[data-chip="all"]');
      if (allChip) {
        allChip.classList.add('is-active');
        allChip.setAttribute('aria-pressed', 'true');
        chip = allChip;
      }
    }
    var min = null, max = null;
    if (priceSel && priceSel.selectedIndex >= 0) {
      var opt = priceSel.options[priceSel.selectedIndex];
      min = opt.hasAttribute('data-min') ? parseInt(opt.getAttribute('data-min'), 10) : null;
      max = opt.hasAttribute('data-max') ? parseInt(opt.getAttribute('data-max'), 10) : null;
    }
    /* K3 (W101): активен ли ЦЕНОВОЙ фильтр (чип с диапазоном ИЛИ селект) —
       для честного empty-текста «не нашлось в выбранном диапазоне цен». */
    var priceChipActive = !!(chip && (chip.hasAttribute('data-min') || chip.hasAttribute('data-max')));
    var priceActive = priceChipActive || min !== null || max !== null;
    var favOn = !!(favToggle && favToggle.getAttribute('aria-pressed') === 'true');
    var rawQ = searchInp ? (searchInp.value || '') : '';
    var q = rawQ.trim().toLowerCase();
    var rawStems = q ? queryStems(q) : [];
    /* H10: длинные стеммы (≥3) матчатся ТОЧНО по Set стеммов карточки;
     короткий запрос целиком (напр. «7») — прежний подстрочный fallback */
    var stems = rawStems.filter(function (w) { return w.length >= 3; });

    /* W105-8fix1 (критик-UX 8-b P1#2): видимый индикатор активного поиска —
       пилюля «Поиск: «розы» ✕» у шапки каталога (разметка — index.php).
       Раньше сетка молча редела до 11/23: ни подсказки, ни выхода (сброс
       жил только внутри скрытого empty-state). Синхронно с apply(), чтобы
       любое изменение поля (ввод/«Все»/сброс/глубокая ссылка ?q=) её вело. */
    var searchChip = document.getElementById('catalogSearchChip');
    var searchChipLabel = document.getElementById('catalogSearchChipLabel');
    if (searchChip && searchChipLabel) {
      if (q !== '') {
        searchChipLabel.textContent = 'Поиск: «' + rawQ.trim() + '»';
        searchChip.setAttribute('aria-label', 'Сбросить поиск «' + rawQ.trim() + '» — показать все букеты');
        searchChip.hidden = false;
      } else {
        searchChip.hidden = true;
      }
    }

    /* W96-fix1 (F6): считаем и скрываем ТОЛЬКО карточки каталога (#catalogGrid) —
       селектор .product-card зацепил бы и карусели секций (хиты/премиум/…). */
    var visible = 0;
    grid.querySelectorAll('.product-card').forEach(function (card) {
      var ok = true;
      /* вкладка категории (is-hidden от таб-обработчика выше) */
      if (card.classList.contains('is-hidden')) ok = false;
      /* чип */
      if (ok && chip && !chipMatch(card, chip)) ok = false;
      /* цена (select) */
      if (ok && (min !== null || max !== null)) {
        var price = parseInt(card.getAttribute('data-price'), 10) || 0;
        if ((min !== null && price < min) || (max !== null && price > max)) ok = false;
      }
      /* Поиск (A-b3 4): word-prefix по предрасчитанному индексу (имя+категория+
         описание из data-search). КАЖДАЯ стемма запроса (≥3 симв.) должна быть
         началом какого-то слова карточки — «ромашка» честно находит только
         «Полевые цветы» (там есть ромашки), «Романтика» не всплывает. */
      if (ok && q !== '' && stems.length) {
        var cardStemList = cardStems(card);
        ok = stems.every(function (qw) { return stemPrefixMatch(cardStemList, qw); });
      }
      /* избранное */
      if (ok && favOn && !cardFav(card)) ok = false;

      card.style.display = ok ? '' : 'none';
      if (ok) visible++;
    });

    /* S7: товарные полки — прячем целиком, если после всех фильтров в полке
       не осталось видимых карточек (заголовок без товаров — мусор). */
    grid.querySelectorAll('.fc-shelf').forEach(function (shelf) {
      var shelfCards = shelf.querySelectorAll('.product-card');
      var anyVisible = false;
      Array.prototype.forEach.call(shelfCards, function (c) {
        if (c.style.display !== 'none') { anyVisible = true; }
      });
      shelf.hidden = !anyVisible;
    });

    /* Счётчики — ОБА показывают одно число (W96-fix3b D9): у select цены и у чипов.
       H6 (W99-fixG2): при активном чипе + поиске подпись чипа сохраняет контекст
       запроса — «N букетов по запросу «…»» (раньше ветка запроса затиралась числом). */
    if (priceCount) priceCount.textContent = visible + ' ' + plural(visible);
    if (chipsCount) {
      if (chip) {
        chipsCount.textContent = q !== ''
          ? visible + ' ' + plural(visible) + ' по запросу «' + rawQ.trim() + '»'
          : visible + ' ' + plural(visible);
      } else if (q !== '') {
        chipsCount.textContent = 'по запросу «' + rawQ.trim() + '» — ' + visible + ' ' + plural(visible);
      } else {
        chipsCount.textContent = '';
      }
    }

    /* Empty-state: 0 видимых → подсказка + сброс. Заголовок честный:
       «Избранное» совсем без лайков — про избранное (A2); лайки есть, но их
       вырезал другой фильтр (цена/вкладка/поиск) — про фильтры, не «пусто»;
       K3 (W101): поиск × активная цена — отдельная кнопка «Сбросить цену»
       (снимает ТОЛЬКО цену, запрос/категорию/избранное не трогает);
       C-c4 (CRO P0 «поиск-тупик»): поиск без совпадений — заголовок
       «Ничего не нашлось» + строка «Опишите, какой букет нужен — соберём
       на заказ» + CTA «Собрать на заказ» (скролл к #order — якорный
       клик перехватывает kinetic.js: lenis + offset −96; на вторичных
       страницах — /#order). Разметка #catalogEmpty — в index.php (чужая
       зона), блок собирается здесь целиком, живёт до первого сброса. */
    if (emptyBox) {
      emptyBox.hidden = visible > 0;
      var searchEmpty = q !== '' && visible === 0;
      if (emptyTitle) {
        if (!emptyTitle.dataset.origTitle) emptyTitle.dataset.origTitle = emptyTitle.textContent;
        if (favOn && visible === 0) {
          var anyFav = !!grid.querySelector('.product-card[data-fav="1"]');
          emptyTitle.textContent = anyFav
            ? 'В избранном нет букетов по этим фильтрам — попробуйте вернуть цену или категорию'
            : 'В избранном пока пусто — нажмите ♡ на букете в каталоге';
        } else if (searchEmpty) {
          emptyTitle.textContent = 'Ничего не нашлось';
        } else {
          emptyTitle.textContent = emptyTitle.dataset.origTitle;
        }
      }
      /* C-c4: CTA «Собрать на заказ» — один раз собираем нод, дальше только
         вкл/выкл; серверная подсказка (2-й прямой <p>: «сбросьте цену…»)
         в сценарии поиска прячем — она не про запрос */
      var orderBlock = document.getElementById('catalogEmptyOrder');
      if (!orderBlock) {
        var emptyHintP = null;
        Array.prototype.forEach.call(emptyBox.children, function (child) {
          if (!emptyHintP && child.tagName === 'P' && child !== emptyTitle) emptyHintP = child;
        });
        orderBlock = document.createElement('div');
        orderBlock.id = 'catalogEmptyOrder';
        orderBlock.style.cssText = 'margin:2px 0 16px';
        var orderHint = document.createElement('p');
        orderHint.style.cssText = 'color:var(--ink-soft,#6e6a72);font-size:.9rem;margin:0 0 16px';
        orderHint.textContent = 'Опишите, какой букет нужен — соберём на заказ';
        var orderCta = document.createElement('a');
        orderCta.className = 'btn btn--accent';
        orderCta.style.marginRight = '10px';
        orderCta.textContent = 'Собрать на заказ';
        orderCta.href = document.getElementById('order') ? '#order' : '/#order';
        orderBlock.appendChild(orderHint);
        orderBlock.appendChild(orderCta);
        if (emptyTitle && emptyTitle.nextSibling) {
          emptyBox.insertBefore(orderBlock, emptyTitle.nextSibling);
        } else {
          emptyBox.insertBefore(orderBlock, emptyBox.firstChild);
        }
        orderBlock._nfHintP = emptyHintP; /* прячем/показываем вместе с блоком */
      }
      orderBlock.hidden = !searchEmpty;
      if (orderBlock._nfHintP) orderBlock._nfHintP.hidden = searchEmpty;
      /* K3 (W101): кнопка «Сбросить цену» — создаётся один раз, показывается
         только в сценарии поиск × цена (иначе hidden). */
      var resetPriceBtn = document.getElementById('catalogEmptyResetPrice');
      var showResetPrice = visible === 0 && q !== '' && priceActive;
      if (showResetPrice && !resetPriceBtn) {
        resetPriceBtn = document.createElement('button');
        resetPriceBtn.type = 'button';
        resetPriceBtn.id = 'catalogEmptyResetPrice';
        resetPriceBtn.className = 'btn btn--outline';
        resetPriceBtn.style.marginRight = '10px';
        resetPriceBtn.textContent = 'Сбросить цену';
        resetPriceBtn.addEventListener('click', function () {
          if (priceSel && priceSel.value !== 'all') priceSel.value = 'all';
          /* снимаем только ценовые чипы (data-min/data-max); hit/premium,
             запрос, категорию и избранное не трогаем */
          document.querySelectorAll('.fc-chip.is-active').forEach(function (c) {
            if (c.hasAttribute('data-min') || c.hasAttribute('data-max')) {
              c.classList.remove('is-active');
              c.setAttribute('aria-pressed', 'false');
            }
          });
          apply();
        });
        var mainResetBtn = document.getElementById('catalogEmptyReset');
        if (mainResetBtn && mainResetBtn.parentNode) {
          mainResetBtn.parentNode.insertBefore(resetPriceBtn, mainResetBtn);
        }
      }
      if (resetPriceBtn) resetPriceBtn.hidden = !showResetPrice;
    }

    /* Совместимость: событие «фильтры применились» (число видимых) — как делал
       five.js в W96-fix3b (D9); W99-fixG2 (H2): five.js подписан на него —
       пилюля поиска «Нашлось N» обновляется и сбросом «Сбросить фильтры». */
    try {
      window.dispatchEvent(new CustomEvent('fc:filter', { detail: { visible: visible } }));
    } catch (err) { /* старые браузеры без CustomEvent-конструктора — молча */ }

    /* W105-6fix1: хвост ряда — последняя видимая карточка/CTA-плитка
       (js/grid-tail.js, грузится в footer.php до этого файла) */
    if (window.NfGridTail) window.NfGridTail(grid);
    return visible;
  }

  /* Публичный re-apply: его зовут five.js (чип/поиск/сброс) и nilov.js (сердечки) */
  window.NfCatalogApply = apply;

  /* W105-8fix1: пилюля активного поиска — клик снимает запрос и
     пересчитывает каталог (apply() разошлёт fc:filter — пилюля five.js
     «Нашлось N» спрячется тем же путём) */
  (function () {
    var chipBtn = document.getElementById('catalogSearchChip');
    if (!chipBtn) return;
    chipBtn.addEventListener('click', function () {
      if (searchInp && searchInp.value !== '') searchInp.value = '';
      apply();
    });
  })();

  if (priceSel) priceSel.addEventListener('change', function () {
    /* W102 (UX-критик): двусторонний sync — смена селекта переключает ценовой чип
       (раньше чип оставался is-active → пустое пересечение и «не нашлось»). */
    var v = priceSel.value;
    document.querySelectorAll('.fc-chips [data-chip]').forEach(function (chip) {
      if (!chip.hasAttribute('data-min') && !chip.hasAttribute('data-max')) return; /* «Хиты» и пр. не трогаем */
      var match = v !== 'any' && (chip.getAttribute('data-chip') || '') === v;
      chip.classList.toggle('is-active', match);
      chip.setAttribute('aria-pressed', match ? 'true' : 'false');
    });
    apply();
  });

  /* H9 (W99-fixG2): клик по ценовому чипу синхронизирует select «Цена:» —
     «До 3 500 ₽» ставит селект в ту же опцию (match по data-min/data-max —
     пороги чипов и селекта приходят из настроек и совпадают), снятие чипа
     возвращает «Любая». Хиты/Премиум ценового эквивалента не имеют — их не
     трогаем. Слушатель на .fc-chips срабатывает ПОСЛЕ чип-обработчика five.js
     (target-фаза раньше bubbling) — состояние is-active уже актуально;
     после синхронизации пересчитываем каталог одним apply(). */
  var chipsBoxEl = document.querySelector('.fc-chips');
  if (chipsBoxEl && priceSel) {
    chipsBoxEl.addEventListener('click', function (e) {
      var chipEl = e.target.closest ? e.target.closest('.fc-chip') : null;
      if (!chipEl) return;
      var kind = chipEl.getAttribute('data-chip');
      if (kind === 'hit' || kind === 'premium') return;
      if (!chipEl.classList.contains('is-active')) {
        /* чип только что сняли — селект больше не должен держать его диапазон */
        if (priceSel.value !== 'all') { priceSel.value = 'all'; apply(); }
        return;
      }
      var cMin = chipEl.hasAttribute('data-min') ? chipEl.getAttribute('data-min') : '';
      var cMax = chipEl.hasAttribute('data-max') ? chipEl.getAttribute('data-max') : '';
      var matched = false;
      Array.prototype.forEach.call(priceSel.options, function (o) {
        var oMin = o.hasAttribute('data-min') ? o.getAttribute('data-min') : '';
        var oMax = o.hasAttribute('data-max') ? o.getAttribute('data-max') : '';
        if (oMin === cMin && oMax === cMax) { priceSel.value = o.value; matched = true; }
      });
      if (matched) apply();
    });
  }

  /* Кнопка сброса (empty-state): снимает ВСЕ фильтры — цену, категорию,
     избранное, чип и поиск — и пересчитывает каталог одним apply(). */
  var resetBtn = document.getElementById('catalogEmptyReset');
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      if (priceSel) priceSel.value = 'all';
      if (tabs) {
        var allTab = tabs.querySelector('.catalog-tabs__tab[data-category-id="all"]');
        if (allTab && !allTab.classList.contains('is-active')) {
          allTab.click(); /* таб-обработчик сам снимет is-hidden и позовёт apply() */
        } else {
          cards.forEach(function (card) { card.classList.remove('is-hidden'); });
        }
      } else {
        cards.forEach(function (card) { card.classList.remove('is-hidden'); });
      }
      if (favToggle) {
        favToggle.classList.remove('is-on');
        favToggle.setAttribute('aria-pressed', 'false');
      }
      document.querySelectorAll('.fc-chip.is-active').forEach(function (c) {
        c.classList.remove('is-active');
        c.setAttribute('aria-pressed', 'false');
      });
      if (searchInp) searchInp.value = '';
      apply();
    });
  }

  /* Стартовый расчёт видимости/счётчиков (до deferred nilov.js и DOMContentLoaded
     five.js — оба пере-позовут apply() после своей инициализации) */
  apply();
})();
