<?php
declare(strict_types=1);
/**
 * One-shot FPM OPcache reset after ApiController token policy deploy.
 * Self-deletes. validate_timestamps=0 requires explicit reset.
 */
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t = (string) ($_GET['t'] ?? '');
if (!hash_equals('b8e4d02f3c95e167f2a1b049d5c83671', $t)) {
    http_response_code(404);
    echo "not_found\n";
    exit;
}
$root = dirname(__DIR__);
$files = [
    $root . '/app/controllers/Api/ApiController.php',
    $root . '/app/services/ApiTokenService.php',
];
foreach ($files as $f) {
    $exists = is_file($f) ? 'yes' : 'no';
    $ok = null;
    if (function_exists('opcache_invalidate') && is_file($f)) {
        $ok = @opcache_invalidate($f, true);
    }
    echo "invalidate exists={$exists} ok=" . var_export($ok, true) . " {$f}\n";
}
echo 'reset=' . (function_exists('opcache_reset') ? var_export(opcache_reset(), true) : 'n/a') . "\n";
$ctrl = @file_get_contents($root . '/app/controllers/Api/ApiController.php');
echo 'disk_has_sa_gate=' . (is_string($ctrl) && preg_match('/is_super_admin.*?=== 1/s', $ctrl) ? 'yes' : 'no') . "\n";
echo 'disk_has_company_gate=' . (is_string($ctrl) && str_contains($ctrl, '$companyId < 1') ? 'yes' : 'no') . "\n";
@unlink(__FILE__);
