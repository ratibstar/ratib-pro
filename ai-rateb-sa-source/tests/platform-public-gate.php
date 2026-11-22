<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/public-gate.php';

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

$assert(rateb_public_gate_is_always_exempt('/admin/'), 'admin always exempt');
$assert(rateb_public_gate_is_always_exempt('/hr/employees.php'), 'hr workspace exempt');
$assert(rateb_public_gate_is_always_exempt('/dashboard'), 'dashboard exempt');
$assert(rateb_public_gate_is_always_exempt('/account'), 'account exempt');
$assert(!rateb_public_gate_is_always_exempt('/'), 'home not always exempt');
$assert(rateb_public_gate_is_marketing_path('/'), 'home is marketing');
$assert(rateb_public_gate_is_marketing_path('/register.php'), 'register is marketing');
$assert(rateb_public_gate_is_marketing_path('/contact'), 'contact is marketing');
$assert(rateb_public_gate_is_marketing_path('/privacy'), 'privacy is marketing');
$assert(rateb_public_gate_is_marketing_path('/terms'), 'terms is marketing');
$assert(!rateb_public_gate_is_marketing_path('/dashboard'), 'dashboard not marketing');
$assert(!rateb_public_gate_is_exempt_path('/'), 'home not legacy exempt');

exit($fail > 0 ? 1 : 0);