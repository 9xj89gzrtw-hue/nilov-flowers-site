<?php
declare(strict_types=1);

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function allSettings(): array
{
    $out = [];
    foreach (db()->query('SELECT key, value FROM settings') as $row) {
        $out[$row['key']] = $row['value'];
    }
    return $out;
}

function saveSettings(array $values): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    foreach ($values as $k => $v) {
        $stmt->execute([':k' => $k, ':v' => (string)$v]);
    }
}

function formatPrice(int $v): string
{
    return number_format($v, 0, ',', ' ') . ' ₽';
}

function productPrice(array $p): int
{
    return ($p['sale_price'] !== null && $p['sale_price'] !== '' && (int)$p['sale_price'] > 0)
        ? (int)$p['sale_price'] : (int)$p['price'];
}

function slugify(string $s): string
{
    $trans = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
        'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
        'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, $trans);
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
    return trim($s, '-') ?: 'product-' . time();
}

function statuses(): array
{
    return [
        'new' => 'Новый',
        'confirmed' => 'Подтверждён',
        'done' => 'Выполнен',
        'canceled' => 'Отменён',
        'unredeemed' => 'Не выкуплен',
    ];
}

/**
 * Сохраняет загруженное изображение с проверкой MIME.
 * @return string имя файла в каталоге или ''
 */
function saveUpload(array $file, string $dir): string
{
    if (!isset($file['tmp_name']) || $file['tmp_name'] === '' || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return '';
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return '';
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return '';
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (string)($info['mime'] ?? '');
    if (!isset($allowed[$mime])) {
        return '';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return '';
    }
    return $name;
}

function deleteImage(string $name, string $dir): void
{
    if ($name !== '' && !str_contains('/', $name)) {
        $path = $dir . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
