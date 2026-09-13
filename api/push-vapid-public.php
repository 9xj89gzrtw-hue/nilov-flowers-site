<?php
/* Публичная VAPID-подпись для браузерной подписки PushManager. GET → {publicKey}. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/vapid.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (setting('feature_webpush', '0') !== '1') {
    echo json_encode(['publicKey' => null, 'error' => 'disabled']);
    exit;
}
echo json_encode(['publicKey' => trim(setting('vapid_public', '')) ?: null]);
