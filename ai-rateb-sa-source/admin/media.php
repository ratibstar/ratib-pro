<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/lib.php';
require_once dirname(__DIR__) . '/app/media/library.php';

$adminActor = require_admin_access($pdo);
admin_require_permission('campaigns.view');

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM campaign_media WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('Not found.');
}
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
$name = media_safe_download_name((string) $row['original_name'], pathinfo((string) $row['stored_name'], PATHINFO_EXTENSION));
$ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'media';
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, no-store');
header('Content-Disposition: ' . (((string) ($_GET['download'] ?? '') === '1') ? 'attachment' : 'inline') . '; filename="' . $ascii . '"');
readfile($path);
exit;
