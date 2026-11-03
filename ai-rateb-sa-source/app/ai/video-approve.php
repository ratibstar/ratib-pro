<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../plans.php';
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
$media = db()->prepare('SELECT original_name FROM campaign_media WHERE id = ? AND user_id = ? AND kind = \'video\'');
$media->execute([(int) $job['output_media_id'], (int) $campaign['user_id']]);
$videoName = (string) ($media->fetchColumn() ?: '');
if (str_starts_with($videoName, 'hold-')) {
    $userId = (int) $campaign['user_id'];
    $cost = rateb_get_feature_cost('video_storyboard');
    $balance = db()->prepare('SELECT credits_balance FROM users WHERE id = ?');
    $balance->execute([$userId]);
    if ((int) $balance->fetchColumn() < $cost || rateb_credit_charge(db(), $userId, 'video_storyboard', (int) $campaign['id']) < 1) {
        rateb_credit_stop($userId, 'video_storyboard');
    }
    db()->prepare('UPDATE campaign_media SET original_name = ? WHERE id = ? AND user_id = ? AND original_name = ?')
        ->execute(['paid-' . substr($videoName, 5), (int) $job['output_media_id'], $userId, $videoName]);
}
db()->prepare("UPDATE video_jobs SET approval_status = 'approved' WHERE id = ? AND user_id = ?")->execute([$jobId, (int) $campaign['user_id']]);
header('Location: /campaign.php?id=' . (int) $campaign['id'] . '#video');
