<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$id = (int) ($_POST['campaign_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$pdo = db();

$owned = $pdo->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
$owned->execute([$id, $userId]);
$source = $owned->fetch();
if (!$source) {
    http_response_code(404);
    exit('Campaign not found.');
}

$title = trim((string) $source['title']);
$copyTitle = $title === '' ? 'Campaign copy' : $title . ' — copy';

try {
    $pdo->beginTransaction();
    $insert = $pdo->prepare('INSERT INTO campaigns (user_id, title, product_name, description, price, target_customer, status, objective, start_date, end_date, budget, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $insert->execute([
        $userId,
        mb_substr($copyTitle, 0, 255),
        $source['product_name'] ?? '',
        $source['description'] ?? '',
        $source['price'] ?? null,
        $source['target_customer'] ?? '',
        'draft',
        $source['objective'] ?? null,
        $source['start_date'] ?? null,
        $source['end_date'] ?? null,
        $source['budget'] ?? null,
    ]);
    $newId = (int) $pdo->lastInsertId();

    $outputs = $pdo->prepare('SELECT output_type, content FROM campaign_outputs WHERE campaign_id = ?');
    $outputs->execute([$id]);
    $copyOutput = $pdo->prepare('INSERT INTO campaign_outputs (campaign_id, output_type, content, approval_status) VALUES (?, ?, ?, ?)');
    foreach ($outputs->fetchAll() as $row) {
        $copyOutput->execute([$newId, $row['output_type'], $row['content'], 'draft']);
    }

    $items = $pdo->prepare('SELECT title, body, item_date FROM campaign_items WHERE campaign_id = ? AND user_id = ?');
    $items->execute([$id, $userId]);
    $copyItem = $pdo->prepare('INSERT INTO campaign_items (campaign_id, user_id, title, body, item_date) VALUES (?, ?, ?, ?, ?)');
    foreach ($items->fetchAll() as $row) {
        $copyItem->execute([$newId, $userId, $row['title'], $row['body'], $row['item_date']]);
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: /dashboard.php?copy_error=1');
    exit;
}

header('Location: /campaign.php?id=' . $newId);
exit;
