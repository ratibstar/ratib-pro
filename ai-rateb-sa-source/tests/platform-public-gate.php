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

$assert(rateb_public_gate_is_exempt_path('/admin/'), 'admin exempt');
$assert(rateb_public_gate_is_exempt_path('/hr/employees.php'), 'hr exempt');
$assert(rateb_public_gate_is_exempt_path('/dashboard'), 'dashboard exempt');
$assert(!rateb_public_gate_is_exempt_path('/'), 'home not exempt');

exit($fail > 0 ? 1 : 0);
