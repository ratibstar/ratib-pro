<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/library.php';
require_once __DIR__ . '/../plans.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$id = (int) ($_POST['id'] ?? 0);
$row = media_owned_row($id);
$campaignId = (int) $row['campaign_id'];
$userId = (int) $_SESSION['user_id'];
$name = (string) ($row['original_name'] ?? '');
if ((string) $row['kind'] !== 'image' || (string) $row['source'] !== 'ai' || !str_starts_with($name, 'hold-')) {
    media_redirect($campaignId);
}
$cost = rateb_get_feature_cost('ai_image');
$balance = db()->prepare('SELECT credits_balance FROM users WHERE id = ?');
$balance->execute([$userId]);
if ((int) $balance->fetchColumn() < $cost) {
    rateb_credit_stop($userId, 'ai_image');
}
$paid = 'paid-' . substr($name, 5);
$mark = db()->prepare('UPDATE campaign_media SET original_name = ? WHERE id = ? AND user_id = ? AND original_name = ?');
$mark->execute([$paid, $id, $userId, $name]);
if ($mark->rowCount() !== 1) {
    media_redirect($campaignId);
}
$charge = rateb_credit_charge(db(), $userId, 'ai_image', $campaignId);
if ($charge < 1) {
    db()->prepare('UPDATE campaign_media SET original_name = ? WHERE id = ? AND user_id = ? AND original_name = ?')
        ->execute([$name, $id, $userId, $paid]);
    rateb_credit_stop($userId, 'ai_image');
}
media_redirect($campaignId);
