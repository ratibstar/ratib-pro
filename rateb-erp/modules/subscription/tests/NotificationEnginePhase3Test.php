<?php
declare(strict_types=1);

/**
 * Phase 3 — NotificationEngine eligibility checks (no DB, no senders).
 * Run: php rateb-erp/modules/subscription/tests/NotificationEnginePhase3Test.php
 */

$root = dirname(__DIR__, 3);
foreach ([
    'SubscriptionStatus.php',
    'SubscriptionModule.php',
    'SubscriptionContext.php',
    'NotificationType.php',
    'NotificationChannel.php',
    'NotificationDecision.php',
    'NotificationHistoryStore.php',
    'NotificationHistoryRepository.php',
    'InMemoryNotificationHistoryStore.php',
    'NotificationPolicy.php',
    'NotificationEngine.php',
] as $file) {
    require_once dirname(__DIR__) . '/' . $file;
}

// Stub RATEB_ROOT for policy config path.
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', $root);
}

use Rateb\App\Subscription\InMemoryNotificationHistoryStore;
use Rateb\App\Subscription\NotificationEngine;
use Rateb\App\Subscription\NotificationPolicy;
use Rateb\App\Subscription\NotificationType;
use Rateb\App\Subscription\SubscriptionContext;
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

$policy = new NotificationPolicy();
expect(in_array(14, $policy->triggerDays(), true), 'default includes 14');
expect(in_array(-7, $policy->triggerDays(), true), 'default includes +7 as -7');
expect($policy->typeForTriggerDay(14) === NotificationType::REMINDER, '14 → REMINDER');
expect($policy->typeForTriggerDay(0) === NotificationType::FINAL_WARNING, '0 → FINAL_WARNING');
expect($policy->typeForTriggerDay(-3) === NotificationType::GRACE, '-3 → GRACE');
expect($policy->nextTriggerDayAfter(14) === 11, 'next after 14 is 11');

$custom = new NotificationPolicy([
    'trigger_days' => [10, 0],
    'type_by_trigger_day' => [10 => 'REMINDER', 0 => 'FINAL_WARNING'],
    'channels' => ['email'],
]);
expect($custom->isTriggerDay(10), 'custom trigger 10');
expect(!$custom->isTriggerDay(14), 'custom excludes 14');
expect($custom->channels() === ['email'], 'custom channels');

$store = new InMemoryNotificationHistoryStore();
$engine = new NotificationEngine($policy, $store);

$absent = SubscriptionContext::absent(9);
$d0 = $engine->evaluate($absent);
expect(!$d0->shouldGenerate(), 'absent declines');
expect($d0->reason() === 'no_subscription_record', 'absent reason');

$ctx14 = new SubscriptionContext(
    1,
    SubscriptionStatus::WARNING,
    14,
    false,
    false,
    false,
    true,
    '2026-08-06',
    true,
    100
);
$d14 = $engine->evaluate($ctx14, '2026-07-23');
expect($d14->shouldGenerate(), 'day 14 eligible');
expect($d14->notificationType() === NotificationType::REMINDER, 'day 14 type');
expect($d14->triggerDay() === 14, 'day 14 trigger');
expect($d14->subscriptionId() === 100, 'subscription id from context');
expect($engine->shouldGenerate($ctx14, '2026-07-23'), 'shouldGenerate true');

$id = $engine->recordGenerated($d14);
expect($id > 0, 'recordGenerated inserts');
$d14b = $engine->evaluate($ctx14, '2026-07-23');
expect(!$d14b->shouldGenerate(), 'duplicate declined');
expect($d14b->reason() === 'duplicate_trigger_already_recorded', 'duplicate reason');

$ctxOff = new SubscriptionContext(
    1, SubscriptionStatus::ACTIVE, 9, false, false, false, true, '2026-08-01', true, 100
);
expect(!$engine->shouldGenerate($ctxOff, '2026-07-23'), 'non-trigger day declines');

$ctx0 = new SubscriptionContext(
    2, SubscriptionStatus::CRITICAL, 0, false, false, false, true, '2026-07-23', true, 200
);
$dFinal = $engine->evaluate($ctx0, '2026-07-23');
expect($dFinal->shouldGenerate(), 'day 0 eligible');
expect($dFinal->notificationType() === NotificationType::FINAL_WARNING, 'day 0 FINAL_WARNING');

$ctxGrace = new SubscriptionContext(
    3, SubscriptionStatus::GRACE, -2, true, true, false, true, '2026-07-21', true, 300
);
$dGrace = $engine->evaluate($ctxGrace, '2026-07-23');
expect($dGrace->shouldGenerate(), 'grace -2 eligible');
expect($dGrace->notificationType() === NotificationType::GRACE, 'grace type');

$ctxSus = new SubscriptionContext(
    4, SubscriptionStatus::SUSPENDED, -10, true, false, true, false, '2026-07-01', true, 400
);
$dSus = $engine->evaluate($ctxSus, '2026-07-23');
expect($dSus->shouldGenerate(), 'suspension eligible once');
expect($dSus->notificationType() === NotificationType::SUSPENSION, 'suspension type');
$engine->recordGenerated($dSus);
expect(!$engine->shouldGenerate($ctxSus, '2026-07-23'), 'suspension not duplicated');

$next = $engine->nextNotificationDate($ctx14, '2026-07-23');
expect($next === '2026-07-26', 'next date for trigger 11 from end 2026-08-06');
// end 2026-08-06, next trigger after 14 is 11 → 2026-08-06 - 11 days = 2026-07-26

expect($engine->lastNotification(1) !== null, 'lastNotification after record');
expect(count($engine->history(1)) >= 1, 'history non-empty');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll NotificationEngine Phase 3 checks passed.\n";
exit(0);
