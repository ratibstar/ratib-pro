#!/bin/bash
# Phase AG — capture BEFORE metrics (buggy rateb_app_route still in place)
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
REPORT="$ROOT/tools/boot-bench/reports"
mkdir -p "$REPORT"
R='--resolve rateb.sa:443:167.233.71.107'

# Reuse same probe file as AG (non-instrumented path)
cat > "$ROOT/public/_ae_ag_probe.php" <<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$ROOT = dirname(__DIR__);
$tReq = hrtime(true);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin/hr?company_id=22';
$_GET['company_id'] = '22';
require_once $ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($ROOT);
\Rateb\App\Core\Auth::bootstrapFromSession();
if (!\Rateb\App\Core\Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}
require_once RATEB_ROOT . '/app/helpers/Request.php';
$router = new \Rateb\App\Core\Router();
$tLoad = hrtime(true);
\Rateb\App\Core\RouteModuleLoader::loadForPath($router, '/admin/hr');
$loadMs = (hrtime(true) - $tLoad) / 1e6;
$ref = new ReflectionClass($router);
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
$routes = $prop->getValue($router);
$lines = [];
foreach ($routes as $r) {
    $h = $r['handler'];
    if (is_array($h)) {
        $hs = (is_object($h[0]) ? get_class($h[0]) : (string) $h[0]) . '::' . (string) $h[1];
    } elseif ($h instanceof Closure) {
        $hs = 'Closure';
    } else {
        $hs = 'callable';
    }
    $mw = [];
    foreach ($r['middleware'] as $m) {
        if (is_array($m)) {
            $mw[] = (string) $m[0] . ':' . (string) ($m[1] ?? '');
        } else {
            $mw[] = (string) $m;
        }
    }
    sort($mw);
    $lines[] = $r['method'] . "\t" . $r['pattern'] . "\t" . $hs . "\t" . implode(',', $mw);
}
sort($lines);
$sha = hash('sha256', implode("\n", $lines));
$samples = [];
foreach (['hr', 'inventory', 'accounting', 'users', 'support-tickets', 'dashboard-x', 'crm/leads'] as $p) {
    $samples[$p] = rateb_app_route($p);
}
$totalMs = (hrtime(true) - $tReq) / 1e6;
echo json_encode([
    'ok' => true,
    'phase' => 'before',
    'sapi' => PHP_SAPI,
    'request_total_ms' => round($totalMs, 3),
    'routes_load_ms' => round($loadMs, 3),
    'route_count' => count($routes),
    'route_table_sha256' => $sha,
    'rateb_app_route_samples' => $samples,
    'company_access' => rateb_company_access_routes_enabled(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/ag_mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ag_mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
echo "===== BEFORE ====="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_ag_probe.php" | tee "$REPORT/phase-ag-before.json"
echo
echo "===== BEFORE TTFB ====="
for p in "/admin/" "/admin/hr?company_id=22" "/admin/ops/inventory?company_id=22" "/admin/ops/accounting?company_id=22"; do
  echo -n "$p "
  curl -sk $R -b "$C" -o /dev/null -w "ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
    "https://rateb.sa/rateb-erp/public$p"
done | tee "$REPORT/phase-ag-ttfb-before.txt"
rm -f "$ROOT/public/_ae_ag_probe.php"
echo BEFORE_DONE
