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

$id = (int) ($_POST['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$stmt = db()->prepare("SELECT id, campaign_id, kind, source, approval_status FROM campaign_media WHERE id = ? AND user_id = ? AND kind = 'image'");
$stmt->execute([$id, $userId]);
$media = $stmt->fetch();
if (!$media) {
    http_response_code(404);
    exit(ui_language() === 'ar' ? 'الصورة غير موجودة.' : 'Image not found.');
}
$campaignId = (int) $media['campaign_id'];
$back = '/campaign.php?id=' . $campaignId . '#images';
if ((string) ($media['approval_status'] ?? '') === 'approved') {
    header('Location: ' . $back);
    exit;
}
$paid = db()->prepare("SELECT COUNT(*) FROM usage_events WHERE user_id = ? AND campaign_id = ? AND operation = 'ai_image' AND status = 'completed' AND credits_deducted > 0");
$paid->execute([$userId, $campaignId]);
$approved = db()->prepare("SELECT COUNT(*) FROM campaign_media WHERE user_id = ? AND campaign_id = ? AND kind = 'image' AND source = 'ai' AND approval_status = 'approved'");
$approved->execute([$userId, $campaignId]);
$needsCharge = (int) $approved->fetchColumn() >= (int) $paid->fetchColumn();
if ($needsCharge && rateb_credit_charge(db(), $userId, 'ai_image', $campaignId) < 1) {
    header('Location: /campaign.php?id=' . $campaignId . '&credits=1#images');
    exit;
}
db()->prepare("UPDATE campaign_media SET approval_status = 'approved' WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
header('Location: ' . $back);
exit;
