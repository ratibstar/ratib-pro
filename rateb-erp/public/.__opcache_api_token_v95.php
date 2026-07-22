<?php
declare(strict_types=1);
/** OPcache invalidate — ESS fallback company deploy. */
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t = (string) ($_GET['t'] ?? '');
if (!hash_equals('f2c846d7a039e51b6e483b9c0f27a158', $t)) {
    http_response_code(404);
    echo "not_found\n";
    exit;
}
$root = dirname(__DIR__);
foreach ([
    $root . '/app/controllers/Api/ApiController.php',
    $root . '/app/services/PlanLimitService.php',
    $root . '/app/services/MobileAppConfigService.php',
    $root . '/app/Core/Middleware/Middleware.php',
] as $f) {
    if (function_exists('opcache_invalidate') && is_file($f)) {
        @opcache_invalidate($f, true);
    }
}
if (function_exists('opcache_reset')) {
    opcache_reset();
}
$plan = @file_get_contents($root . '/app/services/PlanLimitService.php');
echo 'build=' . trim((string) @file_get_contents(__DIR__ . '/ratib-erp-build.txt')) . "\n";
echo 'has_ess_fallback=' . (is_string($plan) && str_contains($plan, 'essFallbackCompanyId') ? 'yes' : 'no') . "\n";
echo "ok\n";
