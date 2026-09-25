<?php
declare(strict_types=1);
/* W101 (security-критик): realpath обязателен — иначе dirname('/site/includes/..')
   = '/site/includes', и «вне web-root» state-каталог ложился ВНУТРЬ docroot
   (includes/flowers.state/mail-out/*.eml с ФИО/телефонами отдавался статикой). */
define('BASE_PATH', (string)realpath(__DIR__ . '/..'));
define('DB_PATH', BASE_PATH . '/db/flowers.db');
define('IMG_PRODUCTS_DIR', BASE_PATH . '/img/products');
define('IMG_UPLOADS_DIR', BASE_PATH . '/img/uploads');
/* Security-критик W40 HIGH: .eml/логи фолбэка почты содержали ФИО/телефон/IP покупателей
   и лежали ВНУТРИ docroot (отдавались статикой). Новый дом — на уровень выше web-root;
   если недоступен (локалка), fallback на старый путь, но .htaccess закроет deny. */
$__stateOutside = dirname(BASE_PATH) . '/flowers.state';
if (!is_dir($__stateOutside)) { @mkdir($__stateOutside, 0755, true); }
/* W101: переносим старый state из includes/flowers.state (баг dirname) наружу */
$__stateLegacy = BASE_PATH . '/includes/flowers.state';
if (is_dir($__stateLegacy) && is_writable(dirname($__stateLegacy)) && is_dir($__stateOutside)) {
    foreach (glob($__stateLegacy . '/*') ?: [] as $__f) { @rename($__f, $__stateOutside . '/' . basename($__f)); }
    @rmdir($__stateLegacy);
}
define('STATE_OUT_DIR', is_writable(dirname(BASE_PATH)) && (is_dir($__stateOutside) || is_writable($__stateOutside)) ? $__stateOutside : BASE_PATH . '/state');
/* W85 (стресс-критик): стектрейсы PDO не должны утекать в HTTP-тело (200 с <b>Fatal error</b>).
   error_log/php_error_log продолжают писаться — отладка не теряется. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Europe/Moscow');
