<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/moyasar-sync.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$orderId = (int) ($_GET['order'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$pdo = $GLOBALS['pdo'];

if ($orderId < 1) {
    http_response_code(422);
    echo json_encode(['error' => 'order', 'status' => 'unknown'], JSON_UNESCAPED_UNICODE);
    exit;
}

rateb_payment_sync_order($pdo, $orderId, $userId, true);

$stmt = $pdo->prepare('SELECT id, status, package_code FROM payment_orders WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$orderId, $userId]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found', 'status' => 'unknown'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [
    'status' => (string) $row['status'],
    'package' => (string) $row['package_code'],
];
if ((string) $row['status'] === 'paid') {
    $r = $pdo->prepare('SELECT id FROM payment_receipts WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $r->execute([$userId]);
    $receipt = $r->fetch();
    if ($receipt) {
        $payload['receipt_id'] = (int) $receipt['id'];
    }
}
echo json_encode($payload, JSON_UNESCAPED_UNICODE);