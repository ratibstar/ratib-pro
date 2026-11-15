<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/payment/moyasar-sync.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

$days = (int) ($argv[1] ?? 7);
$stats = rateb_payment_reconcile_pending($pdo, $days, 50);
echo 'reconcile_checked=' . (int) $stats['checked'] . "\n";
echo 'reconcile_paid=' . (int) $stats['paid'] . "\n";
echo 'reconcile_failed=' . (int) $stats['failed'] . "\n";
echo 'reconcile_pending=' . (int) $stats['pending'] . "\n";