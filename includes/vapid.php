<?php
/* VAPID-уведомления (Web Push) — без composer, stdlib PHP + curl.
   Ключи генерируются один раз (openssl P-256), хранятся в settings:
   vapid_public (base64url uncompressed point 65B), vapid_private (PEM).
   Подпись запроса: JWT ES256 (DER→raw r||s 64b по RFC7515/8292).
   Payload — plain JSON (допустимо RFC8292; Chrome/Firefox принимают). */
declare(strict_types=1);

function b64url(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }

function vapidKeysExist(): bool {
    return trim(setting('vapid_public', '')) !== '' && trim(setting('vapid_private', '')) !== '';
}

function vapidGenerate(): array {
    $pk = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if ($pk === false) { return ['error' => 'openssl ec keygen failed']; }
    openssl_pkey_export($pk, $privPem);
    $det = openssl_pkey_get_details($pk);
    $der = base64_decode(preg_replace('#-----[^-]+-----|\s#', '', (string)$det['key']));
    $pubRaw = null;
    for ($i = 0; $i + 65 <= strlen($der); $i++) {
        if ($der[$i] === "\x04") { $pubRaw = substr($der, $i, 65); }
    }
    if ($pubRaw === null || strlen($pubRaw) !== 65) { return ['error' => 'public point parse failed']; }
    saveSettings(['vapid_public' => b64url($pubRaw), 'vapid_private' => $privPem]);
    return ['public' => b64url($pubRaw)];
}

function vapidAuthHeaders(string $endpoint): array {
    $pub = trim(setting('vapid_public', ''));
    $privPem = trim(setting('vapid_private', ''));
    $subject = trim(setting('vapid_subject', '')) ?: 'mailto:owner@flowers.interfood-catering.ru';
    if ($pub === '' || $privPem === '') { return []; }
    $u = parse_url($endpoint);
    $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');
    $header64 = b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload64 = b64url(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => $subject]));
    $signing = $header64 . '.' . $payload64;
    $pkey = openssl_pkey_get_private($privPem);
    if (!$pkey) { return []; }
    openssl_sign($signing, $derSig, $pkey, OPENSSL_ALGO_SHA256);
    // DER: 30 <seqLen> 02 <rLen> <r> 02 <sLen> <s> → raw r(32)||s(32) (RFC7515, big-endian, беззнаковые)
    $raw = derSigToRaw($derSig);
    if ($raw === '') { return []; }
    // RFC 8292: auth-схема называется "vapid" (не "webpush" из draft-01; node-web-push
    // сменила в 2018 именно из-за требований FCM)
    return [
        'Authorization: vapid t=' . $signing . ',k=' . $pub,
        'TTL: 86400',
        'Content-Type: application/octet-stream',
    ];
}

/** ECDSA DER-подпись → фиксированные r(32)||s(32). Пустая строка при любом отклонении формата. */
function derSigToRaw(string $der): string {
    $hex = bin2hex($der);
    if (strlen($hex) < 10 || substr($hex, 0, 2) !== '30') { return ''; }
    $i = 4; // после 30 <seqlen> и первого 02
    if (substr($hex, 2, 2) === '81') { $i = 6; } // длинная форма seqLen
    if (substr($hex, $i, 2) !== '02') { return ''; }
    $rLen = hexdec(substr($hex, $i + 2, 2)) * 2;
    $r = substr($hex, $i + 4, $rLen);
    $j = $i + 4 + $rLen;
    if (substr($hex, $j, 2) !== '02' || strlen($hex) < $j + 4) { return ''; }
    $sLen = hexdec(substr($hex, $j + 2, 2)) * 2;
    $s = substr($hex, $j + 4, $sLen);
    if (strlen($r) !== $rLen || strlen($s) !== $sLen) { return ''; }
    // нормализация: убрать знаковый 00-префикс, дополнить нулями до 64 hex (32 байта)
    while (strlen($r) > 64 && substr($r, 0, 2) === '00') { $r = substr($r, 2); }
    while (strlen($s) > 64 && substr($s, 0, 2) === '00') { $s = substr($s, 2); }
    $r = str_pad($r, 64, '0', STR_PAD_LEFT);
    $s = str_pad($s, 64, '0', STR_PAD_LEFT);
    if (strlen($r) !== 64 || strlen($s) !== 64) { return ''; }
    return hex2bin($r . $s);
}

/* Отправить уведомление подпискам. Жёсткий бюджет времени: заказ не должен ждать
   рассылку — суммарно не более ~5 секунд, дальше пропускаем остальных.
   Протухшие (404/410) — удаляем. */
function webpushSendAll(string $title, string $body, string $url): array {
    if (!vapidKeysExist()) { return ['sent' => 0, 'error' => 'no-vapid-keys']; }
    $subs = db()->query('SELECT id, endpoint FROM push_subscriptions ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0; $skipped = 0; $dead = [];
    $deadline = microtime(true) + 5.0;
    foreach ($subs as $s) {
        if (microtime(true) > $deadline) { $skipped++; continue; }
        $auth = vapidAuthHeaders($s['endpoint']);
        if (!$auth) { continue; } // кривой endpoint у одной подписки не должен глушить остальные
        $ch = curl_init($s['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => 'nilov-order'], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $auth,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) { $sent++; }
        elseif ($code === 404 || $code === 410) { $dead[] = (int)$s['id']; }
    }
    if ($dead) {
        db()->exec('DELETE FROM push_subscriptions WHERE id IN (' . implode(',', $dead) . ')');
    }
    return ['sent' => $sent, 'dead' => count($dead), 'skipped' => $skipped, 'total' => count($subs)];
}
