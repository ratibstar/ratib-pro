<?php
require_once __DIR__.'/app/bootstrap.php';
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
header('Location: /campaign.php?id='.db()->lastInsertId());
exit;
