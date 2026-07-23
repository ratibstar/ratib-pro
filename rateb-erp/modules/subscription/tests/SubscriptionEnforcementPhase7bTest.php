<?php
declare(strict_types=1);

/**
 * Phase 7B — SubscriptionEnforcementGate (feature flag) tests.
 * Run: php rateb-erp/modules/subscription/tests/SubscriptionEnforcementPhase7bTest.php
 */

$mod = dirname(__DIR__);
foreach ([
    'SubscriptionStatus.php',
    'GracePeriodStatus.php',
    'GracePeriodPolicy.php',
    'GracePeriodEngine.php',
    'SubscriptionContext.php',
    'SuspensionDecision.php',
    'SuspensionPolicy.php',
    'SuspensionAuditRepository.php',
    'SuspensionEngine.php',
    'SubscriptionAccessDecision.php',
    'SubscriptionEnforcementGate.php',
] as $file) {
    require_once $mod . '/' . $file;
}

use Rateb\App\Subscription\SubscriptionAccessDecision;
use Rateb\App\Subscription\SubscriptionContext;
use Rateb\App\Subscription\SubscriptionEnforcementGate;
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

$gate = new SubscriptionEnforcementGate();

$active = SubscriptionContext::fromEngineRow(1, [
    'id' => 1,
    'current_status' => 'ACTIVE',
    'subscription_end' => '2026-09-01',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-08-02');

$grace = SubscriptionContext::fromEngineRow(2, [
    'id' => 2,
    'current_status' => 'ACTIVE',
    'subscription_end' => '2026-08-01',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-08-05');

$suspendedEligible = SubscriptionContext::fromEngineRow(3, [
    'id' => 3,
    'current_status' => 'CRITICAL',
    'subscription_end' => '2026-08-01',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-08-09');

$hardSuspended = SubscriptionContext::fromEngineRow(4, [
    'id' => 4,
    'current_status' => SubscriptionStatus::SUSPENDED,
    'subscription_end' => '2026-07-01',
    'grace_period_days' => 7,
    'suspended_at' => '2026-07-10 00:00:00',
], '2026-08-09');

// Ensure flag is off for scenario 1
if (defined('SUBSCRIPTION_ENFORCEMENT_ENABLED')) {
    echo "SKIP redefine: constant already defined\n";
}

// 1. Flag OFF — always allow
expect(SubscriptionEnforcementGate::isEnabled() === false, '1 flag default off');
$d1 = $gate->decide($suspendedEligible, '/admin/inventory');
expect($d1->allowed() === true, '1 flag off allows suspended-eligible');
expect($d1->reason() === 'enforcement_flag_off', '1 reason flag_off');

// Simulate flag ON via putenv for remaining tests
putenv('SUBSCRIPTION_ENFORCEMENT_ENABLED=1');
$_ENV['SUBSCRIPTION_ENFORCEMENT_ENABLED'] = '1';
expect(SubscriptionEnforcementGate::isEnabled() === true, 'flag on via env');

// 2. Flag ON + Active
$d2 = $gate->decide($active, '/admin/inventory');
expect($d2->allowed() === true, '2 active allowed');
expect($d2->reason() === 'subscription_access_ok', '2 reason ok');

// 3. Flag ON + Grace
$d3 = $gate->decide($grace, '/admin/inventory');
expect($grace->isInGrace() === true, '3 in grace');
expect($d3->allowed() === true, '3 grace allowed');

// 4. Flag ON + Suspended eligible → DENY
$d4 = $gate->decide($suspendedEligible, '/admin/inventory', '2026-08-09');
expect($d4->denied() === true, '4 deny inventory');
expect($d4->decision() === SubscriptionAccessDecision::DENY, '4 DENY');
expect($d4->redirectPath() === 'subscription/renew', '4 redirect renew');
expect($d4->reason() === 'grace_expired_suspension_eligible', '4 reason');

$d4b = $gate->decide($hardSuspended, '/admin/hr', '2026-08-09');
expect($d4b->denied() === true, '4b hard suspended denied');

// 5. Renewal URL allow-listed
$d5 = $gate->decide($suspendedEligible, '/admin/subscription/renew', '2026-08-09');
expect($d5->allowed() === true, '5 renew allowed');
expect($d5->reason() === 'allow_listed_path', '5 allow list');

$d5b = $gate->decide($suspendedEligible, '/admin/subscription/invoices', '2026-08-09');
expect($d5b->allowed() === true, '5b invoices allowed');

$d5c = $gate->decide($suspendedEligible, '/admin/logout', '2026-08-09');
expect($d5c->allowed() === true, '5c logout allowed');

// Rollback env
putenv('SUBSCRIPTION_ENFORCEMENT_ENABLED');
unset($_ENV['SUBSCRIPTION_ENFORCEMENT_ENABLED']);
expect(SubscriptionEnforcementGate::isEnabled() === false, 'rollback flag off');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll Subscription Enforcement Phase 7B checks passed.\n";
exit(0);
