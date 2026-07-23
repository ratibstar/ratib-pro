<?php
declare(strict_types=1);

/**
 * Phase 6 — GracePeriodEngine lifecycle calculation tests.
 * Run: php rateb-erp/modules/subscription/tests/GracePeriodPhase6Test.php
 */

$mod = dirname(__DIR__);
foreach ([
    'SubscriptionStatus.php',
    'GracePeriodStatus.php',
    'GracePeriodPolicy.php',
    'GracePeriodEngine.php',
    'SubscriptionContext.php',
] as $file) {
    require_once $mod . '/' . $file;
}

use Rateb\App\Subscription\GracePeriodEngine;
use Rateb\App\Subscription\GracePeriodPolicy;
use Rateb\App\Subscription\GracePeriodStatus;
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

$engine = new GracePeriodEngine(new GracePeriodPolicy(7));
$end = '2026-08-01';

expect($engine->calculateGraceStart($end, 7) === '2026-08-02', 'grace start Aug 2');
expect($engine->calculateGraceEnd($end, 7) === '2026-08-08', 'grace end Aug 8');
expect($engine->isInGracePeriod($end, '2026-08-02', 7) === true, 'Aug 2 in grace');
expect($engine->isInGracePeriod($end, '2026-08-08', 7) === true, 'Aug 8 in grace');
expect($engine->isInGracePeriod($end, '2026-08-01', 7) === false, 'Aug 1 not in grace');
expect($engine->isInGracePeriod($end, '2026-08-09', 7) === false, 'Aug 9 not in grace');
expect($engine->hasGraceExpired($end, '2026-08-09', 7) === true, 'Aug 9 grace expired');
expect($engine->hasGraceExpired($end, '2026-08-08', 7) === false, 'Aug 8 grace not expired');
expect($engine->daysRemaining($end, '2026-08-02', 7) === 6, 'Aug 2 → 6 days to grace end');
expect($engine->daysRemaining($end, '2026-08-08', 7) === 0, 'Aug 8 → 0 days remaining');

expect(
    $engine->resolveLifecycleStatus($end, '2026-07-20', 7, SubscriptionStatus::WARNING) === SubscriptionStatus::WARNING,
    'before expiry keeps WARNING'
);
expect(
    $engine->resolveLifecycleStatus($end, '2026-08-03', 7, SubscriptionStatus::CRITICAL) === GracePeriodStatus::GRACE,
    'during grace → GRACE'
);
expect(
    $engine->resolveLifecycleStatus($end, '2026-08-09', 7, SubscriptionStatus::ACTIVE) === GracePeriodStatus::SUSPENSION_PENDING,
    'after grace → SUSPENSION_PENDING'
);

// Context integration — default grace 7 when row has 0
$ctx = SubscriptionContext::fromEngineRow(1, [
    'id' => 1,
    'current_status' => 'ACTIVE',
    'subscription_end' => '2026-08-01',
    'grace_period_days' => 0,
    'suspended_at' => null,
], '2026-08-02');
expect($ctx->isInGrace() === true, 'context in grace');
expect($ctx->status() === SubscriptionStatus::GRACE, 'context status GRACE');
expect($ctx->graceStartedAt() === '2026-08-02', 'context grace start');
expect($ctx->graceEndDate() === '2026-08-08', 'context grace end');
expect($ctx->graceDaysRemaining() === 6, 'context grace days remaining 6');
expect($ctx->canAccessERP() === true, 'grace still accessible');
expect($ctx->gracePeriodDays() === 7, 'default policy 7');

$pending = SubscriptionContext::fromEngineRow(2, [
    'id' => 2,
    'current_status' => 'CRITICAL',
    'subscription_end' => '2026-08-01',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-08-09');
expect($pending->isInGrace() === false, 'pending not in grace');
expect($pending->status() === SubscriptionStatus::SUSPENSION_PENDING, 'pending status');
expect($pending->canAccessERP() === true, 'pending still accessible (no enforcement)');
expect($pending->isSuspensionPending() === true, 'isSuspensionPending');

$active = SubscriptionContext::fromEngineRow(3, [
    'id' => 3,
    'current_status' => 'WARNING',
    'subscription_end' => '2026-08-20',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-08-02');
expect($active->isInGrace() === false, 'before expiry not grace');
expect($active->status() === SubscriptionStatus::WARNING, 'keeps WARNING');
expect($active->graceDaysRemaining() === 0, 'no grace days before expiry');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll GracePeriod Phase 6 checks passed.\n";
exit(0);
