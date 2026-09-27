<?php
/* Инструкция для владельца магазина: как управлять сайтом через админку.
   Статичный справочник — обновляется вместе с панелью.
   W97-fixB3a (B3a-2): страница перенесена из корня (/help) в /admin/help.php —
   инструкция владельца не должна жить на витрине; кука админ-сессии имеет
   path=/admin, поэтому вне /admin страницу и не было открыть под логином
   (хвост B1). Старый /help — 301-редирект (router.php + .htaccess).
   W105 (4-b): страница переведена с витринных партиалов (partials/head+header+footer
   — из-за них не было НИ админ-навигации, НИ пути назад, плюс чужой cookie-баннер)
   на админ-оболочку includes/layout.php (adminHeader/adminFooter). Контент сохранён. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

/* W97-fixB1 (B1-3): /help — ВНУТРЕННЯЯ инструкция владельца (разделы про админ-панель,
   ссылки на /admin) — не публичный контент. Доступ только администратору, иначе
   редирект на /admin/login.php (паттерн admin/settings.php: ensureAdminUser + requireAdmin).
   Публичных ссылок на /help на витрине нет (rg '/help' — только роутер и .htaccess). */
ensureAdminUser();
requireAdmin();

adminHeader('Инструкция', 'help');
?>
<h1>Как пользоваться сайтом</h1>
<p style="color:var(--ink-soft);font-size:.92rem;margin:-10px 0 18px">Короткая инструкция для владельца магазина. Всё меняется через админ-панель, программист не нужен.</p>
<style>
/* Локальные стили справки: kbd/таблица/тег — в админ-оболочке аналогов нет, красим токенами админки */
.help-text p{margin:6px 0}
.help-text ul,.help-text ol{padding-left:20px;margin:6px 0}
.help-text li{margin:4px 0}
.help-text table{width:100%;border-collapse:collapse;font-size:.88rem;margin-top:6px}
.help-text td,.help-text th{padding:7px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
.help-text th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft)}
.help-text td,.help-text li{white-space:normal}
.help-text a{display:inline-flex;align-items:center;min-height:28px;color:var(--rose-cta,#AE4A71);text-decoration:underline;text-underline-offset:2px;font-weight:600}
.help-text a:hover{color:var(--rose-cta-hover,#9E4062)}
kbd{background:var(--bg-alt,#EFE7D8);border:1px solid var(--line);border-radius:6px;padding:1px 7px;font-size:.82rem;font-family:var(--font-ui)}
.help-tag{display:inline-block;background:var(--rose,#F4A9BE);color:var(--ink);border-radius:999px;padding:2px 10px;font-size:.72rem;font-weight:700;margin-left:8px;vertical-align:middle}
@media(hover:none),(max-width:820px){.help-text a{min-height:44px}} /* W105 (4-b): тап-цели 44px, паттерн order-link */
</style>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Заказы каждый день</h2>
  <div class="help-text">
    <ul>
      <li>Новый заказ появляется в разделе <a href="/admin/index.php"><kbd>Заказы</kbd></a>. Статус меняется кнопками прямо в строке заказа: <b>Новый → Подтверждён → В работе</b> (или <b>Отменён</b>). Финальный статус <b>Выполнен</b> ставится на странице заказа — с фото вручения или причиной, почему фото нет.</li>
      <li>В строке заказа видно имя, телефон, состав, сумму, способ оплаты и комментарий (например, текст открытки). Отдельная колонка «Доставка» — желаемая дата и слот («25.09, Утро»); прочерк — «как можно скорее». Сверху есть фильтры и быстрые чипы по дате доставки: «Сегодня», «Завтра», «7 дней», «Без даты».</li>
    </ul>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Букеты и цены — раздел «Товары»<span class="help-tag">ежедневно</span></h2>
  <div class="help-text">
    <ul>
      <li><b>Новый букет:</b> Товары → «Новый товар» → название, цена, категория, описание, фото → «Добавить товар». Товар создаётся <b>скрытым</b> — он не виден в каталоге, пока вы не проверите карточку и не нажмёте «Показать» в списке (для этого нужны фото и цена).</li>
      <li><b>Смена цены:</b> Товары → «Изменить» → новое значение цены → «Сохранить».</li>
      <li><b>Скидка:</b> в той же форме заполните «Цена по акции» — на витрине появится зачёркнутая старая цена и бейдж с текстом акции (настраивается в «Настройках»). Уберите значение — скидка исчезнет.</li>
      <li><b>Закончился:</b> кнопка «Скрыть» в строке товара — букет мгновенно пропадает с сайта. «Показать» возвращает.</li>
    </ul>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Фото товара с телефона</h2>
  <div class="help-text">
    <p>В форме товара нажмите «Фото» и выберите снимок из галереи телефона — загружать по ссылке не обязательно. Снимайте вертикально, как портретное фото — тогда букет красиво поместится в карточку.</p>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Категории и вкладки каталога</h2>
  <div class="help-text">
    <p>Раздел <a href="/admin/categories.php"><kbd>Категории</kbd></a> — это вкладки над каталогом на сайте («Розы», «Сборные букеты»…). Добавляйте, переименовывайте, меняйте порядок полем «Сортировка». При удалении категории её товары не пропадают — они останутся в каталоге «без категории», пока не назначите другую.</p>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Зоны и цены доставки</h2>
  <div class="help-text">
    <p>Раздел <a href="/admin/zones.php"><kbd>Зоны доставки</kbd></a>: район + стоимость. Покупатель видит их списком в форме заказа, стоимость зоны автоматически прибавляется к сумме.</p>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Главная страница</h2>
  <div class="help-text">
    <p>Раздел <a href="/admin/settings.php"><kbd>Настройки</kbd></a>:</p>
    <table>
      <tr><th>Что меняете</th><th>Где на сайте</th></tr>
      <tr><td>Фото для главной</td><td>Большое фото в hero-блоке</td></tr>
      <tr><td>Заголовок / подзаголовок / кнопка</td><td>Текст поверх hero-блока</td></tr>
      <tr><td>Логотип</td><td>Слева в шапке</td></tr>
      <tr><td>Название, телефон, адрес</td><td>Шапка, подвал, футер</td></tr>
      <tr><td>Заголовок и подпись каталога</td><td>Секция «Каталог»</td></tr>
      <tr><td>Гарантии 1–3</td><td>Траст-ряд под hero и страница товара</td></tr>
    </table>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Документы и реквизиты</h2>
  <div class="help-text">
    <p>В «Настройках» внизу заполните реквизиты (ИП/ООО, ОГРН, адрес) — они автоматически подставятся на страницы <a href="/policy" target="_blank" rel="noopener">«Политика ПД»</a> и <a href="/offer" target="_blank" rel="noopener">«Публичная оферта»</a>. Пока не заполнены, страницы честно пишут, что реквизиты не внесены.</p>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Безопасность — сделать при первом входе</h2>
  <div class="help-text">
    <ol>
      <li>Смените пароль администратора: раздел <a href="/admin/profile.php"><kbd>Профиль</kbd></a> → «Смена пароля» (минимум 8 символов, требует текущий пароль).</li>
      <li>Не передавайте ссылку на админку третьим лицам.</li>
    </ol>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Оплата</h2>
  <div class="help-text">
    <p>По умолчанию заказы принимаются с оплатой при получении. Онлайн-оплата (карты и СБП) включается без программиста: <a href="/admin/settings.php#s-pay"><kbd>Настройки → Оплата</kbd></a> → галочка «Принимать онлайн-оплату» + shopId и секретный ключ из личного кабинета ЮKassa (Интеграция → Ключи API). Раздел виден только владельцу магазина.</p>
  </div>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Продвижение: Яндекс и Google увидят сайт<span class="help-tag">один раз, 15 минут</span></h2>
  <div class="help-text">
    <p style="margin-bottom:6px">Сайт уже готов к подключению: карта сайта работает по адресу <kbd>/sitemap.xml</kbd>, роботы-файл настроен. Осталось подтвердить права — это бесплатные сервисы:</p>
    <ol>
      <li><b>Яндекс.Вебмастер</b> (<a href="https://webmaster.yandex.ru" target="_blank" rel="noopener">webmaster.yandex.ru</a>) — войдите в свою Яндекc-почту → «Добавить сайт» → укажите адрес. Яндекс покажет способ подтверждения «метатег»: скопируйте длинный код и вставьте в админке: <b>Настройки → блок «Продвижение» → «Подтверждение прав для Яндекса и Google» → код Яндекс.Вебмастера</b> → Сохранить. Вернитесь в Вебмастер и нажмите «Проверить».</li>
      <li>Там же в Вебмастере: раздел «Индексирование → Карта сайта» — добавьте <kbd>https://flowers.interfood-catering.ru/sitemap.xml</kbd>. Теперь новые букеты будут попадать в поиск автоматически.</li>
      <li><b>Google Search Console</b> (<a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a>) — «Добавить ресурс» → префикс URL → адрес сайта → способ «HTML-тег»: код в то же поле админки (строка Google). Прогресс по запросам видно в разделе «Эффективность».</li>
      <li><b>Яндекс Бизнес</b> (<a href="https://business.yandex.ru" target="_blank" rel="noopener">business.yandex.ru</a>) — бесплатно: добавьте «Цветочный магазин Nilov Flowers» с адресом Полевая Сабировская 47 и телефоном, подтвердите адрес (придёт код). После подтверждения магазин с фото, отзывами и часами появится на Яндекс.Картах — по этому адресу ищут «цветы рядом».</li>
      <li><b>Метрика</b>: счётчик включается в том же блоке «Продвижение» — просто номер из личного кабинета метрики. Покупателям согласия не мешаем: статистика пишется только после «Принять» в баннере cookie. Цель по заказам (ORDER_SUBMIT) уже настроена на сайте — в отчётах Метрики будете видеть суммы.</li>
    </ol>
    <p style="margin-top:6px;font-size:.82rem;color:var(--ink-soft)">Ничего из этого не требует программиста. Порядок действий: 1 → 2 → 4 (карты важнее поиска для цветочного: по фразе «розы» поиск покажет только часть — карточки товаров и поводы точнее), 3 и 5 по желанию.</p>
  </div>
</div>

<p style="text-align:center;margin:4px 0 12px"><a class="btn btn--accent" href="/admin/">Открыть админ-панель</a></p>
<?php adminFooter();
