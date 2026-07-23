<?php
declare(strict_types=1);

/**
 * Phase 5 — SubscriptionAlertService (history + context only; no Engine/Policy/Repo).
 * Run: php rateb-erp/modules/subscription/tests/SubscriptionAlertPhase5Test.php
 */

$mod = dirname(__DIR__);
$root = dirname($mod, 2);
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', $root);
}

foreach ([
    'SubscriptionStatus.php',
    'GracePeriodStatus.php',
    'GracePeriodPolicy.php',
    'GracePeriodEngine.php',
    'SubscriptionContext.php',
    'SubscriptionRuntime.php',
    'SubscriptionAlertRuntime.php',
    'SubscriptionAlertViewModel.php',
    'NotificationType.php',
    'NotificationChannel.php',
    'NotificationDecision.php',
    'NotificationHistoryStore.php',
    'NotificationHistoryRepository.php',
    'InMemoryNotificationHistoryStore.php',
    'SubscriptionAlertService.php',
] as $file) {
    require_once $mod . '/' . $file;
}

use Rateb\App\Subscription\InMemoryNotificationHistoryStore;
use Rateb\App\Subscription\NotificationType;
use Rateb\App\Subscription\SubscriptionAlertRuntime;
use Rateb\App\Subscription\SubscriptionAlertService;
use Rateb\App\Subscription\SubscriptionAlertViewModel;
use Rateb\App\Subscription\SubscriptionContext;
use Rateb\App\Subscription\SubscriptionRuntime;
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

SubscriptionRuntime::reset();
SubscriptionAlertRuntime::reset();

$history = new InMemoryNotificationHistoryStore();
$svc = new SubscriptionAlertService($history);

expect($svc->current() === null, 'no context → no alert');

$ctx = new SubscriptionContext(
    55,
    SubscriptionStatus::WARNING,
    14,
    false,
    false,
    false,
    true,
    '2026-08-06',
    true,
    9,
    7
);
SubscriptionRuntime::bind($ctx);

SubscriptionAlertRuntime::reset();
$fallback = $svc->current();
expect($fallback !== null, 'no history but 14 days → context fallback alert');
expect($fallback->notificationType() === NotificationType::REMINDER, 'fallback REMINDER');
expect(str_contains($fallback->message(), '14 days'), 'fallback message 14 days');
expect($fallback->historyId() === 0, 'fallback history id 0');
SubscriptionAlertRuntime::reset();

// Seed history via recordGenerated path fields
$history->recordGenerated(
    \Rateb\App\Subscription\NotificationDecision::eligible(
        55,
        9,
        NotificationType::REMINDER,
        14,
        '2026-07-23',
        'test',
        ['email']
    )
);

SubscriptionAlertRuntime::reset();
$alert = $svc->current();
expect($alert !== null, 'reminder alert present');
expect($alert instanceof SubscriptionAlertViewModel, 'view model type');
expect($alert->severity() === SubscriptionAlertViewModel::SEVERITY_NORMAL, 'REMINDER severity normal');
expect(str_contains($alert->message(), '14 days'), 'message has 14 days');
expect($alert->isDismissible() === true, '14 days dismissible');
expect($alert->expirationDate() === '2026-08-06', 'expiry from context');
expect($alert->subscriptionStatus() === SubscriptionStatus::WARNING, 'status from context');
expect($alert->historyId() > 0, 'history-backed alert has id');

// Request cache
$again = $svc->current();
expect($again === $alert, 'request cache same instance');

// Persistent at 3 days
SubscriptionAlertRuntime::reset();
SubscriptionRuntime::bind(new SubscriptionContext(
    55, SubscriptionStatus::CRITICAL, 3, false, false, false, true, '2026-07-26', true, 9, 7
));
// Need history for company 55 still — same row works
$alert3 = $svc->current();
expect($alert3 !== null, '3-day alert present');
expect($alert3->isDismissible() === false, '3 days not dismissible');
expect(str_contains($alert3->message(), '3 days'), 'message 3 days');

// Expires today via context fallback (no history for company 99)
SubscriptionAlertRuntime::reset();
$emptyHistory = new InMemoryNotificationHistoryStore();
$svcToday = new SubscriptionAlertService($emptyHistory);
SubscriptionRuntime::bind(new SubscriptionContext(
    99, SubscriptionStatus::CRITICAL, 0, false, false, false, true, '2026-07-23', true, 1, 7
));
$todayAlert = $svcToday->current();
expect($todayAlert !== null, 'expires today fallback');
expect(str_contains($todayAlert->message(), 'today'), 'expires today message');
expect($todayAlert->notificationType() === NotificationType::FINAL_WARNING, 'today = FINAL_WARNING');

// Grace message
SubscriptionAlertRuntime::reset();
SubscriptionRuntime::bind(SubscriptionContext::fromEngineRow(55, [
    'id' => 9,
    'current_status' => SubscriptionStatus::ACTIVE,
    'subscription_end' => '2026-07-22',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-07-23'));
$history->recordGenerated(
    \Rateb\App\Subscription\NotificationDecision::eligible(
        55, 9, NotificationType::GRACE, -1, '2026-07-23', 'test', []
    )
);
SubscriptionAlertRuntime::reset();
$grace = $svc->current();
expect($grace !== null, 'grace alert');
expect($grace->severity() === SubscriptionAlertViewModel::SEVERITY_CRITICAL_WARNING, 'grace severity');
expect($grace->isDismissible() === false, 'grace not dismissible');
expect(str_contains($grace->message(), 'days remaining in grace period'), 'grace message');
expect(str_contains($grace->message(), '6 days remaining'), 'grace remaining 6');

// Far future → skip history query path (no alert even with history for other triggers)
SubscriptionAlertRuntime::reset();
SubscriptionRuntime::bind(new SubscriptionContext(
    55, SubscriptionStatus::ACTIVE, 40, false, false, false, true, '2026-09-01', true, 9, 7
));
expect($svc->current() === null, 'far from expiry skips alert');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll SubscriptionAlert Phase 5 checks passed.\n";
exit(0);
