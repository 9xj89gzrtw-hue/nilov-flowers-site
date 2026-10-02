<?php
/* ФОРМА ЗАКАЗА (S11 ЗОНА 2 — чекаут 2026): partial переработан в
   ПЯТЬ ПОНЯТНЫХ ШАГОВ оформления по ТЗ владельца:
     Шаг 1 — Контакты заказчика (Имя, Телефон, Email)
     Шаг 2 — Получатель («Заберу сам» / «Доставка получателю в подарок»,
             телефон получателя, «Узнать адрес у получателя»)
     Шаг 3 — Доставка по СПб (район с ценой, адрес, дата, 2-часовой интервал)
     Шаг 4 — Подарки (открытка с текстом 0 ₽, средство Кризал 0 ₽)
     Шаг 5 — Способ оплаты (СБП / карта / при получении)
   ЖЕЛЕЗНЫЙ КОНТРАКТ js/order-form.js СОХРАНЁН БЕЗ ИЗМЕНЕНИЙ — все id полей
   прежние: orderForm, orderName/Phone/Email/Comment(+Error), orderDeliveryZone/
   Address/AddressField/AddressError/Date/Slot/PickupAddr/DeliveryHint, orderPdConsent
   (+Error), orderRecipientName/Phone(+Error), orderCardText, orderStatus,
   orderTotal, orderCompanyWebsite (honeypot), orderPromoInput/Apply/Msg,
   orderGiftToggle/orderRecipientFields, orderSelected/orderEmptyState.
   Форма живёт ТОЛЬКО на /checkout.php (с главной убрана в S10).

   Переменные окружения (считает checkout.php): $zones, $featDeliverySlots,
   $featGiftFields, $orderHideTitle, $orderBare (true — без обёртки
   fc-section/wrap: сеткой управляет .checkout-layout чекаута). */
declare(strict_types=1);

if (!isset($zones)) {
    try {
        $zones = db()->query('SELECT id, name, price, time FROM delivery_zones ORDER BY sort, id')->fetchAll();
    } catch (Throwable $e) {
        $zones = [];
    }
}
$featDeliverySlots = $featDeliverySlots ?? (setting('feature_delivery_slots', '0') === '1');
$featGiftFields = $featGiftFields ?? (setting('feature_gift_fields', '1') === '1');
/* S11: Способы оплаты — все три исполняются при получении (онлайн-касса не
   подключена), выбор уходит в комментарий заказа через js/order-form.js
   (payment_method для API остаётся "cash" — серверный белый список). */
$ykLive = setting('yk_enabled', '0') === '1'
    && trim(setting('yk_shop_id', '')) !== ''
    && trim(setting('yk_secret_key', '')) !== '';
?>
<?php if (empty($orderBare)): ?>
  <section class="fc-section fc-section--subtle" id="order">
    <div class="wrap">
<?php endif; ?>
      <?php if (empty($orderHideTitle)): ?>
      <h2 class="section-title"><?= e(setting('order_title', 'Оформление заказа')) ?></h2>
      <?php endif; ?>
      <p class="order__selected" id="orderSelected"></p>
      <?php /* Пустая корзина — заглушка (показывает js/cart-ui.js
             syncOrderEmptyState: #orderEmptyState + скрытие #orderForm). */ ?>
      <?php
      $__orderPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
      $__orderCatalogHref = ($__orderPath === '/' || $__orderPath === '/index.php') ? '#catalog' : '/#catalog';
      ?>
      <div id="orderEmptyState" style="display:none;text-align:center;padding:44px 20px;border:1px dashed var(--line);border-radius:16px;margin-bottom:18px">
        <p style="font-size:1.05rem;font-weight:600;margin-bottom:6px">Сначала выберите букет — загляните в каталог</p>
        <p style="color:var(--ink-soft);font-size:.9rem;margin-bottom:16px">В каталоге — свежие букеты на любой бюджет</p>
        <a class="btn btn--outline" href="<?= e($__orderCatalogHref) ?>">Перейти в каталог</a>
      </div>
      <form class="order-form" id="orderForm" novalidate>

        <!-- ======== ШАГ 1. КОНТАКТЫ ЗАКАЗЧИКА ======== -->
        <fieldset class="ostep" id="stepContacts">
          <legend class="ostep__legend"><span class="ostep__num" aria-hidden="true">1</span><?= e(setting('fieldset_contacts_legend', 'Контакты заказчика')) ?></legend>
          <div class="ostep__body">
            <div class="order-form__grid2">
              <div class="order-form__field">
                <label for="orderName"><?= e(setting('form_name_label', 'Ваше имя *')) ?></label>
                <input type="text" id="orderName" name="name" autocomplete="name" required minlength="2" placeholder="Например: Евгений">
                <span class="order-form__error" id="orderNameError"></span>
              </div>
              <div class="order-form__field">
                <label for="orderPhone"><?= e(rtrim(setting('form_phone_label', 'Телефон'))) ?>&nbsp;<span aria-hidden="true">*</span></label>
                <input type="tel" id="orderPhone" name="phone" autocomplete="tel" inputmode="tel" required placeholder="+7 (___) ___-__-__">
                <span class="order-form__error" id="orderPhoneError"></span>
              </div>
            </div>
            <div class="order-form__field">
              <label for="orderEmail">Email</label>
              <input type="email" id="orderEmail" name="email" autocomplete="email" placeholder="example@mail.ru">
              <span class="order-form__hint" id="orderEmailHint">Необязательно — пришлём фото букета и статус доставки</span>
              <span class="order-form__error" id="orderEmailError"></span>
            </div>
            <p class="order-form__hint order-form__group-hint" id="contactHint">Телефон обязателен — подтвердим заказ звонком; email — по желанию</p>
          </div>
        </fieldset>

        <?php if ($featGiftFields): ?>
        <!-- ======== ШАГ 2. ПОЛУЧАТЕЛЬ ======== -->
        <fieldset class="ostep" id="stepRecipient">
          <legend class="ostep__legend"><span class="ostep__num" aria-hidden="true">2</span>Получатель</legend>
          <div class="ostep__body">
            <div class="order-form__gift-toggle" role="radiogroup" aria-label="Кому доставить букет" id="orderGiftToggle">
              <label class="order-form__gift-pill"><input type="radio" name="gift_recipient" value="other" checked><span><?= e(setting('gift_other_label', 'Доставка получателю в подарок')) ?></span></label>
              <label class="order-form__gift-pill"><input type="radio" name="gift_recipient" value="self"><span><?= e(setting('gift_self_label', 'Заберу сам')) ?></span></label>
            </div>
            <div id="orderRecipientFields">
              <div class="order-form__grid2">
                <div class="order-form__field">
                  <label for="orderRecipientName">Имя получателя</label>
                  <input type="text" id="orderRecipientName" name="recipient_name" maxlength="120" autocomplete="off" placeholder="Например: Анна">
                </div>
                <div class="order-form__field">
                  <label for="orderRecipientPhone">Телефон получателя</label>
                  <input type="tel" id="orderRecipientPhone" name="recipient_phone" autocomplete="off" inputmode="tel" placeholder="+7 (___) ___-__-__">
                  <span class="order-form__error" id="orderRecipientPhoneError"></span>
                </div>
              </div>
              <p class="order-form__hint">Курьер позвонит получателю, а не вам</p>
              <label class="order-form__check">
                <input type="checkbox" id="orderAddressByRecipient">
                <span>Узнать адрес у получателя — курьер согласует адрес при звонке</span>
              </label>
            </div>
          </div>
        </fieldset>
        <?php endif; ?>

        <!-- ======== ШАГ 3. ДОСТАВКА ПО СПБ ======== -->
        <fieldset class="ostep" id="delivery">
          <legend class="ostep__legend"><span class="ostep__num" aria-hidden="true">3</span>Доставка по Санкт-Петербургу</legend>
          <div class="ostep__body">
            <div class="order-form__grid2">
              <div class="order-form__field">
                <label for="orderDeliveryZone"><?= e(setting('form_delivery_zone_label', 'Район доставки')) ?></label>
                <?php
                /* S11: дефолт — первый платный район (основной сценарий
                   «Доставка получателю в подарок»); «Заберу сам» в шаге 2
                   переключает селект на value=0 (инлайн-скрипт ниже). */
                $firstZoneId = null;
                foreach ($zones as $z) { $firstZoneId = (string)$z['id']; break; }
                ?>
                <select id="orderDeliveryZone" name="delivery_zone">
                  <option value="0" data-price="0"<?= $firstZoneId === null ? ' selected' : '' ?>><?= e(setting('pickup_option_text', 'Самовывоз · 0' . "\u{00A0}" . '₽')) ?></option>
                  <?php foreach ($zones as $z):
                    $zFull = (string)$z['name'];
                    $zShort = trim(str_replace([' район ', ' район'], [' р-н ', ' р-н'], $zFull));
                    $zShort = trim((string)preg_replace('/\s*\([^)]*\)/u', '', $zShort));
                  ?>
                  <option value="<?= (int)$z['id'] ?>" data-price="<?= (int)$z['price'] ?>"<?= (string)$z['id'] === $firstZoneId ? ' selected' : '' ?><?= trim((string)($z['time'] ?? '')) !== '' ? ' data-time="' . e(trim((string)$z['time'])) . '"' : '' ?> title="<?= e($zFull) ?> · <?= (int)$z['price'] ?> ₽"><?= e($zShort . ' · ' . formatPrice((int)$z['price'])) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php
                $pickupAddr = trim(setting('pickup_address', '')) ?: trim(setting('shop_address', ''));
                ?>
                <?php if ($pickupAddr !== ''): ?>
                <p class="order-form__hint" id="orderPickupAddr" style="margin-top:6px" hidden>Адрес самовывоза: <?= e($pickupAddr) ?> · соберём за 1–2 часа, предупредим по телефону</p>
                <?php endif; ?>
              </div>
              <div class="order-form__field order-form__field--muted" id="orderDeliveryAddressField">
                <label for="orderDeliveryAddress">Адрес доставки</label>
                <input type="text" id="orderDeliveryAddress" name="delivery_address" placeholder="Улица, дом, квартира" autocomplete="street-address">
                <span class="order-form__error" id="orderDeliveryAddressError"></span>
              </div>
            </div>
            <?php if ($featDeliverySlots): ?>
            <div class="order-form__grid2">
              <div class="order-form__field">
                <label for="orderDeliveryDate">Дата доставки</label>
                <input type="date" id="orderDeliveryDate" name="delivery_date" aria-label="Дата доставки" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>">
                <span class="order-form__error" id="orderDeliveryDateError"></span>
              </div>
              <div class="order-form__field">
                <label for="orderDeliverySlot">Интервал</label>
                <select id="orderDeliverySlot" name="delivery_slot">
                  <option value="">Любое время дня</option>
                  <?php foreach (array_filter(array_map('trim', explode("\n", setting('delivery_slots', "09:00–11:00\n11:00–13:00\n13:00–15:00\n15:00–17:00\n17:00–19:00\n19:00–21:00")))) as $slotOption): ?>
                  <option value="<?= e($slotOption) ?>"><?= e($slotOption) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <?php endif; ?>
            <p class="order-form__hint order-form__group-hint" id="orderDeliveryHint"><?= e(setting('delivery_hint_text', 'Доставим сегодня в выбранный интервал — точное время согласует курьер')) ?></p>
          </div>
        </fieldset>

        <?php if ($featGiftFields): ?>
        <!-- ======== ШАГ 4. ПОДАРКИ ======== -->
        <fieldset class="ostep" id="stepGifts">
          <legend class="ostep__legend"><span class="ostep__num" aria-hidden="true">4</span>Подарки к букету</legend>
          <div class="ostep__body">
            <div class="ogift" id="orderPostcardBlock">
              <label class="order-form__check ogift__check">
                <input type="checkbox" id="orderPostcardOn">
                <span><strong>Открытка с вашим текстом</strong> · <em class="ogift__price">0&nbsp;₽</em><br><small>Напишем от руки и вложим в букет</small></span>
              </label>
              <div class="ogift__textarea" id="orderPostcardWrap" hidden>
                <textarea id="orderCardText" name="card_text" rows="3" maxlength="500" placeholder="С днём рождения! — от Евгения" aria-label="Текст открытки"></textarea>
              </div>
            </div>
            <div class="ogift ogift--last">
              <label class="order-form__check ogift__check">
                <input type="checkbox" id="orderChrysalOn">
                <span><strong>Средство Кризал</strong> · <em class="ogift__price">0&nbsp;₽</em><br><small>Питательный гель — букет простоит дольше</small></span>
              </label>
            </div>
          </div>
        </fieldset>
        <?php else: ?>
        <?php /* тумблер gift-полей выключен — открытка всё равно доступна (контракт #orderCardText) */ ?>
        <input type="hidden" id="orderCardTextHolder" value="">
        <?php endif; ?>

        <!-- ======== ШАГ 5. СПОСОБ ОПЛАТЫ ======== -->
        <fieldset class="ostep ostep--pay" id="stepPayment">
          <legend class="ostep__legend"><span class="ostep__num" aria-hidden="true">5</span><?= e(setting('fieldset_payment_legend', 'Способ оплаты')) ?></legend>
          <div class="ostep__body">
            <?php if ($ykLive): ?>
            <label class="order-form__radio"><input type="radio" name="payment_method" value="online" checked><span><?= e(setting('pay_online_label', 'Картой или через СБП — сразу онлайн')) ?></span></label>
            <label class="order-form__radio"><input type="radio" name="payment_method" value="cash"><span><?= e(setting('pay_cash_label', 'Наличными или картой при получении')) ?></span></label>
            <p class="order-form__hint">Оплата проходит на защищённой странице ЮKassa. Данные карты магазину не передаются.</p>
            <?php else: ?>
            <?php /* S11: три способа — все при получении (онлайн-касса не подключена).
                   payment_method для API = "cash" (серверный белый список online|cash);
                   выбранная опция уходит в комментарий через js/order-form.js
                   (data-pay-label), флорист и курьер видят предпочтение покупателя. */ ?>
            <input type="hidden" name="payment_method" value="cash" id="paymentMethodHidden">
            <?php /* S16: карточки способов оплаты — заголовок и подпись внутри
                   ЕДИНОГО обёрточного span (раньше title/sub были плоскими
                   соседями: каждый рисовал свою серую рамку, а :checked
                   красил только первый — «СБП» становилась чёрной на чёрном).
                   input:checked + span цепляется за обёртку; data-pay-label
                   и value — контракт js/order-form.js, не трогаем. */ ?>
            <div class="opay" role="radiogroup" aria-label="Способ оплаты">
              <label class="opay__opt"><input type="radio" name="payment_pref" value="СБП — по QR при получении" data-pay-label="СБП (QR)" checked><span><span class="opay__title">СБП</span><span class="opay__sub">По QR-коду при получении</span></span></label>
              <label class="opay__opt"><input type="radio" name="payment_pref" value="Банковская карта — курьеру при получении" data-pay-label="Карта курьеру"><span><span class="opay__title">Банковская карта</span><span class="opay__sub">Курьеру при получении</span></span></label>
              <label class="opay__opt"><input type="radio" name="payment_pref" value="Наличные — курьеру при получении" data-pay-label="Наличные курьеру"><span><span class="opay__title">При получении</span><span class="opay__sub">Наличными курьеру</span></span></label>
            </div>
            <p class="order-form__hint order-form__pay-note">Оплата — при получении: наличными, картой или по СБП. Ничего не платите заранее.</p>
            <?php endif; ?>
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
            <div class="order-form__field order-form__field--full">
              <label for="orderComment">Комментарий</label>
              <textarea id="orderComment" name="comment" rows="3" placeholder="Цветовые пожелания, подъезд, домофон"></textarea>
            </div>
          </div>
        </fieldset>

        <div class="hp-field" aria-hidden="true" inert><label for="orderCompanyWebsite">Сайт компании</label><input type="text" id="orderCompanyWebsite" name="company_website" tabindex="-1" autocomplete="off"></div>
        <label class="order-form__checkbox">
          <input type="checkbox" id="orderPdConsent" name="pd_consent" required>
          <span>Согласен(а) с <a href="/policy" target="_blank" rel="noopener">Политикой обработки персональных данных</a> и <a href="/offer" target="_blank" rel="noopener">Офертой</a> <span aria-hidden="true" style="color:var(--rose-cta,#AE4A71);font-weight:600">*</span></span>
        </label>
        <span class="order-form__error" id="orderPdConsentError"></span>
        <p class="order-form__total" id="orderTotal"></p>
        <button type="submit" class="btn btn--accent order-form__submit" id="orderSubmit"
                data-pay-label="<?= e(setting('submit_button_text', 'Оплатить заказ')) ?>"
                data-nopay-label="<?= e(setting('submit_nopay_text', 'Подтвердить заказ')) ?>"><?= $ykLive ? e(setting('submit_button_text', 'Оплатить заказ')) : e(setting('submit_nopay_text', 'Подтвердить заказ')) ?></button>
        <p class="order-form__hint"><?= e(sprintf('Заказы до %s — доставим сегодня; после %s — привезём завтра с утра.', setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT), setting('order_deadline_hour', '20') . ':' . str_pad(setting('order_deadline_minute', '0'), 2, '0', STR_PAD_LEFT))) ?></p>
        <p class="order-form__status" id="orderStatus" role="status" hidden></p>
      </form>
<?php if (empty($orderBare)): ?>
    </div>
  </section>
<?php endif; ?>

<?php /* S11 ЗОНА 2: сценарная логика чекаута — пилюли «Заберу сам» / «Доставка
       получателю в подарок» + «Узнать адрес у получателя» + открытка/Кризал
       шага 4 (синхронизация с localStorage nf_cart_extras — тот же контракт,
       что у корзины-drawer: window.NF_CART_EXTRAS читает order-form.js при
       отправке). Инлайн: состояние не переживает страницу, дефолт — «в подарок». */ ?>
<script>
(function () {
  var box = document.getElementById('orderGiftToggle');
  var fields = document.getElementById('orderRecipientFields');
  var zoneSel = document.getElementById('orderDeliveryZone');
  var addrField = document.getElementById('orderDeliveryAddressField');
  var addrInput = document.getElementById('orderDeliveryAddress');
  var addrByRec = document.getElementById('orderAddressByRecipient');

  function firstPaidZone() {
    if (!zoneSel) return null;
    for (var i = 0; i < zoneSel.options.length; i++) {
      var o = zoneSel.options[i];
      if (o.value !== '0' && o.value !== '') return o.value;
    }
    return null;
  }
  function setZone(val) {
    if (!zoneSel || zoneSel.value === val) return;
    zoneSel.value = val;
    zoneSel.dispatchEvent(new Event('change', { bubbles: true }));
  }
  function syncGift() {
    if (!box) return;
    var self = box.querySelector('input[value="self"]') && box.querySelector('input[value="self"]').checked;
    if (fields) fields.style.display = self ? 'none' : '';
    if (self) {
      var n = document.getElementById('orderRecipientName');
      var p = document.getElementById('orderRecipientPhone');
      if (n) n.value = '';
      if (p) p.value = '';
      /* «Заберу сам» = самовывоз: район → 0, адрес приглушается
         syncDeliveryAddressRequirement (order-form.js слушает change) */
      setZone('0');
    } else {
      /* «Доставка получателю в подарок» = платная зона (если стоит самовывоз
         и пользователь его руками не выбирал в этом сеансе) */
      if (zoneSel && zoneSel.value === '0' && !zoneSel.dataset.userTouched) {
        var fz = firstPaidZone();
        if (fz !== null) setZone(fz);
      }
    }
  }
  if (box) {
    box.addEventListener('change', syncGift);
    syncGift();
  }
  /* ручной выбор района пользователем фиксирует селект (авто-подмена не сработает);
     программные change (isTrusted=false) от setZone() — НЕ фиксируют */
  if (zoneSel) {
    zoneSel.addEventListener('change', function (e) {
      if (e.isTrusted) zoneSel.dataset.userTouched = '1';
    });
  }
  /* «Узнать адрес у получателя»: адрес подменяется служебной строкой —
     валидация order-form.js проходит (value непустое), флорист в админке
     видит пометку; сняли галочку — поле очищается обратно */
  function syncAddrByRec() {
    if (!addrByRec || !addrInput) return;
    if (addrByRec.checked) {
      addrInput.dataset.prevAddr = addrInput.value;
      addrInput.value = 'Узнаем адрес у получателя при звонке';
      addrInput.disabled = true;
      addrInput.setAttribute('aria-disabled', 'true');
      if (addrField) addrField.classList.add('order-form__field--muted');
    } else {
      addrInput.disabled = false;
      addrInput.setAttribute('aria-disabled', 'false');
      if (addrInput.dataset.prevAddr !== undefined) addrInput.value = addrInput.dataset.prevAddr;
      if (addrField) addrField.classList.remove('order-form__field--muted');
    }
  }
  if (addrByRec) {
    addrByRec.addEventListener('change', syncAddrByRec);
    syncAddrByRec();
    /* смена района перезапускает syncDeliveryAddressRequirement (order-form.js),
       который включает адрес обратно — через тик перевключаем служебную блокировку */
    if (zoneSel) {
      zoneSel.addEventListener('change', function () { setTimeout(syncAddrByRec, 0); });
    }
    /* form.reset() (после успешного заказа) чистит value — восстанавливаем
       служебную строку, если галочка всё ещё стоит */
    var nfForm = document.getElementById('orderForm');
    if (nfForm) {
      nfForm.addEventListener('reset', function () { setTimeout(syncAddrByRec, 0); });
    }
  }

  /* ---------- Шаг 4: открытка + Кризал (контракт nf_cart_extras) ---------- */
  var postcardOn = document.getElementById('orderPostcardOn');
  var postcardWrap = document.getElementById('orderPostcardWrap');
  var cardText = document.getElementById('orderCardText');
  var chrysalOn = document.getElementById('orderChrysalOn');

  function readExtras() {
    try { return JSON.parse(localStorage.getItem('nf_cart_extras') || '{}') || {}; }
    catch (e) { return {}; }
  }
  function writeExtras(patch) {
    var ex = readExtras();
    Object.keys(patch).forEach(function (k) { ex[k] = patch[k]; });
    try { localStorage.setItem('nf_cart_extras', JSON.stringify(ex)); } catch (e) {}
    window.NF_CART_EXTRAS = {
      postcard: !!ex.postcard,
      postcardText: typeof ex.postcardText === 'string' ? ex.postcardText.slice(0, 500) : '',
      chrysal: !!ex.chrysal
    };
    /* корзина-drawer и сводка чекаута пересчитаются по событию */
    document.dispatchEvent(new CustomEvent('cart:change'));
  }
  function syncGiftsUi() {
    var ex = readExtras();
    if (postcardOn) {
      postcardOn.checked = !!ex.postcard;
      if (postcardWrap) postcardWrap.hidden = !ex.postcard;
      if (cardText && ex.postcardText && cardText.value !== ex.postcardText) cardText.value = ex.postcardText;
    }
    if (chrysalOn) chrysalOn.checked = !!ex.chrysal;
  }
  if (postcardOn) {
    postcardOn.addEventListener('change', function () {
      writeExtras({ postcard: postcardOn.checked });
      syncGiftsUi();
    });
  }
  if (chrysalOn) {
    chrysalOn.addEventListener('change', function () {
      writeExtras({ chrysal: chrysalOn.checked });
      syncGiftsUi();
    });
  }
  if (cardText) {
    cardText.addEventListener('input', function () {
      var ex = readExtras();
      if (!ex.postcard) writeExtras({ postcard: true, postcardText: cardText.value });
      else writeExtras({ postcardText: cardText.value });
    });
  }
  syncGiftsUi();
  /* корзина-drawer могла поменять допы (свои чекбоксы) — догоняем состояние
     формы по событию корзины (read-only — цикла нет) */
  document.addEventListener('cart:change', syncGiftsUi);
})();
</script>
