<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/app/maintenance/credits.php';

$dry = in_array('--dry-run', $argv, true);
$command = '';
foreach ($argv as $arg) {
    if ($arg === 'text-repair' || $arg === 'campaign72') {
        $command = $arg;
    }
}
if ($command === '') {
    fwrite(STDERR, "Usage: php cli/maintenance-credits.php [--dry-run] text-repair|campaign72\n");
    exit(2);
}

$table = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
$table->execute(['maintenance_grants']);
if ((int) $table->fetchColumn() < 1) {
    fwrite(STDERR, "maintenance_grants is missing. Apply migrations/2026-11-11-maintenance-grants.sql first. No credits were changed.\n");
    exit(1);
}

if ($command === 'campaign72') {
    $owner = (int) ($pdo->query('SELECT user_id FROM campaigns WHERE id = 72')->fetchColumn() ?: 0);
    if ($owner < 1) {
        fwrite(STDOUT, "campaign 72 has no owner. Nothing to do.\n");
        exit(0);
    }
    if ($dry) {
        fwrite(STDOUT, "dry-run campaign72 user {$owner} amount 200\n");
        exit(0);
    }
    $result = rateb_maintenance_grant($pdo, 'test_credit_grant', $owner, null, 200, 'operator campaign 72 grant', false);
    fwrite(STDOUT, $result . "\n");
    exit($result === 'applied' || $result === 'duplicate' ? 0 : 1);
}

$rows = $pdo->query("SELECT e.user_id, e.campaign_id,
        SUM(e.operation = 'text_campaign' AND e.credits_deducted > 0) AS charged,
        SUM(e.operation = 'text_campaign_refund') AS refunded
    FROM usage_events e
    WHERE e.campaign_id IS NOT NULL
      AND e.operation IN ('text_campaign', 'text_campaign_refund')
    GROUP BY e.user_id, e.campaign_id")->fetchAll();
$did = 0;
foreach ($rows as $row) {
    $amount = rateb_text_repair_credit((int) $row['charged'], (int) $row['refunded']);
    if ($amount < 1) {
        continue;
    }
    $userId = (int) $row['user_id'];
    $campaignId = (int) $row['campaign_id'];
    if ($dry) {
        fwrite(STDOUT, "dry-run text-repair user {$userId} campaign {$campaignId} amount {$amount}\n");
        $did++;
        continue;
    }
    $result = rateb_maintenance_grant($pdo, 'text_campaign_repair', $userId, $campaignId, $amount, 'operator text campaign repair', false);
    fwrite(STDOUT, $result . " user {$userId} campaign {$campaignId} amount {$amount}\n");
    $did++;
}
if ($did === 0) {
    fwrite(STDOUT, "no eligible text repairs\n");
}
exit(0);
