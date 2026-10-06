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
session_write_close();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$kind = (string) ($_POST['kind'] ?? '');
$campaign = media_owned_campaign($campaignId);
if (!isset(media_kind_mimes()[$kind])) {
    media_redirect($campaignId, 'type');
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])) {
    media_redirect($campaignId, 'upload');
}
if ((int) $file['error'] === UPLOAD_ERR_INI_SIZE || (int) $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    media_redirect($campaignId, 'size');
}
if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
    media_redirect($campaignId, 'upload');
}

$max = media_max_bytes($kind);
$size = (int) $file['size'];
if ($size < 1 || $size > $max) {
    media_redirect($campaignId, 'size');
}

$bytes = file_get_contents((string) $file['tmp_name']);
if ($bytes === false || strlen($bytes) !== $size) {
    media_redirect($campaignId, 'upload');
}
if (media_reject_active_content($bytes)) {
    media_redirect($campaignId, 'type');
}
$mime = media_detect_mime($bytes, $kind);
if ($mime === null) {
    media_redirect($campaignId, 'type');
}
if ($kind === 'video') {
    require_once __DIR__ . '/../idea.php';
    require_once __DIR__ . '/../plans.php';
    $existing = db()->prepare('SELECT COUNT(*) FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = ?');
    $existing->execute([$campaignId, (int) $campaign['user_id'], 'video']);
    if ((int) $existing->fetchColumn() >= 1) {
        media_redirect($campaignId, 'video_one');
    }
    $seconds = rateb_video_seconds($bytes, $mime);
    if ($seconds === null) {
        media_redirect($campaignId, 'video_unknown');
    }
    $plan = rateb_user_plan(db(), (int) $_SESSION['user_id']);
    if ($seconds > (int) ($plan['video_max_seconds'] ?? 0)) {
        media_redirect($campaignId, 'video_long');
    }
}

try {
    media_store_bytes($campaign, $kind, 'upload', (string) ($file['name'] ?? ''), $mime, $bytes);
} catch (Throwable $error) {
    media_redirect($campaignId, 'upload');
}
media_redirect($campaignId);
