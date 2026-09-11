<?php
/* Страница «Политика обработки персональных данных».
   Реквизиты — из настроек; пока не заполнены, честно сообщаем об этом. */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$subjectType = setting('legal_subject_type', '');
$subjectName = setting('legal_name', '');
$legalNumber = setting('legal_number', '');
$legalAddress = setting('legal_address', '');
$legalContact = setting('legal_contact_email', '') !== '' ? setting('legal_contact_email') : setting('shop_phone');

$requisitesReady = $subjectType !== '' && $subjectName !== '' && $legalNumber !== '';
$subjectLabel = $subjectType === 'IP' ? 'ИП' : ($subjectType === 'OOO' ? 'ООО' : '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<title>Политика обработки персональных данных — <?= e(setting('shop_name', 'Nilov Flowers')) ?></title>
<meta name="robots" content="noindex">
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>

<main class="doc-page">
  <div class="wrap doc-page__inner">
    <h1 class="section-title">Политика обработки персональных данных</h1>

    <div class="doc-page__card">
      <h2>1. Общие положения</h2>
      <p>Настоящая политика обработки персональных данных составлена в соответствии с Федеральным законом №152-ФЗ «О персональных данных» и определяет порядок обработки персональных данных и меры по обеспечению их безопасности, предпринимаемые оператором.</p>
      <?php if ($requisitesReady): ?>
      <p>Оператор: <?= e($subjectLabel . ' ' . $subjectName) ?><?= $legalNumber !== '' ? ', ' . e(($subjectType === 'IP' ? 'ОГРНИП ' : 'ОГРН ') . $legalNumber) : '' ?><?= $legalAddress !== '' ? ', адрес: ' . e($legalAddress) : '' ?>.</p>
      <?php else: ?>
      <p class="doc-page__note">Реквизиты оператора ещё не внесены в настройках сайта.</p>
      <?php endif; ?>

      <h2>2. Какие данные мы собираем</h2>
      <ul>
        <li>имя покупателя — для обращения и подтверждения заказа;</li>
        <li>телефон — для связи по заказу;</li>
        <li>email — для чека об онлайн-оплате и связи;</li>
        <li>адрес доставки — для передачи заказа курьеру;</li>
        <li>текст комментария к заказу — по вашему желанию.</li>
      </ul>

      <h2>3. Цели обработки</h2>
      <ul>
        <li>оформление, подтверждение и доставка заказа;</li>
        <li>связь с покупателем по заказу;</li>
        <li>формирование фискального чека при онлайн-оплате.</li>
      </ul>

      <h2>4. Передача данных третьим лицам</h2>
      <p>Мы не продаём и не передаём персональные данные третьим лицам, кроме случаев, необходимых для исполнения заказа: платёжному провайдеру (при онлайн-оплате) и курьерской доставке (имя, телефон и адрес получателя).</p>

      <h2>5. Хранение и удаление</h2>
      <p>Данные заказов хранятся не дольше, чем это необходимо для целей обработки и требований бухгалтерского учёта. Вы можете запросить уточнение, блокировку или удаление своих данных, связавшись с нами: <?= $legalContact !== '' ? e($legalContact) : 'по телефону магазина' ?>.</p>

      <h2>6. Cookies</h2>
      <p>Сайт использует только служебное локальное хранилище браузера для содержимого вашей корзины и факт закрытия уведомления о cookies. Системы аналитики и рекламные трекеры на сайте не используются.</p>
    </div>

    <p class="doc-page__back"><a href="/" class="btn btn--accent">Вернуться в магазин</a></p>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
