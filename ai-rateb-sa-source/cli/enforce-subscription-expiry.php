<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/plans.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

$limit = (int) ($argv[1] ?? 80);
$stats = rateb_subscription_expire_due_batch($pdo, $limit);
echo 'subscription_expiry_scanned=' . (int) $stats['scanned'] . "\n";
echo 'subscription_expiry_downgraded=' . (int) $stats['downgraded'] . "\n";