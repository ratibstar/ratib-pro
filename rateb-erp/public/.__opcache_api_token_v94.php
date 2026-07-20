<?php
declare(strict_types=1);
/** OPcache invalidate — ESS API subscription bypass deploy. */
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t = (string) ($_GET['t'] ?? '');
if (!hash_equals('e1b735c6f28d490c5d372a8f9e169a4', $t)) {
    http_response_code(404);
    echo "not_found\n";
    exit;
}
$root = dirname(__DIR__);
$files = [
    $root . '/app/controllers/Api/ApiController.php',
    $root . '/app/services/PlanLimitService.php',
    $root . '/app/Core/Middleware/Middleware.php',
];
foreach ($files as $f) {
    if (function_exists('opcache_invalidate') && is_file($f)) {
        @opcache_invalidate($f, true);
    }
    echo 'invalidate ' . basename($f) . "\n";
}
if (function_exists('opcache_reset')) {
    opcache_reset();
}
echo "ok\n";
