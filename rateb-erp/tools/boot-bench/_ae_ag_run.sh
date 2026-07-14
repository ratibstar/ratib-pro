#!/bin/bash
# Phase AG — deploy fix, measure growth gone, route SHA parity, TTFB. Restore nothing special (fix stays).
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
APP="$ROOT/config/app.php"
REPORT="$ROOT/tools/boot-bench/reports"
mkdir -p "$REPORT"
R='--resolve rateb.sa:443:167.233.71.107'

# --- Probe: routes SHA + timings (uses production rateb_app_route from app.php) ---
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

// Reflect routes for SHA (method|pattern|handler|mw)
$ref = new ReflectionClass($router);
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
/** @var list<array{method:string,pattern:string,handler:mixed,middleware:array}> $routes */
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

// Spot-check rateb_app_route outputs (behaviour samples)
$samples = [];
foreach (['hr', 'inventory', 'accounting', 'users', 'support-tickets', 'dashboard-x', 'crm/leads'] as $p) {
    $samples[$p] = rateb_app_route($p);
}

$totalMs = (hrtime(true) - $tReq) / 1e6;
$ag = $GLOBALS['AG_RATEB_APP_ROUTE'] ?? null;

$out = [
    'ok' => true,
    'sapi' => PHP_SAPI,
    'request_total_ms' => round($totalMs, 3),
    'routes_load_ms' => round($loadMs, 3),
    'route_count' => count($routes),
    'route_table_sha256' => $sha,
    'loaded' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
    'rateb_app_route_samples' => $samples,
    'company_access' => function_exists('rateb_company_access_routes_enabled') ? rateb_company_access_routes_enabled() : null,
];
if (is_array($ag)) {
    $calls = $ag['calls'];
    $n = count($calls);
    $sumWall = 0.0;
    $sumLookup = 0.0;
    foreach ($calls as $c) {
        $sumWall += $c['wall_ms'];
        $sumLookup += $c['lookup_ms'];
    }
    $sizes = array_unique(array_column($calls, 'size'));
    $out['instrumented'] = true;
    $out['invocation_count'] = $n;
    $out['initial_size'] = $ag['initial_size'];
    $out['final_size'] = $ag['final_size'];
    $out['size_stable'] = $ag['initial_size'] === $ag['final_size'] && count($sizes) === 1;
    $out['unique_ok'] = $ag['unique_ok'] ?? null;
    $out['merge_calls'] = $ag['merge_calls'];
    $out['sum_merge_ms'] = round((float) ($ag['sum_merge_ms'] ?? 0), 6);
    $out['sum_lookup_ms'] = round($sumLookup, 6);
    $out['sum_wall_ms'] = round($sumWall, 3);
    $out['pct_of_request'] = round(100.0 * $sumWall / max(0.001, $totalMs), 2);
    $table = [];
    foreach ($calls as $c) {
        if ($c['n'] === 1 || ($c['n'] % 100) === 0 || $c['n'] === $n) {
            $table[] = $c;
        }
    }
    $out['table_every_100'] = $table;
} else {
    $out['instrumented'] = false;
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

# 1) AFTER with instrumented fix for size proof
cp -a "$APP" /tmp/app.php.ag-prod-bak
$PHP -r '
$p="/home/admin/domains/rateb.sa/public_html/rateb-erp/config/app.php";
$app=file_get_contents($p);
$start=strpos($app,"if (!function_exists('\''rateb_app_route'\''))");
$end=strpos($app,"if (!function_exists('\''rateb_app_url'\''))", $start);
if($start===false||$end===false){fwrite(STDERR,"markers\n");exit(1);}
$repl="if (!function_exists('\''rateb_app_route'\'')) {\n"
  ."    require dirname(__DIR__) . '\''/tools/boot-bench/_ae_ag_rateb_app_route.inc.php'\'';\n"
  ."}\n\n";
file_put_contents($p,substr($app,0,$start).$repl.substr($app,$end));
echo "PATCHED instrumented AG\n";
'

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/ag_mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ag_mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')

echo "===== AFTER instrumented ====="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_ag_probe.php" | tee "$REPORT/phase-ag-after-instrumented.json"
echo

# 2) Install CLEAN fix (overwrite app.php with scp'd fixed copy — already at APP from scp before this script)
# Re-read: we will scp clean app.php AFTER this script's measure, OR restore bak then copy fixed.
# Fixed content is already in tools; apply clean function via php extract from uploaded clean file.
# Prefer: scp clean app.php overwrites before this script starts — then we patched over it.
# Restore from the FIXED local that was scp'd as app.php.ag-clean
if [[ -f "$ROOT/config/app.php.ag-clean" ]]; then
  cp -a "$ROOT/config/app.php.ag-clean" "$APP"
  echo "INSTALLED clean AG fix from app.php.ag-clean"
else
  # fall back to unpatch using backup then we'll scp — keep instrumented out
  echo "WARN: no app.php.ag-clean; leaving instrumented require — expect scp clean next"
fi

echo "===== AFTER clean (routes + TTFB) ====="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_ag_probe.php" | tee "$REPORT/phase-ag-after-clean.json"
echo

# TTFB curls
for p in "/admin/" "/admin/hr?company_id=22" "/admin/ops/inventory?company_id=22" "/admin/ops/accounting?company_id=22"; do
  echo "===== TTFB $p ====="
  curl -sk $R -b "$C" -o /dev/null -w "ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
    "https://rateb.sa/rateb-erp/public$p"
done | tee "$REPORT/phase-ag-ttfb.txt"

rm -f "$ROOT/public/_ae_ag_probe.php"
echo PROBE_REMOVED
