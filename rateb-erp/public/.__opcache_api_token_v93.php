<?php
declare(strict_types=1);
/**
 * OPcache invalidate after API token rate-limit policy deploy.
 * Does NOT self-delete (PX-Deploy integrity requires Git == disk).
 */
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t = (string) ($_GET['t'] ?? '');
if (!hash_equals('d0a6f24b5e17c389b4c261f7e8d05893', $t)) {
    http_response_code(404);
    echo "not_found\n";
    exit;
}
$root = dirname(__DIR__);
$files = [
    $root . '/app/controllers/Api/ApiController.php',
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
echo 'disk_has_failure_only_rate=' . (is_string($ctrl) && str_contains($ctrl, 'Count failures only') ? 'yes' : 'no') . "\n";
