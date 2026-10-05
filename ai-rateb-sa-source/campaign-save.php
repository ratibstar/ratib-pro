<?php
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/plans.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST') exit('Method not allowed');
check_csrf();
$price = campaign_money_or_null((string) ($_POST['price'] ?? ''));
$budget = campaign_money_or_null((string) ($_POST['budget'] ?? ''));
$status = campaign_one_of((string) ($_POST['status'] ?? 'draft'), campaign_status_values(), 'draft');
$s=db()->prepare("INSERT INTO campaigns (user_id,title,product_name,description,price,target_customer,status,objective,start_date,end_date,budget,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())");
$s->execute([
    $_SESSION['user_id'],
    mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255),
    mb_substr(trim((string) ($_POST['product_name'] ?? '')), 0, 255),
    trim((string) ($_POST['description'] ?? '')),
    $price,
    mb_substr(trim((string) ($_POST['target_customer'] ?? '')), 0, 255),
    $status,
    mb_substr(trim((string) ($_POST['objective'] ?? '')), 0, 500),
    campaign_date_or_null((string) ($_POST['start_date'] ?? '')),
    campaign_date_or_null((string) ($_POST['end_date'] ?? '')),
    $budget,
]);
$campaignId = (int) db()->lastInsertId();
rateb_sync_brief(db(), (int) $_SESSION['user_id'], $campaignId, [
    'product' => (string) ($_POST['product_name'] ?? ''),
    'description' => (string) ($_POST['description'] ?? ''),
    'audience' => (string) ($_POST['target_customer'] ?? ''),
    'objective' => (string) ($_POST['objective'] ?? ''),
    'campaign_language' => (string) ($_POST['campaign_language'] ?? 'auto'),
]);
header('Location: /campaign.php?id='.$campaignId);
exit;
