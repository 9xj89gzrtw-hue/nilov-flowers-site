<?php
declare(strict_types=1);
define('BASE_PATH', __DIR__ . '/..');
define('DB_PATH', BASE_PATH . '/db/flowers.db');
define('IMG_PRODUCTS_DIR', BASE_PATH . '/img/products');
define('IMG_UPLOADS_DIR', BASE_PATH . '/img/uploads');
/* Security-критик W40 HIGH: .eml/логи фолбэка почты содержали ФИО/телефон/IP покупателей
   и лежали ВНУТРИ docroot (отдавались статикой). Новый дом — на уровень выше web-root;
   если недоступен (локалка), fallback на старый путь, но .htaccess закроет deny. */
$__stateOutside = dirname(BASE_PATH) . '/flowers.state';
if (!is_dir($__stateOutside)) { @mkdir($__stateOutside, 0755, true); }
define('STATE_OUT_DIR', is_writable(dirname(BASE_PATH)) && (is_dir($__stateOutside) || is_writable($__stateOutside)) ? $__stateOutside : BASE_PATH . '/state');
date_default_timezone_set('Europe/Moscow');
