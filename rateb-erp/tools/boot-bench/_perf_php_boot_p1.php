<?php
/**
 * PERF-P1 — Updated PHP bootstrap timing after lazy classmap.
 * Upload to /tmp and run: php /tmp/_perf_php_boot_p1.php
 */
$root = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
$t0 = hrtime(true);
require_once $root . '/app/Core/Bootstrap.php';
$requireMs = (hrtime(true) - $t0) / 1e6;
$t1 = hrtime(true);
\Rateb\App\Core\Bootstrap::init($root);
$bootMs = (hrtime(true) - $t1) / 1e6;
$t2 = hrtime(true);
$pdo = \Rateb\App\Core\Database::connection();
$pdo->query('SELECT 1')->fetchColumn();
$dbMs = (hrtime(true) - $t2) / 1e6;
$t3 = hrtime(true);
$ok = class_exists(\Rateb\App\Controllers\Admin\DashboardController::class);
$classMs = (hrtime(true) - $t3) / 1e6;
$op = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
$rateb = 0;
$eager = 0;
foreach (get_included_files() as $f) {
    if (strpos($f, 'rateb-erp') !== false) {
        $rateb++;
    }
    if (preg_match('#/(controllers|models|services)/#', $f)) {
        $eager++;
    }
}
echo json_encode([
    'bootstrap_require_ms' => round($requireMs, 2),
    'bootstrap_init_ms' => round($bootMs, 2),
    'db_ping_ms' => round($dbMs, 2),
    'dashboard_class_ms' => round($classMs, 2),
    'dashboard_ok' => $ok,
    'included_rateb_files' => $rateb,
    'included_total' => count(get_included_files()),
    'ctrl_model_svc_included' => $eager,
    'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    'opcache_enabled' => !empty($op['opcache_enabled']),
    'opcache_hit_rate' => isset($op['opcache_statistics']['opcache_hit_rate'])
        ? round($op['opcache_statistics']['opcache_hit_rate'], 2) : null,
], JSON_UNESCAPED_SLASHES) . "\n";
