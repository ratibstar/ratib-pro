<?php
declare(strict_types=1);

/**
 * Run commercial maintenance once (safe for deploy smoke; subscription emails only if due).
 */
require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/payment/moyasar-sync.php';
require_once dirname(__DIR__) . '/app/plans.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

$reconcile = rateb_payment_reconcile_pending($pdo, 7, 25);
echo 'reconcile_checked=' . (int) $reconcile['checked'] . "\n";
echo 'reconcile_paid=' . (int) $reconcile['paid'] . "\n";

$expiry = rateb_subscription_expire_due_batch($pdo, 80);
echo 'expiry_scanned=' . (int) $expiry['scanned'] . "\n";
echo 'expiry_downgraded=' . (int) $expiry['downgraded'] . "\n";

require_once dirname(__DIR__) . '/app/notify/events.php';
$rows = $pdo->query("SELECT s.user_id, p.code, s.period_end
    FROM subscriptions s
    INNER JOIN plans p ON p.id = s.plan_id
    WHERE s.status = 'active' AND p.code <> 'free'
      AND s.period_end >= NOW()
      AND s.period_end < DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchAll();
$reminders = 0;
foreach ($rows as $row) {
    rateb_notify_subscription_ending($pdo, (int) $row['user_id'], (string) $row['code'], (string) $row['period_end']);
    $reminders++;
}
echo 'subscription_reminders=' . $reminders . "\n";
echo "commerce_now=ok\n";