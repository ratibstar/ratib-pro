<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/library.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$id = (int) ($_POST['id'] ?? 0);
$row = media_owned_row($id);
$path = media_absolute_path($row);
db()->prepare('DELETE FROM campaign_media WHERE id = ? AND user_id = ?')->execute([$id, (int) $_SESSION['user_id']]);
if (is_file($path)) {
    unlink($path);
}
media_redirect((int) $row['campaign_id']);
