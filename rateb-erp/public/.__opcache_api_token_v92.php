<?php
declare(strict_types=1);
/**
 * FPM OPcache invalidate after API token policy deploy.
 * Does NOT self-delete (PX-Deploy integrity requires Git == disk).
 */
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t = (string) ($_GET['t'] ?? '');
if (!hash_equals('c9f5e13a4d06f278a3b2c150e6d94782', $t)) {
    http_response_code(404);
    echo "not_found\n";
    exit;
}
$root = dirname(__DIR__);
$files = [
    $root . '/app/controllers/Api/ApiController.php',
    $root . '/app/services/ApiTokenService.php',
    $root . '/app/Core/Middleware/Middleware.php',
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
echo 'disk_has_primary_bind=' . (is_string($ctrl) && str_contains($ctrl, 'DedicatedTenantPolicy::primaryCompanyId') ? 'yes' : 'no') . "\n";
