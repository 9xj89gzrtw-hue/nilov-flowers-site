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
    $raw = '';
    if (preg_match('/^30[0-9a-f]{2}02([0-9a-f]{2})([0-9a-f]+)02([0-9a-f]{2})([0-9a-f]+)$/i', bin2hex($derSig), $m)) {
        $r = $m[2]; $s = $m[4];
        if (strlen($r) === 66 && str_starts_with($r, '00')) { $r = substr($r, 2); }
        if (strlen($s) === 66 && str_starts_with($s, '00')) { $s = substr($s, 2); }
        $raw = hex2bin(str_pad($r, 64, '0', STR_PAD_LEFT) . str_pad($s, 64, '0', STR_PAD_LEFT));
    }
    if ($raw === '' || strlen($raw) !== 64) { return []; }
    return [
        'Authorization: webpush t=' . $signing . ',k=' . $pub,
        'TTL: 86400',
        'Content-Type: application/octet-stream',
    ];
}

/* Отправить уведомление всем подпискам (cap 200, curl 4с). Протухшие (404/410) — удалить. */
function webpushSendAll(string $title, string $body, string $url): array {
    if (!vapidKeysExist()) { return ['sent' => 0, 'error' => 'no-vapid-keys']; }
    $subs = db()->query('SELECT id, endpoint FROM push_subscriptions ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0; $dead = [];
    foreach ($subs as $s) {
        $auth = vapidAuthHeaders($s['endpoint']);
        if (!$auth) { break; }
        $ch = curl_init($s['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => 'nilov-order'], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $auth,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
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
    return ['sent' => $sent, 'dead' => count($dead), 'total' => count($subs)];
}
