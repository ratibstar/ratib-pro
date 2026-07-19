<?php
declare(strict_types=1);
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
    $root . '/config/app.php',
    $root . '/views/layouts/main.php',
    $root . '/app/services/ApprovalOversightService.php',
    $root . '/app/services/AccountingService.php',
    $root . '/app/services/DashboardService.php',
    $root . '/app/controllers/Admin/DashboardController.php',
    $root . '/app/controllers/Admin/DashboardChartsController.php',
];
foreach ($files as $f) {
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($f, true);
    }
    echo 'invalidate ' . $f . ' exists=' . (is_file($f) ? '1' : '0') . "\n";
}
echo 'reset=' . (function_exists('opcache_reset') ? var_export(opcache_reset(), true) : 'n/a') . "\n";
$app = @file_get_contents($root . '/config/app.php');
echo 'disk_build=' . (preg_match("/RATEB_ASSET_BUILD',\s*'([^']+)'/", (string) $app, $m) ? $m[1] : '?') . "\n";
$ao = @file_get_contents($root . '/app/services/ApprovalOversightService.php');
echo 'menuCounts_no_alter=' . (str_contains((string) $ao, 'Never run schema ALTER from sidebar') ? '1' : '0') . "\n";
@unlink(__FILE__);
