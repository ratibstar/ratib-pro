#!/bin/bash
# Phase AF — patch in instrumented rateb_app_route, measure, restore. Evidence only.
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
APP="$ROOT/config/app.php"
BAK="/tmp/app.php.af-bak.$$"
INC="$ROOT/tools/boot-bench/_ae_af_rateb_app_route.inc.php"
REPORT_DIR="$ROOT/tools/boot-bench/reports"
mkdir -p "$REPORT_DIR"

cp -a "$APP" "$BAK"
cleanup() {
  if [[ -f "$BAK" ]]; then
    cp -a "$BAK" "$APP"
    rm -f "$BAK"
    echo "RESTORED app.php"
  fi
  rm -f "$ROOT/public/_ae_af_probe.php"
}
trap cleanup EXIT

test -f "$INC" || { echo "missing $INC — scp it first"; exit 1; }

# Patch: swap rateb_app_route block for require of instrumented twin
$PHP -r '
$appPath = "/home/admin/domains/rateb.sa/public_html/rateb-erp/config/app.php";
$app = file_get_contents($appPath);
$start = strpos($app, "if (!function_exists('\''rateb_app_route'\''))");
$end = strpos($app, "if (!function_exists('\''rateb_app_url'\''))", $start);
if ($start === false || $end === false) { fwrite(STDERR, "markers not found\n"); exit(1); }
$repl = "if (!function_exists('\''rateb_app_route'\'')) {\n"
    . "    // Phase AF temporary instrumented twin — restored after measurement\n"
    . "    require dirname(__DIR__) . '\''/tools/boot-bench/_ae_af_rateb_app_route.inc.php'\'';\n"
    . "}\n\n";
file_put_contents($appPath, substr($app, 0, $start) . $repl . substr($app, $end));
echo "PATCHED\n";
'

cat > "$ROOT/public/_ae_af_probe.php" <<'PHP'
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
$totalMs = (hrtime(true) - $tReq) / 1e6;

$af = $GLOBALS['AF_RATEB_APP_ROUTE'] ?? null;
if (!is_array($af) || empty($af['calls'])) {
    echo json_encode(['ok' => false, 'error' => 'no_af_log']);
    exit;
}

$calls = $af['calls'];
$n = count($calls);
$table = [];
foreach ($calls as $c) {
    if ($c['n'] === 1 || ($c['n'] % 100) === 0 || $c['n'] === $n) {
        $table[] = $c;
    }
}

$sumWall = 0.0;
$sumIn = 0.0;
$sumMerge = 0.0;
$sumExplode = 0.0;
foreach ($calls as $c) {
    $sumWall += $c['wall_ms'];
    $sumIn += $c['in_array_ms'];
    $sumMerge += $c['merge_ms'];
    $sumExplode += $c['explode_ms'];
}

$first = $calls[0];
$last = $calls[$n - 1];
$initial = (int) $af['initial_size_before_merge'];
$final = (int) $last['size_after'];

$grewEvery = true;
$deltas = [];
for ($i = 0; $i < $n; $i++) {
    if (!$calls[$i]['did_merge']) {
        $grewEvery = false;
        break;
    }
    if ($i > 0 && $calls[$i]['size_before'] !== $calls[$i - 1]['size_after']) {
        $grewEvery = false;
        break;
    }
    if ($calls[$i]['size_after'] <= $calls[$i]['size_before']) {
        $grewEvery = false;
        break;
    }
    $deltas[] = $calls[$i]['size_after'] - $calls[$i]['size_before'];
}

$trend = [];
$step = max(1, (int) floor($n / 50));
for ($i = 0; $i < $n; $i += $step) {
    $c = $calls[$i];
    $trend[] = [
        'n' => $c['n'],
        'size_after' => $c['size_after'],
        'wall_ms' => $c['wall_ms'],
        'in_array_ms' => $c['in_array_ms'],
        'merge_ms' => $c['merge_ms'],
        'explode_ms' => $c['explode_ms'],
    ];
}
if ($trend !== [] && $trend[count($trend) - 1]['n'] !== $last['n']) {
    $trend[] = [
        'n' => $last['n'],
        'size_after' => $last['size_after'],
        'wall_ms' => $last['wall_ms'],
        'in_array_ms' => $last['in_array_ms'],
        'merge_ms' => $last['merge_ms'],
        'explode_ms' => $last['explode_ms'],
    ];
}

$first100 = array_slice($calls, 0, min(100, $n));
$last100 = array_slice($calls, max(0, $n - 100));
$avg = static function (array $rows, string $k): float {
    $s = 0.0;
    foreach ($rows as $r) {
        $s += $r[$k];
    }
    return $s / max(1, count($rows));
};

echo json_encode([
    'ok' => true,
    'sapi' => PHP_SAPI,
    'company_access' => $af['company_access'],
    'request_total_ms' => round($totalMs, 3),
    'routes_load_ms' => round($loadMs, 3),
    'route_count' => $router->routeCount(),
    'loaded' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
    'invocation_count' => $n,
    'initial_size_before_merge' => $initial,
    'size_after_call_1' => $first['size_after'],
    'final_size_after_merge' => $final,
    'growth_per_call' => $deltas[0] ?? null,
    'growth_delta_total' => $final - $initial,
    'growth_factor' => $initial > 0 ? round($final / $initial, 4) : null,
    'size_grows_every_call' => $grewEvery,
    'sum_wall_ms' => round($sumWall, 3),
    'sum_merge_ms' => round($sumMerge, 3),
    'sum_explode_ms' => round($sumExplode, 3),
    'sum_in_array_ms' => round($sumIn, 3),
    'avg_wall_ms' => round($sumWall / max(1, $n), 6),
    'avg_in_array_ms' => round($sumIn / max(1, $n), 6),
    'avg_in_array_first_100_ms' => round($avg($first100, 'in_array_ms'), 6),
    'avg_in_array_last_100_ms' => round($avg($last100, 'in_array_ms'), 6),
    'avg_wall_first_100_ms' => round($avg($first100, 'wall_ms'), 6),
    'avg_wall_last_100_ms' => round($avg($last100, 'wall_ms'), 6),
    'pct_of_request' => round(100.0 * $sumWall / max(0.001, $totalMs), 2),
    'pct_of_routes_load' => round(100.0 * $sumWall / max(0.001, $loadMs), 2),
    'table_every_100' => $table,
    'plot_samples' => $trend,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/af_mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/af_mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
R='--resolve rateb.sa:443:167.233.71.107'
OUT="$REPORT_DIR/phase-af-growth.json"
echo "===== AF FPM probe ====="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_af_probe.php" | tee "$OUT"
echo
echo "WROTE $OUT"
