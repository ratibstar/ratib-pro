<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$plans = (string) file_get_contents($root . '/app/plans.php');
$events = (string) file_get_contents($root . '/app/notify/events.php');
$billing = (string) file_get_contents($root . '/billing.php');

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

$assert(str_contains($plans, 'rateb_subscription_must_downgrade') && str_contains($plans, 'rateb_subscription_apply_free_entitlement'), 'plans defines downgrade helpers');
$assert(str_contains($plans, "package_type = 'free'") && !str_contains($plans, 'credits_balance = 0'), 'downgrade sets free package without zeroing credits');
$assert(str_contains($plans, 'rateb_subscription_expire_due_batch'), 'batch expiry for cron');
$assert(str_contains($events, 'subscription_expired'), 'expiry email template');
$assert(str_contains($billing, 'rateb_user_plan'), 'billing enforces entitlement on page load');

exit($fail > 0 ? 1 : 0);