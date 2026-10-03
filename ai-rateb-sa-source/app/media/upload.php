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

try {
    media_store_bytes($campaign, $kind, 'upload', (string) ($file['name'] ?? ''), $mime, $bytes);
} catch (Throwable $error) {
    media_redirect($campaignId, 'upload');
}
media_redirect($campaignId);
