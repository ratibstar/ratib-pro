<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../video/engine.php';
require_login();

$campaign = media_owned_campaign((int) ($_GET['campaign_id'] ?? 0));
session_write_close();
$job = rateb_video_latest_job(db(), (int) $campaign['id'], (int) $campaign['user_id']);
$arabic = ui_language() === 'ar';
if (!$job) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'status' => 'empty', 'error' => ''], JSON_UNESCAPED_UNICODE);
    exit;
}
$job = rateb_video_advance(db(), $job, $campaign);
$scenes = json_decode((string) $job['storyboard'], true);
$seconds = is_array($scenes) ? count($scenes) * rateb_video_clip_seconds() : 0;
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => (string) $job['status'] !== 'failed',
    'status' => (string) $job['status'],
    'approval' => (string) $job['approval_status'],
    'media_id' => (int) ($job['output_media_id'] ?? 0),
    'seconds' => $seconds,
    'format' => '9:16',
    'error' => (string) $job['error_code'] === '' ? '' : rateb_video_message((string) $job['error_code'], $arabic),
], JSON_UNESCAPED_UNICODE);
