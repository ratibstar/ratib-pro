<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/plans.php';

$failures = 0;
$check = static function (bool $ok, string $name) use (&$failures): void {
    if ($ok) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}\n";
};

$root = dirname(__DIR__);
$checkout = (string) file_get_contents($root . '/app/payment/checkout.php');
$webhook = (string) file_get_contents($root . '/app/payment/webhook.php');
$sync = (string) file_get_contents($root . '/app/payment/moyasar-sync.php');
$return = (string) file_get_contents($root . '/app/payment/return.php');
$plansSrc = (string) file_get_contents($root . '/app/plans.php');
$schema = (string) file_get_contents($root . '/app/schema.php');

$check(str_contains($checkout, 'INSERT INTO payment_orders') && str_contains($checkout, 'rateb_payment_mark_failed'), 'checkout creates and can fail a payment order');
$check(str_contains($checkout, 'rateb_campaign_credit_bill') && str_contains($checkout, 'rateb_paid_package'), 'checkout keeps usage billing and starter/pro packages');
$check(str_contains($checkout, 'verify_csrf()'), 'checkout posts are CSRF protected');
$check(!str_contains($checkout, 'MOYASAR_WEBHOOK_TOKEN') && !str_contains($webhook, 'MOYASAR_WEBHOOK_TOKEN'), 'reconciled payment files do not read a new webhook token');
$check(str_contains($webhook, "(string) (\$payment['id'] ?? '') !== \$paymentId") && str_contains($webhook, 'rateb_payment_record'), 'webhook verifies the fetched payment id before recording');
foreach (['payment_orders', 'welcome_grants', 'signup_audit', 'idempotency_key', 'lock_token'] as $needle) {
    $check(str_contains($schema, $needle), "schema keeps {$needle}");
}
$check(!str_contains($schema, 'credits_balance = credits_balance +') && !str_contains($schema, 'test_credit_grant'), 'schema bootstrap does not grant credits');

$check(str_contains($checkout, 'rateb_campaign_credit_bill') && rateb_credit_halala(10) === 198, 'usage billing prices consumed credits from the published starter rate');
$check(str_contains($checkout, 'rateb_app_origin()') && str_contains($return, 'rateb_payment_sync_order'), 'return and checkout sync and use the live host');
$check(str_contains($sync, 'rateb_payment_sync_order') && str_contains($sync, 'rateb_payment_reconcile_pending'), 'moyasar sync exposes order sync and batch reconcile');
$check(str_contains($plansSrc, 'rateb_subscription_apply_paid_plan') && str_contains($plansSrc, "strtotime('+1 month', \$endTs)"), 'paid plans extend subscription from period_end');

echo $failures === 0 ? "ALL PASS 0\n" : "FAILURES {$failures}\n";
echo "SIMULATED ONLY. No MariaDB connection was opened.\n";
exit($failures === 0 ? 0 : 1);
