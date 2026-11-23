<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/guard.php';
require_once dirname(__DIR__) . '/app/schema.php';
require_once dirname(__DIR__) . '/migrations/platform_settings_schema.php';
require_once dirname(__DIR__) . '/app/plans.php';

$pdo = db();
$campaignId = (int) ($argv[1] ?? 80);

$stmt = $pdo->prepare('SELECT id, user_id, title, status FROM campaigns WHERE id = ? LIMIT 1');
$stmt->execute([$campaignId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
echo "campaign=" . json_encode($campaign, JSON_UNESCAPED_UNICODE) . PHP_EOL;
$userId = (int) ($campaign['user_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT id, operation, status, event_type, credits_deducted, created_at
     FROM usage_events
     WHERE user_id = ? AND (campaign_id = ? OR status = 'reserved')
     ORDER BY id DESC LIMIT 25"
);
$stmt->execute([$userId, $campaignId]);
echo "events:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo 'MEDIA_ROOT=' . (defined('MEDIA_ROOT') ? MEDIA_ROOT : 'undef') . PHP_EOL;
$jobDir = rateb_image_job_directory();
echo "jobDir={$jobDir}\n";
$file = rtrim($jobDir, '/\\') . DIRECTORY_SEPARATOR . 'user-' . $userId . '.json';
$alt = '/home/admin/.campaign-media/image-jobs/user-' . $userId . '.json';
foreach ([$file, $alt] as $path) {
    echo "path={$path} exists=" . (is_file($path) ? 'yes' : 'no') . PHP_EOL;
    if (is_file($path)) {
        echo file_get_contents($path) . PHP_EOL;
    }
}

$bal = $pdo->prepare('SELECT credits_balance FROM users WHERE id = ?');
$bal->execute([$userId]);
echo 'credits_balance=' . $bal->fetchColumn() . PHP_EOL;
