<?php
/* Инструкция для владельца магазина: как управлять сайтом через админку.
   Статичный справочник — обновляется вместе с панелью. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
?><!DOCTYPE html>
<html lang="ru">
<head>
<title>Инструкция — Админ-панель</title>
<meta name="robots" content="noindex">
<style>
:root{--rose:#F4A9BE;--rose-deep:#E2799C;--bg:#F6F1E6;--bg-alt:#EFE7D8;--ink:#2B2D2F;--ink-soft:#6E6A61;--line:rgba(43,45,47,.12);
--font-display:'Playfair Display',Georgia,serif;--font-ui:'Golos Text',system-ui,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-ui);color:var(--ink);background:var(--bg);line-height:1.6;padding:24px 16px 60px}
.wrap{max-width:820px;margin:0 auto}
h1{font-family:var(--font-display);font-weight:600;font-size:1.7rem;margin-bottom:6px}
p.sub{color:var(--ink-soft);font-size:.9rem;margin-bottom:22px}
.card{background:#fff;border-radius:18px;padding:20px 22px;box-shadow:0 14px 40px -28px rgba(43,45,47,.35);margin-bottom:14px}
.card h2{font-size:1.02rem;font-weight:700;margin-bottom:6px}
.card p,.card li{font-size:.9rem}
.card ul{padding-left:18px;margin-top:4px}
.card ol{padding-left:18px;margin-top:4px}
kbd{background:var(--bg-alt);border:1px solid var(--line);border-radius:6px;padding:1px 7px;font-size:.82rem;font-family:var(--font-ui)}
a.top{display:inline-block;color:var(--ink-soft);font-size:.85rem;margin-bottom:14px;text-decoration:underline}
table{width:100%;border-collapse:collapse;font-size:.88rem;margin-top:6px}
td,th{padding:7px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft)}
.tag{display:inline-block;background:var(--rose);border-radius:999px;padding:1px 10px;font-size:.72rem;font-weight:700;margin-left:8px;vertical-align:middle}
</style>
</head>
<body>
<div class="wrap">
  <h1>Как пользоваться сайтом</h1>
  <p class="sub">Короткая инструкция для владельца магазина. Всё меняется через админ-панель, программист не нужен.</p>

  <div class="card">
    <h2>Заказы каждый день</h2>
    <ul>
      <li>Новый заказ появляется в разделе <a href="/admin/index.php"><kbd>Заказы</kbd></a>. Статус меняется кнопками прямо в строке заказа: <b>Новый → Подтверждён → Выполнен</b> (или <b>Отменён</b>).</li>
      <li>В строке заказа видно имя, телефон, состав, сумму, способ оплаты и комментарий (например, текст открытки).</li>
    </ul>
  </div>

  <div class="card">
    <h2>Букеты и цены — раздел «Товары» <span class="tag">ежедневно</span></h2>
    <ul>
      <li><b>Новый букет:</b> Товары → «Новый товар» → название, цена, категория, описание, фото → «Добавить товар». Он сразу появится в каталоге.</li>
      <li><b>Смена цены:</b> Товары → «Изменить» → новое значение цены → «Сохранить».</li>
      <li><b>Скидка:</b> в той же форме заполните «Цена по акции» — на витрине появится зачёркнутая старая цена и бейдж «Скидка». Уберите значение — скидка исчезнет.</li>
      <li><b>Закончился:</b> кнопка «Скрыть» в строке товара — букет мгновенно пропадает с сайта. «Показать» возвращает.</li>
    </ul>
  </div>

  <div class="card">
    <h2>Фото товара с телефона</h2>
    <p>В форме товара нажмите «Фото» и выберите снимок из галереи телефона — загружать по ссылке не обязательно. Снимайте вертикально, как портретное фото — тогда букет красиво поместится в карточку.</p>
  </div>

  <div class="card">
    <h2>Категории и вкладки каталога</h2>
    <p>Раздел <a href="/admin/categories.php"><kbd>Категории</kbd></a> — это вкладки над каталогом на сайте («Розы», «Сборные букеты»…). Добавляйте, переименовывайте, меняйте порядок полем «Сортировка». Категорию с товарами удалить нельзя — сначала перенесите товары.</p>
  </div>

  <div class="card">
    <h2>Зоны и цены доставки</h2>
    <p>Раздел <a href="/admin/zones.php"><kbd>Зоны доставки</kbd></a>: район + стоимость. Покупатель видит их списком в форме заказа, стоимость зоны автоматически прибавляется к сумме.</p>
  </div>

  <div class="card">
    <h2>Главная страница</h2>
    <p>Раздел <a href="/admin/settings.php"><kbd>Настройки</kbd></a>:</p>
    <table>
      <tr><th>Что меняете</th><th>Где на сайте</th></tr>
      <tr><td>Фото для главной</td><td>Большое фото справа в hero-блоке</td></tr>
      <tr><td>Заголовок / подзаголовок / кнопка</td><td>Текст поверх hero-блока</td></tr>
      <tr><td>Логотип</td><td>Слева в шапке</td></tr>
      <tr><td>Название, телефон, адрес</td><td>Шапка, подвал, футер</td></tr>
      <tr><td>Шаги 1–3</td><td>Блок «Как это работает»</td></tr>
      <tr><td>Гарантии 1–3</td><td>Строка преимуществ и страница товара</td></tr>
    </table>
  </div>

  <div class="card">
    <h2>Документы и реквизиты</h2>
    <p>В «Настройках» внизу заполните реквизиты (ИП/ООО, ОГРН, адрес) — они автоматически подставятся на страницы <a href="/policy" target="_blank">«Политика ПД»</a> и <a href="/offer" target="_blank">«Публичная оферта»</a>. Пока не заполнены, страницы честно пишут, что реквизиты не внесены.</p>
  </div>

  <div class="card">
    <h2>Безопасность — сделать при первом входе</h2>
    <ol>
      <li>Смените пароль администратора (по запросу — добавим страницу смены пароля или сообщите новый пароль тому, кто настраивал сайт).</li>
      <li>Не передавайте ссылку на админку третьим лицам.</li>
    </ol>
  </div>

  <div class="card">
    <h2>Оплата</h2>
    <p>Сейчас заказы принимаются в полном объёме, онлайн-оплата работает в тестовом режиме (без списания денег). Для боевой оплаты подключается ЮKassa — это делается один раз специалистом: понадобится shopId и секретный ключ из личного кабинета.</p>
  </div>

  <p style="text-align:center;margin-top:24px"><a class="btn-top" href="/admin/" style="color:#E2799C;font-weight:600">Открыть админ-панель →</a></p>
</div>
</body>
</html>
