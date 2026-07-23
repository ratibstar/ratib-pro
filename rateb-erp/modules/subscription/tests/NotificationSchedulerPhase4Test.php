<?php
declare(strict_types=1);

/**
 * Phase 4 — NotificationScheduler / Generator (no DB, no senders).
 * Run: php rateb-erp/modules/subscription/tests/NotificationSchedulerPhase4Test.php
 */

$mod = dirname(__DIR__);
$root = dirname($mod, 2);
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', $root);
}

foreach ([
    'SubscriptionStatus.php',
    'SubscriptionModule.php',
    'GracePeriodStatus.php',
    'GracePeriodPolicy.php',
    'GracePeriodEngine.php',
    'SubscriptionContext.php',
    'SubscriptionPolicy.php',
    'SubscriptionEngineStore.php',
    'InMemorySubscriptionEngineStore.php',
    'SubscriptionEngine.php',
    'NotificationType.php',
    'NotificationChannel.php',
    'NotificationDecision.php',
    'NotificationHistoryStore.php',
    'NotificationHistoryRepository.php',
    'InMemoryNotificationHistoryStore.php',
    'NotificationPolicy.php',
    'NotificationEngine.php',
    'NotificationGenerator.php',
    'NotificationScheduler.php',
    'SubscriptionNotificationJob.php',
] as $file) {
    require_once $mod . '/' . $file;
}

use Rateb\App\Subscription\InMemoryNotificationHistoryStore;
use Rateb\App\Subscription\InMemorySubscriptionEngineStore;
use Rateb\App\Subscription\NotificationEngine;
use Rateb\App\Subscription\NotificationGenerator;
use Rateb\App\Subscription\NotificationScheduler;
use Rateb\App\Subscription\NotificationType;
use Rateb\App\Subscription\SubscriptionEngine;
use Rateb\App\Subscription\SubscriptionNotificationJob;
use Rateb\App\Subscription\SubscriptionStatus;

$failed = 0;
function expect(bool $cond, string $msg): void
{
    global $failed;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failed++;
        return;
    }
    echo "OK: {$msg}\n";
}

$today = '2026-07-23';
// end = today + 14 days → trigger day 14
$rows = [
    [
        'id' => 1,
        'company_id' => 10,
        'subscription_start' => '2026-01-01',
        'subscription_end' => '2026-08-06',
        'grace_period_days' => 7,
        'current_status' => SubscriptionStatus::WARNING,
        'suspended_at' => null,
    ],
    [
        'id' => 2,
        'company_id' => 20,
        'subscription_start' => '2026-01-01',
        'subscription_end' => '2026-08-01', // 9 days remaining — not a trigger
        'grace_period_days' => 7,
        'current_status' => SubscriptionStatus::ACTIVE,
        'suspended_at' => null,
    ],
    [
        'id' => 3,
        'company_id' => 30,
        'subscription_start' => '2026-01-01',
        'subscription_end' => '2026-07-23', // day 0 FINAL_WARNING
        'grace_period_days' => 7,
        'current_status' => SubscriptionStatus::CRITICAL,
        'suspended_at' => null,
    ],
];

$store = new InMemorySubscriptionEngineStore($rows);
$history = new InMemoryNotificationHistoryStore();
$subEngine = new SubscriptionEngine($store);
$notifEngine = new NotificationEngine(null, $history);
$generator = new NotificationGenerator($history);
$scheduler = new NotificationScheduler($store, $subEngine, $notifEngine, $generator);

$dry = $scheduler->run(['today' => $today, 'dry_run' => true, 'batch_size' => 2]);
expect($dry['scanned'] === 3, 'dry scanned 3');
expect($dry['batches'] === 2, 'dry used 2 batches (size 2)');
expect($dry['eligible'] === 2, 'dry eligible = company 10 + 30');
expect($dry['inserted'] === 0, 'dry inserts nothing');
expect($history->listByCompanyId(10) === [], 'dry no history yet');

$run1 = $scheduler->run(['today' => $today, 'batch_size' => 100]);
expect($run1['inserted'] === 2, 'first run inserts 2');
expect($run1['eligible'] === 2, 'first run eligible 2');
$last10 = $history->findLastByCompanyId(10);
expect(is_array($last10) && $last10['notification_type'] === NotificationType::REMINDER, 'company 10 REMINDER');
$last30 = $history->findLastByCompanyId(30);
expect(is_array($last30) && $last30['notification_type'] === NotificationType::FINAL_WARNING, 'company 30 FINAL_WARNING');

$run2 = (new SubscriptionNotificationJob($scheduler))->execute([
    'today' => $today,
    'batch_size' => 100,
]);
expect($run2['inserted'] === 0, 'second run inserts 0 (dedupe)');
expect($run2['eligible'] === 0, 'second run not eligible (duplicates)');
expect($run2['declined'] === 3, 'second run all declined');

$gen = new NotificationGenerator($history);
$statsMany = $gen->generateMany([]);
expect($statsMany['attempted'] === 0, 'generateMany empty');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll NotificationScheduler Phase 4 checks passed.\n";
exit(0);
