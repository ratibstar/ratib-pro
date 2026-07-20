<?php
declare(strict_types=1);
/**
 * One-shot FPM OPcache reset after ApiController token policy deploy.
 * Self-deletes. validate_timestamps=0 requires explicit reset.
 */
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t = (string) ($_GET['t'] ?? '');
if (!hash_equals('a7f3c91e2b84d056e1f0a938c4b72560', $t)) {
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
echo 'disk_has_platform_code=' . (is_string($ctrl) && str_contains($ctrl, 'platform_sa_token_disabled') ? 'yes' : 'no') . "\n";
echo 'disk_has_company_gate=' . (is_string($ctrl) && str_contains($ctrl, '$companyId < 1') ? 'yes' : 'no') . "\n";
@unlink(__FILE__);
