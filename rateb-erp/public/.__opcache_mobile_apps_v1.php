<?php
declare(strict_types=1);
/**
 * One-shot OPcache invalidate for Mobile Apps routes (self-deletes).
 * GET ?t=c9e6a13d4f20b587d1e3f2b6c0a49851
 */
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t = (string) ($_GET['t'] ?? '');
if (!hash_equals('c9e6a13d4f20b587d1e3f2b6c0a49851', $t)) {
    http_response_code(404);
    echo "not_found\n";
    exit;
}
$root = dirname(__DIR__);
$files = [
    $root . '/routes/modules/platform.php',
    $root . '/app/Core/RouteModuleLoader.php',
    $root . '/config/app.php',
    $root . '/views/layouts/main.php',
];
foreach ($files as $f) {
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($f, true);
    }
    echo 'invalidate ' . $f . "\n";
}
echo 'reset=' . (function_exists('opcache_reset') ? var_export(opcache_reset(), true) : 'n/a') . "\n";
@unlink(__FILE__);
