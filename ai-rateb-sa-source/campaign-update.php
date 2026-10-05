<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Method not allowed');
}
check_csrf();
$id = (int) ($_POST['campaign_id'] ?? 0);
$owned = db()->prepare('SELECT id FROM campaigns WHERE id = ? AND user_id = ?');
$owned->execute([$id, $_SESSION['user_id']]);
if (!$owned->fetch()) {
    http_response_code(404);
    exit('Campaign not found.');
}
$stmt = db()->prepare('UPDATE campaigns SET title=?, product_name=?, description=?, price=?, target_customer=?, status=?, objective=?, start_date=?, end_date=?, budget=?, updated_at=NOW() WHERE id=? AND user_id=?');
$stmt->execute([
    mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255),
    mb_substr(trim((string) ($_POST['product_name'] ?? '')), 0, 255),
    trim((string) ($_POST['description'] ?? '')),
    campaign_money_or_null((string) ($_POST['price'] ?? '')),
    mb_substr(trim((string) ($_POST['target_customer'] ?? '')), 0, 255),
    campaign_one_of((string) ($_POST['status'] ?? 'draft'), campaign_status_values(), 'draft'),
    mb_substr(trim((string) ($_POST['objective'] ?? '')), 0, 500),
    campaign_date_or_null((string) ($_POST['start_date'] ?? '')),
    campaign_date_or_null((string) ($_POST['end_date'] ?? '')),
    campaign_money_or_null((string) ($_POST['budget'] ?? '')),
    $id,
    $_SESSION['user_id'],
]);
rateb_sync_brief(db(), (int) $_SESSION['user_id'], $id, [
    'product' => (string) ($_POST['product_name'] ?? ''),
    'description' => (string) ($_POST['description'] ?? ''),
    'audience' => (string) ($_POST['target_customer'] ?? ''),
    'objective' => (string) ($_POST['objective'] ?? ''),
    'campaign_language' => (string) ($_POST['campaign_language'] ?? 'auto'),
]);
header('Location: /campaign.php?id=' . $id);
exit;
