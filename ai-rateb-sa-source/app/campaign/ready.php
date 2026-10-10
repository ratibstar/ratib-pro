<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$stmt = db()->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
$stmt->execute([$campaignId, $userId]);
$campaign = $stmt->fetch();
if (!$campaign) {
    http_response_code(404);
    exit('Campaign not found.');
}

$outputs = db()->prepare('SELECT output_type, content, approval_status FROM campaign_outputs WHERE campaign_id = ?');
$outputs->execute([$campaignId]);
$strategy = false;
$copy = false;
foreach ($outputs as $row) {
    if ((string) $row['output_type'] === 'strategy' && (string) $row['approval_status'] === 'approved') {
        $strategy = true;
    }
    if (in_array((string) $row['output_type'], rateb_copy_types(), true) && (string) $row['approval_status'] === 'approved') {
        $copy = true;
    }
}
$media = db()->prepare('SELECT kind FROM campaign_media WHERE campaign_id = ? AND user_id = ?');
$media->execute([$campaignId, $userId]);
$kinds = [];
foreach ($media as $row) {
    $kinds[(string) $row['kind']] = true;
}
$idea = trim((string) ($campaign['product_name'] ?? '') . (string) ($campaign['description'] ?? '')) !== '';
$approved = db()->prepare("SELECT j.id FROM video_jobs j INNER JOIN campaign_media m ON m.id = j.output_media_id AND m.source = 'ai' AND m.kind = 'video' WHERE j.campaign_id = ? AND j.user_id = ? AND j.approval_status = 'approved' AND j.status = 'completed' LIMIT 1");
$approved->execute([$campaignId, $userId]);
if (!$idea || !$strategy || !$copy || empty($kinds['image']) || empty($kinds['audio']) || (!$approved->fetch() && empty($kinds['video']))) {
    header('Location: /campaign.php?id=' . $campaignId . '&ready_error=1#ready');
    exit;
}

db()->prepare("UPDATE campaigns SET status = 'ready', updated_at = NOW() WHERE id = ? AND user_id = ?")->execute([$campaignId, $userId]);
header('Location: /campaign.php?id=' . $campaignId . '#ready');
exit;
