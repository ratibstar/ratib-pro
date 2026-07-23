<?php
declare(strict_types=1);

/**
 * Phase 7A — SuspensionEngine shadow-mode scenarios.
 * Run: php rateb-erp/modules/subscription/tests/SuspensionShadowPhase7aTest.php
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
    'InMemorySuspensionAuditRepository.php',
    'SuspensionEngine.php',
] as $file) {
    require_once $mod . '/' . $file;
}

use Rateb\App\Subscription\InMemorySuspensionAuditRepository;
use Rateb\App\Subscription\SubscriptionContext;
use Rateb\App\Subscription\SubscriptionStatus;
use Rateb\App\Subscription\SuspensionEngine;

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

$audit = new InMemorySuspensionAuditRepository();
$engine = new SuspensionEngine(null, $audit, true);

// 1. Active subscription → not eligible
$active = SubscriptionContext::fromEngineRow(1, [
    'id' => 1,
    'current_status' => 'WARNING',
    'subscription_end' => '2026-08-20',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-08-02');
$d1 = $engine->evaluate($active, '2026-08-02');
expect($d1->isEligible() === false, '1 active not eligible');
expect($d1->reason() === 'subscription_active', '1 reason active');
expect($engine->shouldSuspend($active, '2026-08-02') === false, '1 shouldSuspend false');
expect($engine->suspensionDate($active) === '2026-08-28', '1 suspension date after grace');

// 2. Expired but grace active → not eligible
$grace = SubscriptionContext::fromEngineRow(2, [
    'id' => 2,
    'current_status' => 'ACTIVE',
    'subscription_end' => '2026-08-01',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-08-05');
$d2 = $engine->evaluate($grace, '2026-08-05');
expect($grace->isInGrace() === true, '2 in grace');
expect($d2->isEligible() === false, '2 grace not eligible');
expect($d2->reason() === 'grace_period_active', '2 reason grace');
expect($engine->suspensionDate($grace) === '2026-08-09', '2 eligible from Aug 9');

// 3. Grace expired → eligible
$expired = SubscriptionContext::fromEngineRow(3, [
    'id' => 3,
    'current_status' => 'CRITICAL',
    'subscription_end' => '2026-08-01',
    'grace_period_days' => 7,
    'suspended_at' => null,
], '2026-08-09');
$d3 = $engine->evaluate($expired, '2026-08-09');
expect($d3->isEligible() === true, '3 grace expired eligible');
expect($d3->reason() === 'grace_period_expired', '3 reason expired');
expect($d3->effectiveDate() === '2026-08-09', '3 effective Aug 9');
expect($d3->currentStatus() === SubscriptionStatus::SUSPENSION_PENDING, '3 status pending');
expect(count($audit->all()) === 1, '3 audit wrote eligible once');
expect($audit->all()[0]['decision'] === 'eligible', '3 audit decision');
expect($engine->lastDecision()?->isEligible() === true, '3 last decision eligible');
expect($engine->reason() === 'grace_period_expired', '3 last reason');
// Fresh engine avoids double-audit from shouldSuspend re-evaluate
$engine2 = new SuspensionEngine();
expect($engine2->shouldSuspend($expired, '2026-08-09') === true, '3 shouldSuspend true');

// 4. Missing subscription data → not eligible
$missing = SubscriptionContext::absent(0);
$d4 = $engine->evaluate($missing, '2026-08-09');
expect($d4->isEligible() === false, '4 missing not eligible');
expect($d4->reason() === 'missing_subscription_data', '4 reason missing');

$missingCompany = SubscriptionContext::absent(99);
$d4b = $engine->evaluate($missingCompany, '2026-08-09');
expect($d4b->isEligible() === false, '4b absent record not eligible');
expect($d4b->reason() === 'missing_subscription_data', '4b reason');

// Shadow: eligible does not flip canAccessERP
expect($expired->canAccessERP() === true, 'shadow still accessible');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll Suspension Shadow Phase 7A checks passed.\n";
exit(0);
