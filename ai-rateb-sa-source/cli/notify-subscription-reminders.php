<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/notify/events.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

$rows = $pdo->query("SELECT s.user_id, p.code, s.period_end
    FROM subscriptions s
    INNER JOIN plans p ON p.id = s.plan_id
    WHERE s.status = 'active' AND p.code <> 'free'
      AND s.period_end >= NOW()
      AND s.period_end < DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchAll();
$count = 0;
foreach ($rows as $row) {
    rateb_notify_subscription_ending($pdo, (int) $row['user_id'], (string) $row['code'], (string) $row['period_end']);
    $count++;
}
echo "subscription_reminders={$count}\n";