<?php
/* ФОРМА ЗАКАЗА — partial (W106-E1, Нильсен P0 «чекаут заперт в лендинге»):
   извлечена из index.php 1:1 (та же разметка, те же id полей — контракт
   js/order-form.js сохранён), подключается на главной (прежнее место,
   поведение главной НЕ меняется) и на /checkout.php.

   Переменные, которые форма берёт из окружения (index.php считает их выше):
     $zones             — delivery_zones (id, name, price); нет — запросим сами;
     $featDeliverySlots — тумблер feature_delivery_slots (слоты даты/времени);
     $featGiftFields    — тумблер feature_gift_fields (получатель + открытка);
     $orderHideTitle    — true на /checkout.php: H2 «Оформление заказа» не
                          печатается (его роль несёт page-hero H1 чекаута).
   D-d1: три смысловые группы с легендами («Как получить» → «Кто получит» →
   «Ваши контакты» → оплата) — DOM-порядок, id полей НЕ менялись.
   W106-E1 (Нильсен P0, три волны жалоб): «Адрес доставки» ВИДИМ ВСЕГДА —
   при «Самовывоз» поле приглушено (disabled + aria-disabled + плейсхолдер
   «Для самовывоза адрес не нужен»), при выборе района — активно. Логику
   enable/disable ведёт js/order-form.js (syncDeliveryAddressRequirement). */
declare(strict_types=1);

if (!isset($zones)) {
    try {
        $zones = db()->query('SELECT id, name, price FROM delivery_zones ORDER BY sort, id')->fetchAll();
    } catch (Throwable $e) {
        $zones = [];
    }
}
$featDeliverySlots = $featDeliverySlots ?? (setting('feature_delivery_slots', '0') === '1');
$featGiftFields = $featGiftFields ?? (setting('feature_gift_fields', '1') === '1');
?>
  <section class="fc-section fc-section--subtle" id="order">
    <div class="wrap">
      <?php if (empty($orderHideTitle)): ?>
      <h2 class="section-title"><?= e(setting('order_title', 'Оформление заказа')) ?></h2>
      <?php endif; ?>
      <p class="order__selected" id="orderSelected"></p>
      <?php /* W98-fixE (E14): пустая корзина на #order — заглушка-подсказка. Скрыта
         по умолчанию (display:none); показывает js-волна при пустой корзине.
         G-g2 (редактор P0 «сабмит без товара»): формулировка-действие «Сначала
         выберите букет — загляните в каталог» + кнопка работает и на /checkout
         (абсолютный /#catalog — на вторичной странице якоря #catalog нет).
         Сабмит-валидация пустой корзины — js/order-form.js (не тронуто):
         статус «Корзина пуста — добавьте букет из каталога» при гонке состояний;
         сама форма при пустой корзине скрыта (cart-ui syncOrderEmptyState). */ ?>
      <?php
      $__orderPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
      $__orderCatalogHref = ($__orderPath === '/' || $__orderPath === '/index.php') ? '#catalog' : '/#catalog';
      ?>
      <div id="orderEmptyState" style="display:none;text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px;margin-bottom:18px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px">Сначала выберите букет — загляните в каталог</p>
        <p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px">В каталоге — свежие букеты на любой бюджет</p> <?php /* W104-λ: убрано «с утренней поставки» — эхо seo-абзаца ниже */ ?>
        <a class="btn btn--outline" href="<?= e($__orderCatalogHref) ?>">Перейти в каталог</a>
      </div>
      <?php /* D-d1 (P0-4, маркетолог + покупатель 55+): 13 полей без группировки →
         ТРИ смысловые группы с типографскими легендами (не fieldset-рамки):
         «Как получить» (район/адрес/дата/интервал) → «Кто получит» (получатель/
         телефон/открытка) → «Ваши контакты» (имя/телефон/email/комментарий) →
         оплата. Переставлен только DOM-порядок: id полей НЕ менялись —
         js/order-form.js (валидация, маска +7, чипы дат, ошибки) читает по id. */ ?>
      <form class="order-form" id="orderForm" novalidate>
        <fieldset class="order-form__nested order-form__group" id="delivery">
          <legend><?= e(setting('fieldset_delivery_legend', 'Как получить')) ?></legend>
          <div class="order-form__field">
            <label for="orderDeliveryZone"><?= e(setting('form_delivery_zone_label', 'Район доставки')) ?></label>
            <select id="orderDeliveryZone" name="delivery_zone">
              <?php
              /* Самовывоз — приоритетный способ: выбран по умолчанию, бесплатно.
                 Критик-мобайл (баг 4): полный адрес самовывоза — своя настройка pickup_address
                 (с фолбэком на shop_address), иначе город без улицы. */
              $pickupAddr = trim(setting('pickup_address', '')) ?: trim(setting('shop_address', ''));
              ?>
              <option value="0" data-price="0" selected><?= e(setting('pickup_option_text', 'Самовывоз · 0' . "\u{00A0}" . '₽')) ?></option>
              <?php /* W70 (obvious-витрина NEW-1): native select режет длинные имена зон на 390
                     (366px в 220px-бокс без эллипсиса). Корень — метка: «район»→«р-н",
                     пояснение в скобках и цена уходят в title/hint (цена есть в «К оплате»). */
                 foreach ($zones as $z):
                 $zFull = (string)$z['name'];
                     $zShort = trim(str_replace([' район ', ' район'], [' р-н ', ' р-н'], $zFull));
                     $zShort = trim((string)preg_replace('/\s*\([^)]*\)/u', '', $zShort));
                     ?>
                <?php /* W97-fixB2 (B2-1): цена зоны в тексте опции — как у «Самовывоз · 0 ₽»
                       (formatPrice — тысячи через пробел); value/data-price НЕ трогаем:
                       js/order-form.js берёт data-price, value=id зоны. Имена зон короткие,
                       W70-сокращение (р-н) сохранено. */ ?>
                <option value="<?= (int)$z['id'] ?>" data-price="<?= (int)$z['price'] ?>" title="<?= e($zFull) ?> · <?= (int)$z['price'] ?> ₽"><?= e($zShort . ' · ' . formatPrice((int)$z['price'])) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($pickupAddr !== ''): ?>
            <p class="order-form__hint" id="orderPickupAddr" style="margin-top:6px">Адрес самовывоза: <?= e($pickupAddr) ?> · соберём за 1–2 часа, предупредим по телефону</p> <?php /* W106-E1: единая SLA-формула (было «готов в течение дня») */ ?>
            <?php endif; ?>
          </div>
          <?php /* W106-E1 (Нильсен P0, три волны): поле «Адрес доставки» видно
                 ВСЕГДА — при самовывозе приглушено (класс вешает JS
                 syncDeliveryAddressRequirement: disabled + aria-disabled +
                 плейсхолдер «Для самовывоза адрес не нужен»), при выборе
                 района — активно. Было hidden-до-выбора: покупатель не
                 понимал, появится ли адрес вообще. */ ?>
          <div class="order-form__field order-form__field--muted" id="orderDeliveryAddressField">
            <label for="orderDeliveryAddress">Адрес доставки</label>
            <input type="text" id="orderDeliveryAddress" name="delivery_address" placeholder="Для самовывоза адрес не нужен" autocomplete="street-address" disabled aria-disabled="true">
            <span class="order-form__error" id="orderDeliveryAddressError"></span>
          </div>
          <?php if ($featDeliverySlots): ?>
          <?php /* Критик functional top#1: слоты даты/времени вместо «договоримся по телефону».
                 D-d1: поля переехали в группу «Как получить» (легенда одна). */ ?>
          <div class="order-form__field">
            <label for="orderDeliveryDate">Дата</label>
            <?php /* W96-fix4: min=today — не даём выбрать прошлое до отправки (сервер валидирует повторно).
                   W97-fixB2 (B2-2): max=today+60 — нативный календарь не предлагает бесконечное будущее.
                   D-d1 (P1, жюри): aria-label — «Дата доставки» (видимое «Дата» коротко, а
                   скринридер должен называть поле целиком). */ ?>
            <input type="date" id="orderDeliveryDate" name="delivery_date" aria-label="Дата доставки" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>">
            <span class="order-form__error" id="orderDeliveryDateError"></span>
          </div>
          <div class="order-form__field">
            <label for="orderDeliverySlot">Интервал</label>
            <select id="orderDeliverySlot" name="delivery_slot">
              <option value="">Любое время дня</option>
              <?php foreach (array_filter(array_map('trim', explode("\n", setting('delivery_slots', "Утро 9:00–14:00\nДень 14:00–18:00\nВечер 18:00–22:00")))) as $slotOption): ?>
                <option value="<?= e($slotOption) ?>"><?= e($slotOption) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <?php /* D-d1 (P1, конкурент): «в течение дня» → честный интервал —
                 точное время назовёт курьер. Логику видимости (скрыт при выбранной
                 зоне) ведёт js/order-form.js по id — не трогаем. */ ?>
          <p class="order-form__hint order-form__group-hint" id="orderDeliveryHint"><?= e(setting('delivery_hint_text', 'Доставим сегодня в выбранный интервал — точное время согласует курьер')) ?></p>
        </fieldset>
        <?php if ($featGiftFields): ?>
        <?php /* Критик functional (gift-UX): цветы дарят — кому и что написать на открытке.
                 Все поля необязательные; пустые просто не попадают в заказ.
                 D-d1: группа «Кто получит» идёт второй — поток подарка (куда → кому → от кого). */ ?>
        <fieldset class="order-form__nested order-form__group">
          <legend><?= e(setting('fieldset_recipient_legend', 'Кто получит')) ?></legend>
          <div class="order-form__field">
            <label for="orderRecipientName">Имя получателя</label>
            <input type="text" id="orderRecipientName" name="recipient_name" maxlength="120" autocomplete="off" placeholder="Например: Анна">
          </div>
          <div class="order-form__field">
            <label for="orderRecipientPhone">Телефон получателя</label>
            <?php /* D-d1 (P1, 55+): inputmode=tel — цифровая клавиатура на мобиле */ ?>
            <input type="tel" id="orderRecipientPhone" name="recipient_phone" autocomplete="off" inputmode="tel" placeholder="+7 (___) ___-__-__">
            <span class="order-form__error" id="orderRecipientPhoneError"></span>
            <p class="order-form__hint">Курьер позвонит получателю, а не вам</p>
          </div>
          <div class="order-form__field order-form__field--full">
            <label for="orderCardText">Текст открытки</label>
            <textarea id="orderCardText" name="card_text" rows="2" maxlength="500" placeholder="С днём рождения! — от Евгения"></textarea>
            <p class="order-form__hint">Напишем от руки и вложим в букет · бесплатно</p>
          </div>
        </fieldset>
        <?php endif; ?>
        <fieldset class="order-form__nested order-form__group">
          <legend><?= e(setting('fieldset_contacts_legend', 'Ваши контакты')) ?></legend>
          <div class="order-form__field">
            <label for="orderName"><?= e(setting('form_name_label', 'Ваше имя *')) ?></label>
            <input type="text" id="orderName" name="name" autocomplete="name" required minlength="2">
            <span class="order-form__error" id="orderNameError"></span>
          </div>
          <div class="order-form__field">
            <?php /* G-g2 (CRO P1 «Телефон␣» — висячий пробел): NBSP клеит
                  звёздочку к слову — «*» не переносится на новую строку
                  одиночным символом (как «Ваше имя *»); rtrim страхует от
                  хвостового пробела в кастомном form_phone_label. */ ?>
            <label for="orderPhone"><?= e(rtrim(setting('form_phone_label', 'Телефон'))) ?>&nbsp;<span aria-hidden="true">*</span></label>
            <?php /* W106 (B2, задача 10): required + inputmode=tel — телефон обязателен
                  для подтверждения заказа; JS-валидацию ведёт order-form.js (не трогаем),
                  здесь только нативные атрибуты. */ ?>
            <input type="tel" id="orderPhone" name="phone" autocomplete="tel" inputmode="tel" required placeholder="+7 (___) ___-__-__">
            <span class="order-form__error" id="orderPhoneError"></span>
          </div>
          <div class="order-form__field">
            <?php /* W106-E1 (корректор + Нильсен P0): у Email были скрытые подписи
                   «* обязательно для онлайн-оплаты» и «На этот адрес придёт чек
                   об оплате» — онлайн-оплаты нет, обещание противоречило
                   действительности. Одна нейтральная строка без звёздочки;
                   при включённой ЮKassa текст подменяет order-form.js
                   (syncEmailRequirement). */ ?>
            <label for="orderEmail">Email</label>
            <input type="email" id="orderEmail" name="email" autocomplete="email" placeholder="example@mail.ru">
            <span class="order-form__hint" id="orderEmailHint">Необязательно — пришлём фото букета и статус доставки</span>
            <span class="order-form__error" id="orderEmailError"></span>
          </div>
          <p class="order-form__hint order-form__group-hint" id="contactHint">Укажите телефон и/или email — как удобнее для связи</p>
          <div class="order-form__field order-form__field--full">
            <label for="orderComment">Комментарий</label>
            <textarea id="orderComment" name="comment" rows="3" placeholder="Цветовые пожелания, подъезд, домофон"></textarea>
          </div>
        </fieldset>
        <fieldset class="order-form__payment">
          <legend><?= e(setting('fieldset_payment_legend', 'Способ оплаты')) ?></legend>
          <?php
          /* Fail-safe: «онлайн» показываем только если ЮKassa реально настроена
             (галочка + ключи). Иначе покупатель увидит несбыточное обещание. */
          $ykLive = setting('yk_enabled', '0') === '1'
              && trim(setting('yk_shop_id', '')) !== ''
              && trim(setting('yk_secret_key', '')) !== '';
          ?>
          <?php if ($ykLive): ?>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="online" checked><span><?= e(setting('pay_online_label', 'Картой или через СБП — сразу онлайн')) ?></span></label>
          <label class="order-form__radio"><input type="radio" name="payment_method" value="cash"><span><?= e(setting('pay_cash_label', 'Наличными или картой при получении')) ?></span></label>
          <p class="order-form__hint">Оплата проходит на защищённой странице ЮKassa. Данные карты магазину не передаются.</p>
          <?php else: ?>
          <?php /* W106 (B2, задача 10): единственный способ — тихая подпись вместо
                   одиночного radio (визуального «выбора нет»); значение payment_method
                   для API держит hidden-инпут (order-form.js читает radio → hidden —
                   контракт сохранён, дублей имени нет). */ ?>
          <input type="hidden" name="payment_method" value="cash">
          <p class="order-form__hint order-form__pay-note">Оплата — курьеру при получении: наличными или картой.</p>
          <?php endif; ?>
          <?php /* G-g2 (редактор P1 «промокод применяется в корзине — путает»):
                 промо-строка в самой форме — фраза сводки /checkout «Промокод
                 можно указать ниже, в форме» теперь правда. Без name — код в
                 заказ уходит из window.PROMO_STATE (order-form.js, не тронуто);
                 проверка/применение — js/cart-ui.js (тот же /api/promo —
                 единый источник PROMO_STATE с drawer-полем корзины). */ ?>
          <?php if (setting('feature_promo', '1') === '1'): ?>
          <div class="order-form__field order-form__promo">
            <label for="orderPromoInput">Промокод</label>
            <div class="order-form__promo-row">
              <input type="text" id="orderPromoInput" maxlength="32" placeholder="Например: NILOV" autocomplete="off" aria-describedby="orderPromoMsg">
              <button type="button" class="btn btn--outline" id="orderPromoApply">Применить</button>
            </div>
            <span class="order-form__hint" id="orderPromoMsg" aria-live="polite"></span>
          </div>
          <?php endif; ?>
        </fieldset>
        <?php /* Honeypot (критик security): невидимое поле — боты заполняют, люди нет.
               W106-E1 (Нильсен P0): контейнер несёт aria-hidden + inert, input —
               tabindex=-1 + autocomplete=off; CSS-сокрытие — style.css .hp-field +
               страховка в five.css (двойной слой: правки style.css параллельными
               волнами не «раскрывают» поле). */ ?>
        <div class="hp-field" aria-hidden="true" inert><label for="orderCompanyWebsite">Сайт компании</label><input type="text" id="orderCompanyWebsite" name="company_website" tabindex="-1" autocomplete="off"></div>
        <label class="order-form__checkbox">
          <input type="checkbox" id="orderPdConsent" name="pd_consent" required>
          <?php /* W106 (B2, бизнес-критик P1): юридическая простыня 500+ символов →
             короткое согласие; ПОЛНЫЕ тексты живут на /policy и /offer (ссылки
             открываются в новой вкладке — заполненную форму не потерять).
             W106-E1 (корректор P0): согласие ОБЯЗАТЕЛЬНО, как имя и телефон —
             звёздочка в строке согласия честно совпадает со счётчиком кнопки
             «Заполните 3 поля» (раньше звёздочки виднелись только у 2 полей). */ ?>
          <span>Согласен(а) с <a href="/policy" target="_blank" rel="noopener">Политикой обработки персональных данных</a> и <a href="/offer" target="_blank" rel="noopener">Офертой</a> <span aria-hidden="true" style="color:var(--rose-cta,#AE4A71);font-weight:600">*</span></span>
        </label>
        <span class="order-form__error" id="orderPdConsentError"></span>
        <p class="order-form__total" id="orderTotal"></p>
        <button type="submit" class="btn btn--accent order-form__submit" id="orderSubmit"
                data-pay-label="<?= e(setting('submit_button_text', 'Оплатить заказ')) ?>"
                data-nopay-label="<?= e(setting('submit_nopay_text', 'Отправить заказ')) ?>"><?= $ykLive ? e(setting('submit_button_text', 'Оплатить заказ')) : e(setting('submit_nopay_text', 'Отправить заказ')) ?></button>
        <p class="order-form__hint"><?= e(sprintf('Заказы до %s — доставим сегодня; после %s — привезём завтра с утра.', setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT), setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT))) ?></p>
        <p class="order-form__status" id="orderStatus" role="status" hidden></p>
      </form>
    </div>
  </section>
