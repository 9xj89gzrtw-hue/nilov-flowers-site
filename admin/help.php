<?php
/* Инструкция для владельца магазина: как управлять сайтом через админку.
   Статичный справочник — обновляется вместе с панелью.
   Редизайн 5cv (W96/T2-d): каркас сайта (partials) + page-hero + карточки .fc-store.
   W97-fixB3a (B3a-2): страница перенесена из корня (/help) в /admin/help.php —
   инструкция владельца не должна жить на витрине; кука админ-сессии имеет
   path=/admin, поэтому вне /admin страницу и не было открыть под логином
   (хвост B1). Старый /help — 301-редирект (router.php + .htaccess). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/auth.php';

/* W97-fixB1 (B1-3): /help — ВНУТРЕННЯЯ инструкция владельца (разделы про админ-панель,
   ссылки на /admin) — не публичный контент. Доступ только администратору, иначе
   редирект на /admin/login.php (паттерн admin/settings.php: ensureAdminUser + requireAdmin).
   Публичных ссылок на /help на витрине нет (rg '/help' — только роутер и .htaccess). */
ensureAdminUser();
requireAdmin();

$pageTitle = 'Инструкция — Админ-панель';
?><!DOCTYPE html>
<html lang="ru">
<head>
<title>Инструкция — Админ-панель</title>
<meta name="robots" content="noindex">
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
/* Локальные стили справки: kbd/таблица/тег — в five.css аналогов нет, красим токенами 5cv */
.help-text p{margin:6px 0}
.help-text ul,.help-text ol{padding-left:20px;margin:6px 0}
.help-text li{margin:4px 0}
.help-text table{width:100%;border-collapse:collapse;font-size:.88rem;margin-top:6px}
.help-text td,.help-text th{padding:7px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
.help-text th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-muted)}
kbd{background:var(--surface-warm);border:1px solid var(--line);border-radius:6px;padding:1px 7px;font-size:.82rem;font-family:var(--font-ui)}
.help-tag{display:inline-block;background:var(--amber);color:var(--ink);border-radius:999px;padding:2px 10px;font-size:.72rem;font-weight:700;margin-left:8px;vertical-align:middle}
</style>
</head>
<body>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main" tabindex="-1">
  <section class="fc-section">
    <div class="wrap" style="max-width:820px">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> / <span aria-current="page">Инструкция</span>
      </nav>
      <div class="page-hero">
        <h1 class="page-hero__title">Как пользоваться сайтом</h1>
        <p class="section-sub">Короткая инструкция для владельца магазина. Всё меняется через админ-панель, программист не нужен.</p>
      </div>

      <div class="fc-stores" style="grid-template-columns:1fr;gap:14px;margin-top:24px">
        <div class="fc-store">
          <h2 class="fc-store__title">Заказы каждый день</h2>
          <div class="fc-store__text help-text">
            <ul>
              <li>Новый заказ появляется в разделе <a href="/admin/index.php"><kbd>Заказы</kbd></a>. Статус меняется кнопками прямо в строке заказа: <b>Новый → Подтверждён → Выполнен</b> (или <b>Отменён</b>).</li>
              <li>В строке заказа видно имя, телефон, состав, сумму, способ оплаты и комментарий (например, текст открытки).</li>
            </ul>
          </div>
        </div>

        <div class="fc-store">
          <h2 class="fc-store__title">Букеты и цены — раздел «Товары»<span class="help-tag">ежедневно</span></h2>
          <div class="fc-store__text help-text">
            <ul>
              <li><b>Новый букет:</b> Товары → «Новый товар» → название, цена, категория, описание, фото → «Добавить товар». Он сразу появится в каталоге.</li>
              <li><b>Смена цены:</b> Товары → «Изменить» → новое значение цены → «Сохранить».</li>
              <li><b>Скидка:</b> в той же форме заполните «Цена по акции» — на витрине появится зачёркнутая старая цена и бейдж с текстом акции (настраивается в «Настройках»). Уберите значение — скидка исчезнет.</li>
              <li><b>Закончился:</b> кнопка «Скрыть» в строке товара — букет мгновенно пропадает с сайта. «Показать» возвращает.</li>
            </ul>
          </div>
        </div>

        <div class="fc-store">
          <h2 class="fc-store__title">Фото товара с телефона</h2>
          <div class="fc-store__text help-text">
            <p>В форме товара нажмите «Фото» и выберите снимок из галереи телефона — загружать по ссылке не обязательно. Снимайте вертикально, как портретное фото — тогда букет красиво поместится в карточку.</p>
          </div>
        </div>

        <div class="fc-store">
          <h2 class="fc-store__title">Категории и вкладки каталога</h2>
          <div class="fc-store__text help-text">
            <p>Раздел <a href="/admin/categories.php"><kbd>Категории</kbd></a> — это вкладки над каталогом на сайте («Розы», «Сборные букеты»…). Добавляйте, переименовывайте, меняйте порядок полем «Сортировка». Категорию с товарами удалить нельзя — сначала перенесите товары.</p>
          </div>
        </div>

        <div class="fc-store">
          <h2 class="fc-store__title">Зоны и цены доставки</h2>
          <div class="fc-store__text help-text">
            <p>Раздел <a href="/admin/zones.php"><kbd>Зоны доставки</kbd></a>: район + стоимость. Покупатель видит их списком в форме заказа, стоимость зоны автоматически прибавляется к сумме.</p>
          </div>
        </div>

        <div class="fc-store">
          <h2 class="fc-store__title">Главная страница</h2>
          <div class="fc-store__text help-text">
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

        <div class="fc-store">
          <h2 class="fc-store__title">Документы и реквизиты</h2>
          <div class="fc-store__text help-text">
            <p>В «Настройках» внизу заполните реквизиты (ИП/ООО, ОГРН, адрес) — они автоматически подставятся на страницы <a href="/policy" target="_blank">«Политика ПД»</a> и <a href="/offer" target="_blank">«Публичная оферта»</a>. Пока не заполнены, страницы честно пишут, что реквизиты не внесены.</p>
          </div>
        </div>

        <div class="fc-store">
          <h2 class="fc-store__title">Безопасность — сделать при первом входе</h2>
          <div class="fc-store__text help-text">
            <ol>
              <li>Смените пароль администратора (по запросу — добавим страницу смены пароля или сообщите новый пароль тому, кто настраивал сайт).</li>
              <li>Не передавайте ссылку на админку третьим лицам.</li>
            </ol>
          </div>
        </div>

        <div class="fc-store">
          <h2 class="fc-store__title">Оплата</h2>
          <div class="fc-store__text help-text">
            <p>Сейчас заказы принимаются в полном объёме, онлайн-оплата работает в тестовом режиме (без списания денег). Для боевой оплаты подключается ЮKassa — это делается один раз специалистом: понадобится shopId и секретный ключ из личного кабинета.</p>
          </div>
        </div>

        <div class="fc-store">
          <h2 class="fc-store__title">Продвижение: Яндекс и Google увидят сайт<span class="help-tag">один раз, 15 минут</span></h2>
          <div class="fc-store__text help-text">
            <p style="margin-bottom:6px">Сайт уже готов к подключению: карта сайта работает по адресу <kbd>/sitemap.xml</kbd>, роботы-файл настроен. Осталось подтвердить права — это бесплатные сервисы:</p>
            <ol>
              <li><b>Яндекс.Вебмастер</b> (<a href="https://webmaster.yandex.ru" target="_blank" rel="noopener">webmaster.yandex.ru</a>) — войдите в свою Яндекc-почту → «Добавить сайт» → укажите адрес. Яндекс покажет способ подтверждения «метатег»: скопируйте длинный код и вставьте в админке: <b>Настройки → блок «Продвижение» → «Подтверждение прав для Яндекса и Google» → код Яндекс.Вебмастера</b> → Сохранить. Вернитесь в Вебмастер и нажмите «Проверить».</li>
              <li>Там же в Вебмастере: раздел «Индексирование → Карта сайта» — добавьте <kbd>https://flowers.interfood-catering.ru/sitemap.xml</kbd>. Теперь новые букеты будут попадать в поиск автоматически.</li>
              <li><b>Google Search Console</b> (<a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a>) — «Добавить ресурс» → префикс URL → адрес сайта → способ «HTML-тег»: код в то же поле админки (строка Google). Прогресс по запросам видно в разделе «Эффективность».</li>
              <li><b>Яндекс Бизнес</b> (<a href="https://business.yandex.ru" target="_blank" rel="noopener">business.yandex.ru</a>) — бесплатно: добавьте «Цветочный магазин Nilov Flowers» с адресом Полевая Сабировская 47 и телефоном, подтвердите адрес (придёт код). После подтверждения магазин с фото, отзывами и часами появится на Яндекс.Картах — по этому адресу ищут «цветы рядом».</li>
              <li><b>Метрика</b>: счётчик включается в том же блоке «Продвижение» — просто номер из личного кабинета метрики. Покупателям согласия не мешаем: статистика пишется только после «Принять» в баннере cookie. Цель по заказам (ORDER_SUBMIT) уже настроена на сайте — в отчётах Метрики будете видеть суммы.</li>
            </ol>
            <p style="margin-top:6px;font-size:.82rem;color:var(--ink-muted)">Ничего из этого не требует программиста. Порядок действий: 1 → 2 → 4 (карты важнее поиска для цветочного: по фразе «розы» поиск покажет только часть — карточки товаров и поводы точнее), 3 и 5 по желанию.</p>
          </div>
        </div>
      </div>

      <p style="text-align:center;margin-top:28px"><a class="btn btn--accent" href="/admin/">Открыть админ-панель</a></p>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
