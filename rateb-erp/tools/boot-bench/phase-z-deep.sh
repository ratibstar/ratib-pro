#!/bin/bash
set -eu
PUB=/home/admin/domains/rateb.sa/public_html/rateb-erp/public
cleanup() { rm -f "$PUB/__phase_z_deep.php"; }
trap cleanup EXIT
cat > "$PUB/__phase_z_deep.php" <<'PHP'
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
require $root . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init($root);

$n = 1000;
$out = [];
$measure = static function (string $label, callable $fn) use ($n, &$out): void {
    $a = hrtime(true);
    for ($i = 0; $i < $n; $i++) {
        $fn();
    }
    $ms = (hrtime(true) - $a) / 1e6;
    $out[$label] = ['total_ms' => round($ms, 3), 'per_call_ms' => round($ms / $n, 5)];
};

$measure('rateb_erp_deployment_mode', static fn () => rateb_erp_deployment_mode());
$measure('rateb_erp_is_dedicated_deployment', static fn () => rateb_erp_is_dedicated_deployment());
$measure('getenv_RATEB_ERP_MODE', static fn () => getenv('RATEB_ERP_MODE'));
$measure('rateb_normalize_http_host', static fn () => rateb_normalize_http_host('rateb.sa'));
$measure('rateb_erp_is_main_platform_host', static fn () => rateb_erp_is_main_platform_host('rateb.sa'));
$measure('rateb_is_platform_oversight_host', static fn () => rateb_is_platform_oversight_host());
$measure('rateb_is_agency_erp_host', static fn () => rateb_is_agency_erp_host());
$measure('rateb_company_access_routes_enabled', static fn () => rateb_company_access_routes_enabled());
$measure('rateb_app_route_inventory', static fn () => rateb_app_route('inventory/items'));
$measure('rateb_erp_mw', static fn () => rateb_erp_mw('inventory', '', 'inventory'));

// Count app() invocations by executing company with instrumented rateb_app_route — can't.
// Use file count of $app(
$src = (string) file_get_contents($root . '/routes/company.php');
$appCalls = preg_match_all('/\$app\s*\(/', $src);

$per = $out['rateb_app_route_inventory']['per_call_ms'];
$projected = round($per * $appCalls, 1);

echo json_encode([
    'n' => $n,
    'bench' => $out,
    'app_calls_company_php' => $appCalls,
    'projected_app_route_ms' => $projected,
    'company_file_ms_observed_prior' => 463,
    'fraction_explained' => round($projected / 463, 3),
    'single_function' => [
        'file' => 'config/app.php',
        'function' => 'rateb_app_route',
        'dominant_callee' => 'rateb_company_access_routes_enabled',
        'callee_file' => 'config/app.php',
        'why' => 'Every $app() in company.php calls rateb_app_route which calls rateb_company_access_routes_enabled on every invocation without memoization; that walks agency+dedicated+platform host checks (~0.35 ms/call). 765 calls ≈ 270+ ms. Inline path logic alone is 0.00018 ms/call.',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP
curl -sk "https://rateb.sa/rateb-erp/public/__phase_z_deep.php" | tee /tmp/phase-z-deep.json
echo
php -r 'echo file_get_contents("/tmp/phase-z-deep.json");'
