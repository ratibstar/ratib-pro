#!/bin/bash
set -eu
PUB=/home/admin/domains/rateb.sa/public_html/rateb-erp/public
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
cleanup() { rm -f "$PUB/__phase_z_leaf.php"; }
trap cleanup EXIT

cat > "$PUB/__phase_z_leaf.php" <<'PHP'
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
require $root . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init($root);
require_once $root . '/routes/middleware-helpers.php';

$n = 500;
$bench = [];

$fns = [
    'rateb_company_access_routes_enabled',
    'rateb_is_agency_erp_host',
    'rateb_erp_is_dedicated_deployment',
    'rateb_is_platform_oversight_host',
];
foreach ($fns as $fn) {
    if (!function_exists($fn)) {
        $bench[$fn] = null;
        continue;
    }
    $a = hrtime(true);
    for ($i = 0; $i < $n; $i++) {
        $fn();
    }
    $bench[$fn] = [
        'total_ms' => round((hrtime(true) - $a) / 1e6, 3),
        'per_call_ms' => round(((hrtime(true) - $a) / 1e6) / $n, 5),
    ];
    // fix: used wrong timing — recompute properly
    $a = hrtime(true);
    for ($i = 0; $i < $n; $i++) {
        $fn();
    }
    $ms = (hrtime(true) - $a) / 1e6;
    $bench[$fn] = ['total_ms' => round($ms, 3), 'per_call_ms' => round($ms / $n, 5)];
}

// Decompose rateb_app_route
$a = hrtime(true);
for ($i = 0; $i < $n; $i++) {
    rateb_app_route('inventory/items');
}
$ms = (hrtime(true) - $a) / 1e6;
$bench['rateb_app_route'] = ['total_ms' => round($ms, 3), 'per_call_ms' => round($ms / $n, 5)];

// Inline equivalent without access check
$a = hrtime(true);
for ($i = 0; $i < $n; $i++) {
    $path = ltrim(preg_replace('#^company/#', '', trim('inventory/items')), '/');
    $conflictRoots = ['inventory'];
    $root = explode('/', $path)[0];
    $x = in_array($root, $conflictRoots, true) ? 'admin/ops/' . $path : 'admin/' . $path;
}
$ms = (hrtime(true) - $a) / 1e6;
$bench['inline_path_only'] = ['total_ms' => round($ms, 3), 'per_call_ms' => round($ms / $n, 5)];

// enabled check alone inside loop like app_route
$a = hrtime(true);
for ($i = 0; $i < $n; $i++) {
    rateb_company_access_routes_enabled();
    $path = ltrim(preg_replace('#^company/#', '', trim('inventory/items')), '/');
    static $cr = null;
    if ($cr === null) {
        $cr = ['inventory','suppliers'];
    }
    if (rateb_company_access_routes_enabled()) {
        $cr = array_merge($cr, ['access-control']);
    }
    $root = explode('/', $path)[0];
    in_array($root, $cr, true);
}
$ms = (hrtime(true) - $a) / 1e6;
$bench['simulate_app_route_body'] = ['total_ms' => round($ms, 3), 'per_call_ms' => round($ms / $n, 5)];

// How many $app() calls needed path? Count from file
$src = file_get_contents($root . '/routes/company.php');
$appCalls = preg_match_all('/\$app\s*\(/', $src);

echo json_encode([
    'n' => $n,
    'bench' => $bench,
    'projected_ms_for_app_calls' => round(($bench['rateb_app_route']['per_call_ms'] ?? 0) * $appCalls, 1),
    'app_calls_in_company_php' => $appCalls,
    'leaf_winner' => array_keys($bench, max(array_map(static fn($b) => is_array($b) ? ($b['per_call_ms'] ?? 0) : 0, $bench))) ?: null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

# Fix leaf_winner properly in post
curl -sk "https://rateb.sa/rateb-erp/public/__phase_z_leaf.php" | tee /tmp/phase-z-leaf.json
echo
php -r '
$j=json_decode(file_get_contents("/tmp/phase-z-leaf.json"),true);
$best=null;$bestMs=-1;
foreach(($j["bench"]??[]) as $k=>$v){
  if(!is_array($v)) continue;
  $p=$v["per_call_ms"]??0;
  if($p>$bestMs){$bestMs=$p;$best=$k;}
}
echo "slowest_leaf=$best per_call_ms=$bestMs\n";
echo "agency=".json_encode($j["bench"]["rateb_is_agency_erp_host"]??null)."\n";
echo "dedicated=".json_encode($j["bench"]["rateb_erp_is_dedicated_deployment"]??null)."\n";
echo "platform=".json_encode($j["bench"]["rateb_is_platform_oversight_host"]??null)."\n";
echo "access=".json_encode($j["bench"]["rateb_company_access_routes_enabled"]??null)."\n";
echo "app_route=".json_encode($j["bench"]["rateb_app_route"]??null)."\n";
echo "inline=".json_encode($j["bench"]["inline_path_only"]??null)."\n";
'
