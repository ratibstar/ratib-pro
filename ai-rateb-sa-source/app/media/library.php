<?php
declare(strict_types=1);

const MEDIA_ROOT = '/home/admin/.campaign-media';

function media_kind_limits(): array
{
    return [
        'image' => 8 * 1024 * 1024,
        'video' => 256 * 1024 * 1024,
        'audio' => 10 * 1024 * 1024,
    ];
}

function media_kind_mimes(): array
{
    return [
        'image' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ],
        'video' => [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
        ],
        'audio' => [
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            'audio/mp4' => 'm4a',
        ],
    ];
}

function media_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g') {
        return (int) ($number * 1073741824);
    }
    if ($unit === 'm') {
        return (int) ($number * 1048576);
    }
    if ($unit === 'k') {
        return (int) ($number * 1024);
    }
    return (int) $number;
}

function media_max_bytes(string $kind): int
{
    $limits = media_kind_limits();
    $wanted = $limits[$kind] ?? 0;
    $upload = media_ini_bytes((string) ini_get('upload_max_filesize'));
    $post = media_ini_bytes((string) ini_get('post_max_size'));
    $ceiling = $wanted;
    if ($upload > 0) {
        $ceiling = min($ceiling, $upload);
    }
    if ($post > 524288) {
        $ceiling = min($ceiling, $post - 262144);
    }
    return max(0, $ceiling);
}

function media_owned_campaign(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, (int) $_SESSION['user_id']]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        http_response_code(404);
        exit('Campaign not found.');
    }
    return $campaign;
}

function media_owned_row(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM campaign_media WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, (int) $_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Not found.');
    }
    return $row;
}

function media_safe_download_name(string $name, string $extension): string
{
    $name = basename(str_replace(["\0", '\\'], ['', '/'], $name));
    $name = preg_replace('/[\r\n"]/', '', $name) ?? '';
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'media.' . $extension;
    }
    if (strlen($name) > 180) {
        $name = substr($name, 0, 180);
    }
    return $name;
}

function media_reject_active_content(string $bytes): bool
{
    $head = substr($bytes, 0, 64);
    if ($head === '') {
        return true;
    }
    if (str_starts_with($head, '<?') || str_starts_with($head, "\x7fELF") || str_starts_with($head, 'MZ')) {
        return true;
    }
    $lower = strtolower($head);
    return str_contains($lower, '<html') || str_contains($lower, '<svg') || str_contains($lower, '<script');
}

function media_detect_mime(string $bytes, string $kind): ?string
{
    $map = media_kind_mimes()[$kind] ?? null;
    if ($map === null) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) $finfo->buffer($bytes));
    if (!isset($map[$mime])) {
        return null;
    }
    if ($kind === 'image') {
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            return null;
        }
        $detected = image_type_to_mime_type((int) $info[2]);
        if ($detected !== $mime) {
            return null;
        }
    }
    return $mime;
}

function media_store_bytes(array $campaign, string $kind, string $source, string $originalName, string $mime, string $bytes): int
{
    $map = media_kind_mimes()[$kind] ?? null;
    if ($map === null || !isset($map[$mime]) || ($source !== 'upload' && $source !== 'ai')) {
        throw new RuntimeException('Unsupported media.');
    }
    if (media_reject_active_content($bytes)) {
        throw new RuntimeException('Unsupported media.');
    }
    $extension = $map[$mime];
    $stored = bin2hex(random_bytes(16)) . '.' . $extension;
    $dir = MEDIA_ROOT . '/' . (int) $campaign['id'];
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Storage unavailable.');
    }
    $path = $dir . '/' . $stored;
    if (file_put_contents($path, $bytes, LOCK_EX) === false) {
        throw new RuntimeException('Storage unavailable.');
    }
    chmod($path, 0600);
    try {
        $stmt = db()->prepare('INSERT INTO campaign_media (campaign_id, user_id, kind, source, original_name, stored_name, mime, bytes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            (int) $campaign['id'],
            (int) $campaign['user_id'],
            $kind,
            $source,
            media_safe_download_name($originalName, $extension),
            $stored,
            $mime,
            strlen($bytes),
        ]);
    } catch (Throwable $error) {
        @unlink($path);
        throw $error;
    }
    return (int) db()->lastInsertId();
}

function media_absolute_path(array $row): string
{
    $name = (string) $row['stored_name'];
    if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif|mp4|webm|mp3|wav|ogg|m4a)$/', $name) !== 1) {
        http_response_code(404);
        exit('Not found.');
    }
    $root = realpath(MEDIA_ROOT);
    $full = realpath(MEDIA_ROOT . '/' . (int) $row['campaign_id'] . '/' . $name);
    if ($root === false || $full === false || !str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit('Not found.');
    }
    return $full;
}

function media_redirect(int $campaignId, string $error = ''): void
{
    $url = '/campaign.php?id=' . $campaignId;
    if ($error !== '') {
        $url .= '&media_error=' . rawurlencode($error);
    }
    $panel = (string) ($_POST['panel'] ?? $_GET['panel'] ?? '');
    if (in_array($panel, ['images', 'voice', 'video'], true)) {
        $url .= '#' . $panel;
    }
    header('Location: ' . $url);
    exit;
}
