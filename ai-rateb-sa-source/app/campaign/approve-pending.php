<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../media/library.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$owned = db()->prepare('SELECT id FROM campaigns WHERE id = ? AND user_id = ?');
$owned->execute([$campaignId, $userId]);
if (!$owned->fetch()) {
    http_response_code(404);
    exit(ui_language() === 'ar' ? 'الحملة غير موجودة.' : 'Campaign not found.');
}

$strategy = db()->prepare("SELECT id, content, approval_status FROM campaign_outputs WHERE campaign_id = ? AND output_type = 'strategy' ORDER BY id DESC LIMIT 1");
$strategy->execute([$campaignId]);
$strategyRow = $strategy->fetch();
if ($strategyRow && (string) $strategyRow['approval_status'] !== 'approved' && rateb_strategy_document((string) $strategyRow['content']) !== null) {
    if (!rateb_campaign_feature_paid($userId, 'text_campaign', $campaignId) && rateb_credit_charge(db(), $userId, 'text_campaign', $campaignId) < 1) {
        rateb_credit_stop($userId, 'text_campaign');
    }
    db()->prepare("UPDATE campaign_outputs SET approval_status = 'approved' WHERE id = ? AND campaign_id = ?")->execute([(int) $strategyRow['id'], $campaignId]);
}

$copies = db()->prepare("SELECT id FROM campaign_outputs WHERE campaign_id = ? AND approval_status <> 'approved' AND output_type <> 'strategy' AND output_type <> 'voice_script'");
$copies->execute([$campaignId]);
foreach ($copies->fetchAll() as $copy) {
    db()->prepare("UPDATE campaign_outputs SET approval_status = 'approved' WHERE id = ? AND campaign_id = ?")->execute([(int) $copy['id'], $campaignId]);
}

$held = db()->prepare("SELECT id, kind, original_name FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND source = 'ai' AND original_name LIKE 'hold-%' AND kind IN ('image', 'audio') ORDER BY id");
$held->execute([$campaignId, $userId]);
foreach ($held->fetchAll() as $item) {
    $feature = (string) $item['kind'] === 'audio' ? 'saudi_voice' : 'ai_image';
    $name = (string) $item['original_name'];
    $paid = 'paid-' . substr($name, 5);
    $mark = db()->prepare('UPDATE campaign_media SET original_name = ? WHERE id = ? AND user_id = ? AND original_name = ?');
    $mark->execute([$paid, (int) $item['id'], $userId, $name]);
    if ($mark->rowCount() !== 1) {
        continue;
    }
    if (rateb_credit_charge(db(), $userId, $feature, $campaignId) < 1) {
        db()->prepare('UPDATE campaign_media SET original_name = ? WHERE id = ? AND user_id = ? AND original_name = ?')
            ->execute([$name, (int) $item['id'], $userId, $paid]);
        rateb_credit_stop($userId, $feature);
    }
}

$job = db()->prepare("SELECT id, output_media_id FROM video_jobs WHERE campaign_id = ? AND user_id = ? AND status = 'completed' AND approval_status <> 'approved' AND output_media_id IS NOT NULL ORDER BY id DESC LIMIT 1");
$job->execute([$campaignId, $userId]);
$video = $job->fetch();
if ($video) {
    $media = db()->prepare('SELECT original_name FROM campaign_media WHERE id = ? AND user_id = ?');
    $media->execute([(int) $video['output_media_id'], $userId]);
    $videoName = (string) ($media->fetchColumn() ?: '');
    if (str_starts_with($videoName, 'hold-')) {
        if (rateb_credit_charge(db(), $userId, 'video_storyboard', $campaignId) < 1) {
            rateb_credit_stop($userId, 'video_storyboard');
        }
        db()->prepare('UPDATE campaign_media SET original_name = ? WHERE id = ? AND user_id = ?')
            ->execute(['paid-' . substr($videoName, 5), (int) $video['output_media_id'], $userId]);
    }
    db()->prepare("UPDATE video_jobs SET approval_status = 'approved' WHERE id = ? AND user_id = ?")->execute([(int) $video['id'], $userId]);
}

header('Location: /campaign.php?id=' . $campaignId . '#idea');
exit;
