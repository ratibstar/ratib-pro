<?php
declare(strict_types=1);

/**
 * Phase 2 — SubscriptionContext unit checks (no DB).
 * Run: php rateb-erp/modules/subscription/tests/SubscriptionContextPhase2Test.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/modules/subscription/SubscriptionStatus.php';
require_once $root . '/modules/subscription/GracePeriodStatus.php';
require_once $root . '/modules/subscription/GracePeriodPolicy.php';
require_once $root . '/modules/subscription/GracePeriodEngine.php';
require_once $root . '/modules/subscription/SubscriptionContext.php';

use Rateb\App\Subscription\SubscriptionContext;
use Rateb\App\Subscription\SubscriptionStatus;

$failed = 0;

function expect(bool $cond, string $msg) : void
{
    global $failed;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failed++;
        return;
    }
    echo "OK: {$msg}\n";
}

$absent = SubscriptionContext::absent(42);
expect($absent->companyId() === 42, 'absent companyId');
expect($absent->status() === SubscriptionStatus::ACTIVE, 'absent status ACTIVE');
expect($absent->daysRemaining() === 0, 'absent daysRemaining 0');
expect($absent->isExpired() === false, 'absent not expired');
expect($absent->isInGrace() === false, 'absent not grace');
expect($absent->isSuspended() === false, 'absent not suspended');
expect($absent->canAccessERP() === true, 'absent canAccessERP true');
expect($absent->expirationDate() === null, 'absent no expiration');
expect($absent->hasRecord() === false, 'absent hasRecord false');

$row = [
    'current_status' => 'WARNING',
    'subscription_end' => '2026-07-30',
    'suspended_at' => null,
];
$ctx = SubscriptionContext::fromEngineRow(7, $row, '2026-07-23');
expect($ctx->companyId() === 7, 'row companyId');
expect($ctx->status() === SubscriptionStatus::WARNING, 'row status WARNING');
expect($ctx->daysRemaining() === 7, 'row daysRemaining 7');
expect($ctx->isExpired() === false, 'row not expired');
expect($ctx->isInGrace() === false, 'row not grace');
expect($ctx->isSuspended() === false, 'row not suspended');
expect($ctx->canAccessERP() === true, 'row canAccessERP');
expect($ctx->expirationDate() === '2026-07-30', 'row expiration');
expect($ctx->hasRecord() === true, 'row hasRecord');

$suspended = SubscriptionContext::fromEngineRow(1, [
    'current_status' => 'SUSPENDED',
    'subscription_end' => '2026-01-01',
    'suspended_at' => '2026-01-02 00:00:00',
], '2026-07-23');
expect($suspended->isSuspended() === true, 'suspended flag');
expect($suspended->isExpired() === true, 'suspended expired by date');
expect($suspended->canAccessERP() === false, 'suspended advisory canAccessERP false');
expect($suspended->isInGrace() === false, 'suspended not grace');

$grace = SubscriptionContext::fromEngineRow(2, [
    'current_status' => 'ACTIVE',
    'subscription_end' => '2026-07-20',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-07-23');
expect($grace->isInGrace() === true, 'grace status');
expect($grace->canAccessERP() === true, 'grace still advisory accessible');
expect($grace->graceDaysRemaining() === 4, 'grace days remaining to Jul 27');
expect($grace->graceEndDate() === '2026-07-27', 'grace end date');

// Immutability: readonly class — no setters exist.
expect(!method_exists($ctx, 'setStatus'), 'no setStatus');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll SubscriptionContext Phase 2 checks passed.\n";
exit(0);
