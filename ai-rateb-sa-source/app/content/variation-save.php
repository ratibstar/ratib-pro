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

$outputs = db()->prepare('SELECT output_type, content FROM campaign_outputs WHERE campaign_id = ? ORDER BY id');
$outputs->execute([$campaignId]);
$rows = $outputs->fetchAll();
if ($rows) {
    $label = 'Saved ' . gmdate('Y-m-d H:i');
    $insert = db()->prepare('INSERT INTO campaign_variations (campaign_id, user_id, label, output_type, content) VALUES (?, ?, ?, ?, ?)');
    foreach ($rows as $row) {
        $insert->execute([
            $campaignId,
            $_SESSION['user_id'],
            $label,
            mb_substr((string) $row['output_type'], 0, 64),
            (string) $row['content'],
        ]);
    }
}
header('Location: /campaign.php?id=' . $campaignId . '#variations');
exit;
