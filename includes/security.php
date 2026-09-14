<?php
declare(strict_types=1);
/* includes/security.php — P0-фиксы критика robustness/security (4/10):
   1) http→https 301 (в .htaccess, тут — детект для заголовков);
   2) заголовки безопасности на всех HTML/JSON ответах;
   3) скрытие X-Powered-By;
   4) rate-limit по IP (файловый token-bucket — нет лишней таблицы);
   вызывается из util.php (его включают index/product/api/admin/login). */

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function security_headers(): void
{
    if (headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    /* Security-критик W40 re-check: CSP была декларативной (0 fetch-директив).
       Реальные ограничения при self-hosted стеке: инлайн-скрипты/стили страницы нужны,
       зато закрываем base/form/connect/frame — XSS-патч не уйдёт на чужой домен. */
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://mc.yandex.ru; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https://mc.yandex.ru https://yandex.ru; font-src 'self' https://fonts.gstatic.com; connect-src 'self' https://mc.yandex.ru https://*.api-metrica.tech; frame-src 'self' https://mc.yandex.ru; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; report-uri /api/csp-report.php; upgrade-insecure-requests");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    /* HSTS только по https (по http браузер игнорирует, но и не ругается) */
    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* Token-bucket по IP: файл в sys_get_temp_dir(). true = разрешить, false = превышено. */
function rl_check(string $bucket, int $max, int $windowSec): bool
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = sys_get_temp_dir() . '/rl_' . preg_replace('/[^a-z0-9_]/i', '', $bucket) . '_' . md5($ip);
    $now = time();
    $data = [];
    $raw = @file_get_contents($key);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = array_values(array_filter($decoded, static fn($t) => is_int($t) && ($now - $t) < $windowSec));
        }
    }
    if (count($data) >= $max) {
        return false;
    }
    $data[] = $now;
    @file_put_contents($key, json_encode($data), LOCK_EX);
    return true;
}

/* Минуты до разрешения следующей попытки (для Retry-After / сообщения). */
function rl_retry_after(string $bucket, int $windowSec): int
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = sys_get_temp_dir() . '/rl_' . preg_replace('/[^a-z0-9_]/i', '', $bucket) . '_' . md5($ip);
    $raw = @file_get_contents($key);
    if (!is_string($raw) || $raw === '') return $windowSec;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || $decoded === []) return $windowSec;
    $oldest = (int)min($decoded);
    return max(5, (int)ceil(($windowSec - (time() - $oldest)) / 60) * 60);
}

security_headers();
