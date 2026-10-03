<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$owned = db()->prepare('SELECT id FROM campaigns WHERE id = ? AND user_id = ?');
$owned->execute([$campaignId, $_SESSION['user_id']]);
if (!$owned->fetch()) {
    http_response_code(404);
    exit('Campaign not found.');
}

$itemId = (int) ($_POST['id'] ?? 0);
if ((string) ($_POST['action'] ?? '') === 'delete' && $itemId > 0) {
    $delete = db()->prepare('DELETE FROM campaign_items WHERE id = ? AND campaign_id = ? AND user_id = ?');
    $delete->execute([$itemId, $campaignId, $_SESSION['user_id']]);
    header('Location: /campaign.php?id=' . $campaignId . '#planner');
    exit;
}

$title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
$body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 20000);
if ($title === '' || $body === '') {
    header('Location: /campaign.php?id=' . $campaignId . '#planner');
    exit;
}
$date = campaign_date_or_null((string) ($_POST['item_date'] ?? ''));
$planner = campaign_one_of((string) ($_POST['planner_status'] ?? 'draft'), planner_status_values(), 'draft');
$approval = campaign_one_of((string) ($_POST['approval_status'] ?? 'draft'), approval_status_values(), 'draft');

if ($itemId > 0) {
    $update = db()->prepare('UPDATE campaign_items SET title=?, body=?, item_date=?, planner_status=?, approval_status=? WHERE id=? AND campaign_id=? AND user_id=?');
    $update->execute([$title, $body, $date, $planner, $approval, $itemId, $campaignId, $_SESSION['user_id']]);
} else {
    $insert = db()->prepare('INSERT INTO campaign_items (campaign_id, user_id, title, body, item_date, planner_status, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$campaignId, $_SESSION['user_id'], $title, $body, $date, $planner, $approval]);
}
header('Location: /campaign.php?id=' . $campaignId . '#planner');
exit;
