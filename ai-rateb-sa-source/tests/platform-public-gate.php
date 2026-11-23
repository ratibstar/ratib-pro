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
$assert(rateb_public_gate_is_always_exempt('/login'), 'login always exempt');
$assert(rateb_public_gate_is_always_exempt('/login.php'), 'login.php always exempt');
$assert(rateb_public_gate_is_always_exempt('/forgot-password'), 'forgot-password always exempt');
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
$assert(!rateb_public_gate_is_marketing_path('/login'), 'login not marketing');
$assert(!rateb_public_gate_is_marketing_path('/forgot-password'), 'forgot-password not marketing');
$assert(!rateb_public_gate_is_exempt_path('/'), 'home not legacy exempt');
$_SERVER['SCRIPT_NAME'] = '/login.php';
$_SERVER['SCRIPT_FILENAME'] = '/var/www/login.php';
$assert(rateb_public_gate_is_exempt_script(), 'login.php script is exempt');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = '/var/www/index.php';
$assert(!rateb_public_gate_is_exempt_script(), 'index.php script is not exempt');

exit($fail > 0 ? 1 : 0);