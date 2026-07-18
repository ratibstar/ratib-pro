<?php
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
\Rateb\App\Core\SessionManager::start();
$sessMs = (hrtime(true) - $t3) / 1e6;
session_write_close();
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
    'session_start_ms' => round($sessMs, 2),
    'included_rateb_files' => $rateb,
    'included_total' => count(get_included_files()),
    'eager_ctrl_model_svc_files' => $eager,
    'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    'opcache_enabled' => !empty($op['opcache_enabled']),
    'opcache_hit_rate' => isset($op['opcache_statistics']['opcache_hit_rate'])
        ? round($op['opcache_statistics']['opcache_hit_rate'], 2) : null,
    'cached_scripts' => $op['opcache_statistics']['num_cached_scripts'] ?? null,
], JSON_UNESCAPED_SLASHES) . "\n";
