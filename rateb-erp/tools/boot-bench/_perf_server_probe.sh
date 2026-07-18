#!/bin/bash
# Remote PHP bootstrap timing (no Composer vendor — custom Bootstrap autoload).
set -euo pipefail
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
php <<'PHP'
<?php
$root = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
$t0 = hrtime(true);
require_once $root . '/app/Core/Bootstrap.php';
$requireMs = (hrtime(true) - $t0) / 1e6;
$t1 = hrtime(true);
\Rateb\App\Core\Bootstrap::init($root);
$bootMs = (hrtime(true) - $t1) / 1e6;
$t2 = hrtime(true);
$pdo = \Rateb\App\Core\Database::pdo();
$pdo->query('SELECT 1')->fetchColumn();
$dbMs = (hrtime(true) - $t2) / 1e6;
$t3 = hrtime(true);
\Rateb\App\Core\SessionManager::start();
$sessMs = (hrtime(true) - $t3) / 1e6;
session_write_close();
$op = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
$ratebFiles = 0;
foreach (get_included_files() as $f) {
    if (strpos($f, 'rateb-erp') !== false) {
        $ratebFiles++;
    }
}
$eager = 0;
foreach (get_included_files() as $f) {
    if (preg_match('#/(controllers|models|services)/#', $f)) {
        $eager++;
    }
}
echo json_encode([
    'bootstrap_require_ms' => round($requireMs, 2),
    'bootstrap_init_ms' => round($bootMs, 2),
    'db_ping_ms' => round($dbMs, 2),
    'session_start_ms' => round($sessMs, 2),
    'included_rateb_files' => $ratebFiles,
    'included_total' => count(get_included_files()),
    'eager_ctrl_model_svc_files' => $eager,
    'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    'opcache_enabled' => !empty($op['opcache_enabled']),
    'opcache_hit_rate' => isset($op['opcache_statistics']['opcache_hit_rate'])
        ? round($op['opcache_statistics']['opcache_hit_rate'], 2) : null,
    'cached_scripts' => $op['opcache_statistics']['num_cached_scripts'] ?? null,
], JSON_UNESCAPED_SLASHES) . "\n";
PHP

# FPM document TTFB via public host (not loopback resolve — that 404'd)
COOKIE=$(php /tmp/remote-auth.php mint 2>/dev/null || php "$ROOT/tools/boot-bench/remote-auth.php mint")
SN=$(php -r '$j=json_decode(getenv("J")?:file_get_contents("php://stdin"),true); echo $j["session_name"]??"rateb_erp";' <<<"$COOKIE")
SID=$(php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo $j["session_id"]??"";' <<<"$COOKIE")
echo "SN=$SN"
printf "rateb.sa\tFALSE\t/\tTRUE\t0\t%s\t%s\n" "$SN" "$SID" > /tmp/rateb-perf.cookie
echo COLD_PUBLIC
curl -sk -L -o /tmp/admin_perf.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" \
  -b /tmp/rateb-perf.cookie -c /tmp/rateb-perf.cookie \
  -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo WARM_PUBLIC
curl -sk -L -o /tmp/admin_perf2.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" \
  -b /tmp/rateb-perf.cookie \
  -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo EXEC_DASH
curl -sk -o /tmp/exec.json -w "code=%{http_code} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" \
  -b /tmp/rateb-perf.cookie -H "Accept: application/json" \
  "https://rateb.sa/rateb-erp/public/admin/executive-dashboard"
echo PROFILE
curl -sk -o /dev/null -w "code=%{http_code} ttfb=%{time_starttransfer} total=%{time_total}\n" \
  -b /tmp/rateb-perf.cookie -H "Accept: application/json" \
  "https://rateb.sa/rateb-erp/public/admin/ops/profile?company_id=22"
echo NOTIF
curl -sk -o /dev/null -w "code=%{http_code} ttfb=%{time_starttransfer} total=%{time_total}\n" \
  -b /tmp/rateb-perf.cookie -H "Accept: application/json" \
  "https://rateb.sa/rateb-erp/public/admin/ops/notifications?company_id=22"
wc -c /tmp/admin_perf.html
php -r 'echo "eager_list_count="; echo substr_count(file_get_contents("/home/admin/domains/rateb.sa/public_html/rateb-erp/app/Core/Bootstrap.php"), "require_once"); echo "\n";'
