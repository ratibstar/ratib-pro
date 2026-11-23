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
$assert(rateb_public_gate_is_always_exempt('/login'), 'login soft-exempt');
$assert(rateb_public_gate_is_always_exempt('/dashboard'), 'dashboard soft-exempt');
$assert(rateb_public_gate_is_always_exempt('/account'), 'account soft-exempt');
$assert(rateb_public_gate_is_always_exempt('/hr/employees.php'), 'hr soft-exempt');
$assert(!rateb_public_gate_is_always_exempt('/'), 'home not soft-exempt');

$assert(rateb_public_gate_is_maintenance_exempt('/admin'), 'admin maintenance-exempt');
$assert(rateb_public_gate_is_maintenance_exempt('/admin/settings.php'), 'admin settings maintenance-exempt');
$assert(rateb_public_gate_is_maintenance_exempt('/public/assets/css/app.css'), 'assets maintenance-exempt');
$assert(!rateb_public_gate_is_maintenance_exempt('/'), 'home locked in maintenance');
$assert(!rateb_public_gate_is_maintenance_exempt('/dashboard'), 'dashboard locked in maintenance');
$assert(!rateb_public_gate_is_maintenance_exempt('/login'), 'login locked in maintenance');
$assert(!rateb_public_gate_is_maintenance_exempt('/campaign-new.php'), 'campaign locked in maintenance');

$assert(rateb_public_gate_is_marketing_path('/'), 'home is marketing');
$assert(rateb_public_gate_is_marketing_path('/register.php'), 'register is marketing');
$assert(rateb_public_gate_is_marketing_path('/contact'), 'contact is marketing');
$assert(rateb_public_gate_is_marketing_path('/privacy'), 'privacy is marketing');
$assert(rateb_public_gate_is_marketing_path('/terms'), 'terms is marketing');
$assert(!rateb_public_gate_is_marketing_path('/dashboard'), 'dashboard not marketing');
$assert(!rateb_public_gate_is_exempt_path('/'), 'home not legacy exempt');

$_SERVER['SCRIPT_NAME'] = '/admin/index.php';
$_SERVER['SCRIPT_FILENAME'] = '/home/admin/domains/ai.rateb.sa/admin/index.php';
$assert(rateb_public_gate_is_admin_script(), 'admin index is admin script');
$assert(rateb_public_gate_is_exempt_script(), 'admin index soft script exempt');

$_SERVER['SCRIPT_NAME'] = '/dashboard.php';
$_SERVER['SCRIPT_FILENAME'] = '/home/admin/domains/ai.rateb.sa/dashboard.php';
$assert(!rateb_public_gate_is_admin_script(), 'dashboard is not admin script');
$assert(rateb_public_gate_is_exempt_script(), 'dashboard soft script exempt');

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = '/home/admin/domains/ai.rateb.sa/index.php';
$assert(!rateb_public_gate_is_admin_script(), 'index.php not admin script');
$assert(!rateb_public_gate_is_exempt_script(), 'index.php under /home/admin is not exempt by filesystem path');

exit($fail > 0 ? 1 : 0);
