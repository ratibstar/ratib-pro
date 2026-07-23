<?php
declare(strict_types=1);

/**
 * Phase 8 — RenewalEngine / reactivation tests.
 * Run: php rateb-erp/modules/subscription/tests/RenewalPhase8Test.php
 */

$mod = dirname(__DIR__);
foreach ([
    'SubscriptionStatus.php',
    'GracePeriodStatus.php',
    'GracePeriodPolicy.php',
    'GracePeriodEngine.php',
    'SubscriptionContext.php',
    'SubscriptionEngineStore.php',
    'InMemorySubscriptionEngineStore.php',
    'RenewalStore.php',
    'RenewalRequest.php',
    'RenewalResult.php',
    'RenewalAuthorizer.php',
    'AllowAllRenewalAuthorizer.php',
    'InMemoryRenewalRepository.php',
    'RenewalEngine.php',
] as $file) {
    require_once $mod . '/' . $file;
}

use Rateb\App\Subscription\AllowAllRenewalAuthorizer;
use Rateb\App\Subscription\InMemoryRenewalRepository;
use Rateb\App\Subscription\InMemorySubscriptionEngineStore;
use Rateb\App\Subscription\RenewalEngine;
use Rateb\App\Subscription\RenewalRequest;
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

$today = '2026-08-10';

// --- 5. Expiry calculation (pure) ---
$calcEngine = new RenewalEngine(
    new InMemorySubscriptionEngineStore(),
    new InMemoryRenewalRepository(new InMemorySubscriptionEngineStore()),
    new AllowAllRenewalAuthorizer()
);
expect($calcEngine->calculateNewPeriod('2026-08-01', '30d', $today) === '2026-09-09', '5 calc from today when expired (30d)');
expect($calcEngine->calculateNewPeriod('2026-09-01', '30d', $today) === '2026-10-01', '5 calc extend future end +30d');
expect($calcEngine->calculateNewPeriod('2026-08-15', '1m', $today) === '2026-09-15', '5 calc +1m');
expect($calcEngine->calculateNewPeriod('2026-08-15', '1y', $today) === '2027-08-15', '5 calc +1y');
expect($calcEngine->calculateNewPeriod('2026-08-15', '90', $today) === '2026-11-13', '5 calc plain days');
expect($calcEngine->calculateNewPeriod('2026-08-15', 'bad', $today) === null, '5 invalid period → null');

// --- Shared harness factory ---
$make = static function (array $rows) use ($today): array {
    $store = new InMemorySubscriptionEngineStore($rows);
    $renewals = new InMemoryRenewalRepository($store);
    $engine = new RenewalEngine($store, $renewals, new AllowAllRenewalAuthorizer());
    return [$engine, $store, $renewals];
};

// --- 1. Active renewal → extended expiry ---
[$engine1, $store1, $hist1] = $make([[
    'id' => 1,
    'company_id' => 10,
    'subscription_end' => '2026-09-01',
    'current_status' => SubscriptionStatus::ACTIVE,
    'suspended_at' => null,
    'grace_started_at' => null,
    'grace_end_at' => null,
    'grace_period_days' => 7,
]]);
$r1 = $engine1->renew(new RenewalRequest(10, '2026-12-01', '90d', 99, 'REF-ACTIVE'));
expect($r1->success() === true, '1 renew success');
expect($r1->newExpiryDate() === '2026-12-01', '1 new expiry');
expect($r1->previousExpiryDate() === '2026-09-01', '1 previous expiry');
expect($r1->newStatus() === SubscriptionStatus::ACTIVE, '1 new status ACTIVE');
$row1 = $store1->findByCompanyId(10);
expect(($row1['subscription_end'] ?? '') === '2026-12-01', '1 store expiry extended');
expect(($row1['current_status'] ?? '') === SubscriptionStatus::ACTIVE, '1 store ACTIVE');
expect(count($hist1->history()) === 1, '1 history row');
expect(($hist1->audits()[0]['action'] ?? '') === 'RENEWED', '1 audit RENEWED');

// --- 2. Grace renewal → ACTIVE ---
[$engine2, $store2] = $make([[
    'id' => 2,
    'company_id' => 20,
    'subscription_end' => '2026-08-01',
    'current_status' => SubscriptionStatus::GRACE,
    'suspended_at' => null,
    'grace_started_at' => '2026-08-02',
    'grace_end_at' => '2026-08-08',
    'grace_period_days' => 7,
]]);
$beforeGrace = SubscriptionContext::fromEngineRow(20, $store2->findByCompanyId(20), $today);
expect($beforeGrace->isInGrace() === false || $beforeGrace->status() === SubscriptionStatus::SUSPENSION_PENDING
    || $beforeGrace->isExpired(), '2 pre-state expired/grace-related');
$r2 = $engine2->renew(new RenewalRequest(20, '2026-11-01', '90d', 99, null));
expect($r2->success() === true, '2 renew success');
expect($r2->oldStatus() === SubscriptionStatus::GRACE, '2 old GRACE');
expect($r2->newStatus() === SubscriptionStatus::ACTIVE, '2 new ACTIVE');
$row2 = $store2->findByCompanyId(20);
$ctx2 = SubscriptionContext::fromEngineRow(20, $row2, $today);
expect($ctx2->status() === SubscriptionStatus::ACTIVE, '2 context ACTIVE');
expect($ctx2->isInGrace() === false, '2 grace reset (not in grace)');
expect($ctx2->isSuspended() === false, '2 not suspended');
expect(($row2['grace_started_at'] ?? null) === null, '2 grace_started cleared');
expect(($row2['grace_end_at'] ?? null) === null, '2 grace_end cleared');

// --- 3. Suspended renewal → immediate restore ---
[$engine3, $store3] = $make([[
    'id' => 3,
    'company_id' => 30,
    'subscription_end' => '2026-07-01',
    'current_status' => SubscriptionStatus::SUSPENDED,
    'suspended_at' => '2026-07-10 12:00:00',
    'grace_started_at' => '2026-07-02',
    'grace_end_at' => '2026-07-08',
    'grace_period_days' => 7,
]]);
$r3 = $engine3->renew(new RenewalRequest(30, '2027-01-01', '12m', 99, 'RESTORE'));
expect($r3->success() === true, '3 renew success');
expect($r3->oldStatus() === SubscriptionStatus::SUSPENDED, '3 old SUSPENDED');
$row3 = $store3->findByCompanyId(30);
expect(array_key_exists('suspended_at', $row3) && $row3['suspended_at'] === null, '3 suspended_at cleared');
expect(($row3['current_status'] ?? '') === SubscriptionStatus::ACTIVE, '3 status ACTIVE');
$ctx3 = SubscriptionContext::fromEngineRow(30, $row3, $today);
expect($ctx3->status() === SubscriptionStatus::ACTIVE, '3 context ACTIVE');
expect($ctx3->isSuspended() === false, '3 suspension disabled');
expect($ctx3->canAccessERP() === true, '3 ERP accessible immediately');
expect($ctx3->expirationDate() === '2027-01-01', '3 new expiry on context');

// --- 4. Invalid company → reject ---
[$engine4] = $make([[
    'id' => 4,
    'company_id' => 40,
    'subscription_end' => '2026-09-01',
    'current_status' => SubscriptionStatus::ACTIVE,
]]);
$r4a = $engine4->renew(new RenewalRequest(0, '2026-12-01', '30d', 99, null));
expect($r4a->success() === false, '4a company 0 rejected');
expect($r4a->code() === 'invalid_company', '4a code invalid_company');
$r4b = $engine4->renew(new RenewalRequest(9999, '2026-12-01', '30d', 99, null));
expect($r4b->success() === false, '4b missing company rejected');
expect($r4b->code() === 'invalid_company', '4b code invalid_company');

// Unauthorized actor
$deny = new class implements \Rateb\App\Subscription\RenewalAuthorizer {
    public function canRenew(int $actorId): bool
    {
        return false;
    }
};
$storeU = new InMemorySubscriptionEngineStore([[
    'id' => 5,
    'company_id' => 50,
    'subscription_end' => '2026-09-01',
    'current_status' => SubscriptionStatus::ACTIVE,
]]);
$engineU = new RenewalEngine($storeU, new InMemoryRenewalRepository($storeU), $deny);
$ru = $engineU->renew(new RenewalRequest(50, '2026-12-01', '30d', 1, null));
expect($ru->success() === false && $ru->code() === 'unauthorized', '4c unauthorized rejected');

// Auto-calc expiry when empty new_expiry_date
[$engine5, $store5] = $make([[
    'id' => 6,
    'company_id' => 60,
    'subscription_end' => '2026-09-01',
    'current_status' => SubscriptionStatus::WARNING,
]]);
$r5 = $engine5->renew(new RenewalRequest(60, '', '30d', 99, null));
expect($r5->success() === true, '5b renew with calculated expiry');
expect($r5->newExpiryDate() === '2026-10-01', '5b calculated 30d from Sep 1');
expect(($store5->findByCompanyId(60)['subscription_end'] ?? '') === '2026-10-01', '5b store updated');

if ($failed > 0) {
    echo "\n{$failed} failure(s)\n";
    exit(1);
}
echo "\nAll Renewal Phase 8 checks passed.\n";
exit(0);
