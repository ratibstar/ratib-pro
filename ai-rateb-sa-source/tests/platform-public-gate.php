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

$assert(rateb_public_gate_is_infrastructure_exempt('/admin/'), 'admin infra exempt');
$assert(rateb_public_gate_is_infrastructure_exempt('/login'), 'login infra exempt');
$assert(!rateb_public_gate_is_infrastructure_exempt('/dashboard'), 'dashboard blocked under maintenance');
$assert(!rateb_public_gate_is_infrastructure_exempt('/hr/'), 'hr blocked under maintenance');
$assert(rateb_public_gate_is_marketing_path('/'), 'home is marketing');
$assert(!rateb_public_gate_is_marketing_path('/dashboard'), 'dashboard not marketing');

exit($fail > 0 ? 1 : 0);