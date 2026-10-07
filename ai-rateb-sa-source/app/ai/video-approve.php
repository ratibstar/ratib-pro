<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../media/library.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();
$campaign = media_owned_campaign((int) ($_POST['campaign_id'] ?? 0));
$jobId = (int) ($_POST['job_id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM video_jobs WHERE id = ? AND campaign_id = ? AND user_id = ? AND status = 'completed' AND output_media_id IS NOT NULL");
$stmt->execute([$jobId, (int) $campaign['id'], (int) $campaign['user_id']]);
$job = $stmt->fetch();
if (!$job) {
    header('Location: /campaign.php?id=' . (int) $campaign['id'] . '&video_error=invalid#video');
    exit;
}
db()->prepare("UPDATE video_jobs SET approval_status = 'approved' WHERE id = ? AND user_id = ?")->execute([$jobId, (int) $campaign['user_id']]);
header('Location: /campaign.php?id=' . (int) $campaign['id'] . '#video');
