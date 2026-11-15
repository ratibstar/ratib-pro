<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/payment/commerce.php';

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

$assert(rateb_refund_credits_for_package('starter') === 500, 'starter refund credits');
$assert(rateb_refund_credits_for_package('pro') === 2500, 'pro refund credits');
$assert(rateb_refund_credits_for_package('free') === 0, 'free refund credits');

exit($fail > 0 ? 1 : 0);