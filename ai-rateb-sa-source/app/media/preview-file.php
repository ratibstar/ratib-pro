<?php
declare(strict_types=1);
/**
 * Serve a temporary AI image preview owned by the logged-in user.
 * Previews are NOT campaign_media rows and do not imply credit charges.
 */
require_once __DIR__ . '/../bootstrap.php';
require_login();

$token = strtolower(trim((string) ($_GET['token'] ?? '')));
if (preg_match('/^[a-f0-9]{16,64}$/', $token) !== 1) {
    http_response_code(404);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$root = (defined('MEDIA_ROOT') ? MEDIA_ROOT : '/home/admin/.campaign-media') . '/previews/' . $userId;
$metaPath = $root . '/' . $token . '.json';
if (!is_file($metaPath)) {
    http_response_code(404);
    exit;
}
$meta = json_decode((string) file_get_contents($metaPath), true);
if (!is_array($meta) || (int) ($meta['user_id'] ?? 0) !== $userId) {
    http_response_code(403);
    exit;
}
$file = $root . '/' . (string) ($meta['stored_name'] ?? '');
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
$mime = (string) ($meta['mime'] ?? 'application/octet-stream');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
