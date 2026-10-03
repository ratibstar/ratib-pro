<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_login();

const BRAND_ROOT = '/home/admin/.brand-kits';

function brand_owned(): array
{
    $stmt = db()->prepare('SELECT * FROM brand_kits WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    return $row ?: [
        'brand_name' => '',
        'logo_stored' => null,
        'logo_mime' => null,
        'primary_color' => '#0f766e',
        'secondary_color' => '#111827',
        'preferred_language' => 'en',
        'tone' => '',
        'contact' => '',
    ];
}

function brand_store_logo(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['stored' => null, 'mime' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('upload');
    }
    $bytes = (string) file_get_contents((string) $file['tmp_name']);
    if ($bytes === '' || strlen($bytes) > 2 * 1024 * 1024) {
        throw new RuntimeException('size');
    }
    if (str_starts_with($bytes, '<?') || str_starts_with($bytes, "\x7fELF") || str_starts_with($bytes, 'MZ')) {
        throw new RuntimeException('type');
    }
    $mime = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes));
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $info = @getimagesizefromstring($bytes);
    if (!isset($map[$mime]) || $info === false || image_type_to_mime_type((int) $info[2]) !== $mime) {
        throw new RuntimeException('type');
    }
    $userId = (int) $_SESSION['user_id'];
    $dir = BRAND_ROOT . '/' . $userId;
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('upload');
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $map[$mime];
    if (file_put_contents($dir . '/' . $stored, $bytes, LOCK_EX) === false) {
        throw new RuntimeException('upload');
    }
    chmod($dir . '/' . $stored, 0600);
    return ['stored' => $stored, 'mime' => $mime];
}
