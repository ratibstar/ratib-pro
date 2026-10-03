<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/library.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$row = media_owned_row($id);
$path = media_absolute_path($row);
$mime = (string) $row['mime'];
$allowed = [];
foreach (media_kind_mimes() as $map) {
    foreach ($map as $item => $extension) {
        $allowed[$item] = true;
    }
}
if (!isset($allowed[$mime]) || !is_file($path)) {
    http_response_code(404);
    exit('Not found.');
}

$download = (string) ($_GET['download'] ?? '') === '1';
$name = media_safe_download_name((string) $row['original_name'], pathinfo((string) $row['stored_name'], PATHINFO_EXTENSION));
$ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'media';
if ($ascii === '' || $ascii === '_') {
    $ascii = 'media';
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, no-store');
header(
    'Content-Disposition: ' . ($download ? 'attachment' : 'inline')
    . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name)
);
readfile($path);
exit;
