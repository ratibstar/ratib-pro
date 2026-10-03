<?php
declare(strict_types=1);

require_once __DIR__ . '/library.php';
require_login();

$row = brand_owned();
$stored = (string) ($row['logo_stored'] ?? '');
$mime = (string) ($row['logo_mime'] ?? '');
if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $stored) !== 1) {
    http_response_code(404);
    exit('Not found.');
}
$path = BRAND_ROOT . '/' . (int) $_SESSION['user_id'] . '/' . $stored;
$real = realpath($path);
$root = realpath(BRAND_ROOT . '/' . (int) $_SESSION['user_id']);
if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
    http_response_code(404);
    exit('Not found.');
}
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($real));
header('Cache-Control: private, no-store');
header('Content-Disposition: inline; filename="logo.' . pathinfo($stored, PATHINFO_EXTENSION) . '"');
readfile($real);
exit;
