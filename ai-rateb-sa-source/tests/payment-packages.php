<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/plans.php';
require_once __DIR__ . '/support/fake-ledger.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

function paid(string $id, string $package, int $amount, string $currency = 'SAR', string $status = 'paid', int $userId = 7, int $orderId = 1): array
{
    return ['id' => $id, 'status' => $status, 'amount' => $amount, 'currency' => $currency, 'metadata' => ['user_id' => (string) $userId, 'package' => $package, 'order_id' => (string) $orderId]];
}

function seed_order(FakeLedger $pdo, int $id, string $package, int $amount, int $credits, int $userId = 7, string $status = 'pending'): void
{
    $pdo->orders[$id] = ['user_id' => $userId, 'package_code' => $package, 'amount' => $amount, 'credits' => $credits, 'currency' => 'SAR', 'status' => $status];
}

check(rateb_paid_package('starter')['amount'] === 9900 && rateb_paid_package('starter')['credits'] === 500, 'starter maps to 99 SAR and 500 credits');
check(rateb_paid_package('pro')['amount'] === 24900 && rateb_paid_package('pro')['credits'] === 2500, 'pro maps to 249 SAR and 2500 credits');
check(rateb_paid_package('growth') === null && rateb_paid_package('business') === null && rateb_paid_package('free') === null, 'unpublished packages stay blocked');

$pdo = new FakeLedger();
$pdo->balances[7] = 50;
$pdo->planIds = ['starter' => 2, 'pro' => 3];
$pdo->subscriptions[7] = 1;
seed_order($pdo, 1, 'starter', 9900, 500);
check(rateb_payment_record($pdo, paid('11111111-1111-1111-1111-111111111111', 'starter', 9900)) === 'ok', 'a verified starter payment is recorded');
check($pdo->balances[7] === 550 && $pdo->subscriptions[7] === 2, 'starter grants 500 credits and switches the subscription');
check(rateb_payment_record($pdo, paid('11111111-1111-1111-1111-111111111111', 'starter', 9900)) === 'duplicate' && $pdo->balances[7] === 550, 'the same payment cannot grant credits again');
seed_order($pdo, 2, 'pro', 24900, 2500);
check(rateb_payment_record($pdo, paid('22222222-2222-2222-2222-222222222222', 'pro', 24900, 'SAR', 'paid', 7, 2)) === 'ok' && $pdo->balances[7] === 3050, 'pro adds its own 2500 credits');
check(rateb_payment_record($pdo, paid('33333333-3333-3333-3333-333333333333', 'growth', 19900)) === 'package', 'growth is rejected');
check(rateb_payment_record($pdo, paid('44444444-4444-4444-4444-444444444444', 'starter', 7900)) === 'amount', 'a catalog price that is not the published charge is rejected');
check(rateb_payment_record($pdo, paid('55555555-5555-5555-5555-555555555555', 'starter', 9900, 'USD')) === 'currency', 'a non-SAR payment is rejected');
check(rateb_payment_record($pdo, paid('66666666-6666-6666-6666-666666666666', 'starter', 9900, 'SAR', 'authorized')) === 'unpaid', 'an unpaid status is rejected');
seed_order($pdo, 7, 'starter', 9900, 500, 99);
check(rateb_payment_record($pdo, paid('77777777-7777-7777-7777-777777777777', 'starter', 9900, 'SAR', 'paid', 99, 7)) === 'owner', 'a payment for a missing user is rejected');
seed_order($pdo, 8, 'starter', 100, 500);
check(rateb_payment_record($pdo, paid('88888888-8888-8888-8888-888888888888', 'starter', 9900, 'SAR', 'paid', 7, 8)) === 'amount', 'an order amount that differs from the package is rejected');
check(rateb_payment_record($pdo, paid('99999999-9999-9999-9999-999999999999', 'starter', 9900, 'SAR', 'paid', 7, 0)) === 'order', 'a payment without a pending order is rejected');
check(!rateb_moyasar_webhook_authorized('', 'secret') && !rateb_moyasar_webhook_authorized('secret', '') && rateb_moyasar_webhook_authorized('secret', 'secret') && !rateb_moyasar_webhook_authorized('secret', 'other'), 'a webhook token must match and cannot be empty');
seed_order($pdo, 9, 'starter', 9900, 500);
rateb_payment_mark_failed($pdo, 9, 7);
check($pdo->orders[9]['status'] === 'failed' && $pdo->balances[7] === 3050, 'a failed payment closes the order without credits');
check($pdo->balances[7] === 3050, 'rejected payments do not change the balance');

echo $failures === 0 ? "OK\n" : "FAILED {$failures}\n";
exit($failures === 0 ? 0 : 1);
