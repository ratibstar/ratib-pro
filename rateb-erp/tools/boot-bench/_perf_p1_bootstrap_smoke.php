<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/app/Core/Bootstrap.php';
$t0 = hrtime(true);
\Rateb\App\Core\Bootstrap::init($root);
$bootMs = (hrtime(true) - $t0) / 1e6;
$t1 = hrtime(true);
$ok = class_exists(\Rateb\App\Controllers\Admin\ExecutiveDashboardController::class);
$loadMs = (hrtime(true) - $t1) / 1e6;
$t2 = hrtime(true);
$ok2 = class_exists(\Rateb\App\Controllers\Admin\DashboardController::class);
$load2Ms = (hrtime(true) - $t2) / 1e6;
$ratebFiles = 0;
foreach (get_included_files() as $f) {
    if (strpos($f, 'rateb-erp') !== false || strpos($f, 'Documents') !== false) {
        $ratebFiles++;
    }
}
echo json_encode([
    'boot_ms' => round($bootMs, 2),
    'exec_dash_ok' => $ok,
    'exec_dash_ms' => round($loadMs, 2),
    'dashboard_ok' => $ok2,
    'dashboard_ms' => round($load2Ms, 2),
    'included_total' => count(get_included_files()),
    'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
], JSON_UNESCAPED_SLASHES) . "\n";
