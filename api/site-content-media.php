<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/site-content.php';

$name = (string) ($_GET['f'] ?? '');
$name = basename(str_replace('\\', '/', $name));
if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
    http_response_code(404);
    exit;
}
$ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
$allowed = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
];
if (!isset($allowed[$ext])) {
    http_response_code(404);
    exit;
}

$base = function_exists('ratib_site_content_media_storage_dir')
    ? ratib_site_content_media_storage_dir()
    : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ratib_cms_media');
$file = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $name;
if (!is_file($file)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $allowed[$ext]);
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: public, max-age=604800, immutable');
readfile($file);
exit;
