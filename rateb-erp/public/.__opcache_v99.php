<?php
declare(strict_types=1);
header('Content-Type: application/json');
$root = dirname(__DIR__);
$f = $root . '/app/services/HrEssPhaseCService.php';
$out = [
    'build' => trim((string) @file_get_contents(__DIR__ . '/ratib-erp-build.txt')),
    'has_homeLeaveBalances' => is_file($f) && str_contains((string) file_get_contents($f), 'homeLeaveBalances'),
    'opcache_reset' => function_exists('opcache_reset') ? opcache_reset() : null,
];
if (is_file($f) && function_exists('opcache_invalidate')) {
    opcache_invalidate($f, true);
    opcache_invalidate($root . '/app/services/HrService.php', true);
}
echo json_encode($out);
