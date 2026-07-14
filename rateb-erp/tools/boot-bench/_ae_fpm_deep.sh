#!/bin/bash
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
echo "=== FPM vs CLI php.ini / modules ==="
echo "-- CLI --"
$PHP -m 2>/dev/null | tr '\n' ' '; echo
$PHP -i 2>/dev/null | grep -E '^(Loaded Configuration|Additional \.ini|auto_prepend|auto_append|realpath_cache|opcache\.|xdebug\.|zend_extension)' | head -40
echo "-- FPM pool sniff via curl to probe --"
# Deploy deep probe
cat > "$ROOT/public/_ae_fpm_deep.php" <<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$path = '/' . trim(str_replace('\\', '/', (string)($_GET['path'] ?? '/admin/hr')), '/');
if ($path !== '/') { $path = rtrim($path, '/') ?: '/'; }
$ROOT = dirname(__DIR__);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public' . $path . '?company_id=22';
$_GET['company_id'] = '22';

$t0 = hrtime(true);
$marks = [];
$mark = static function (string $n) use (&$marks, $t0): void {
    $marks[$n] = round((hrtime(true) - $t0) / 1e6, 3);
};

$info = [
    'sapi' => PHP_SAPI,
    'php_version' => PHP_VERSION,
    'opcache_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
    'xdebug' => extension_loaded('xdebug'),
    'auto_prepend' => (string) ini_get('auto_prepend_file'),
    'auto_append' => (string) ini_get('auto_append_file'),
    'realpath_cache_size' => ini_get('realpath_cache_size'),
    'realpath_cache_ttl' => ini_get('realpath_cache_ttl'),
    'include_path' => ini_get('include_path'),
    'modules' => get_loaded_extensions(),
];

require_once $ROOT . '/app/Core/Bootstrap.php';
$mark('after_bootstrap_require');
\Rateb\App\Core\Bootstrap::init($ROOT);
$mark('after_bootstrap_init');
\Rateb\App\Core\Auth::bootstrapFromSession();
$mark('after_auth');
if (!\Rateb\App\Core\Auth::check()) {
    echo json_encode(['ok'=>false,'error'=>'auth','info'=>$info,'marks'=>$marks]); exit;
}

require_once RATEB_ROOT . '/app/helpers/Request.php';
$router = new \Rateb\App\Core\Router();
$mark('before_modules');

$inc0 = get_included_files();
$tAuth = hrtime(true);
require RATEB_ROOT . '/routes/modules/auth.php';
$auth_ms = round((hrtime(true) - $tAuth) / 1e6, 3);
$mark('after_auth_module');
$inc1 = get_included_files();

$tOps = hrtime(true);
require RATEB_ROOT . '/routes/modules/ops.php';
$ops_ms = round((hrtime(true) - $tOps) / 1e6, 3);
$mark('after_ops_module');
$inc2 = get_included_files();

$auth_new = array_values(array_diff($inc1, $inc0));
$ops_new = array_values(array_diff($inc2, $inc1));
$opsSizes = [];
foreach ($ops_new as $f) { $opsSizes[$f] = @filesize($f) ?: 0; }
arsort($opsSizes);

// Also time empty loop of Router::add equivalent cost: 1100 adds
$r2 = new \Rateb\App\Core\Router();
$tAdd = hrtime(true);
for ($i = 0; $i < 1100; $i++) {
    $r2->get('/bench/' . $i, ['Bench', 'x']);
}
$add_ms = round((hrtime(true) - $tAdd) / 1e6, 3);

$total = round((hrtime(true) - $t0) / 1e6, 3);
echo json_encode([
    'ok' => true,
    'path' => $path,
    'total_ms' => $total,
    'marks_abs_ms' => $marks,
    'auth_module_ms' => $auth_ms,
    'ops_module_ms' => $ops_ms,
    'router_add_1100_bench_ms' => $add_ms,
    'route_count' => $router->routeCount(),
    'ops_new_includes' => count($ops_new),
    'ops_new_include_bytes' => array_sum($opsSizes),
    'ops_top_includes' => array_slice($opsSizes, 0, 15, true),
    'info' => $info,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/ae_mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ae_mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
R='--resolve rateb.sa:443:167.233.71.107'
echo "===== Deep FPM probe /admin/hr ====="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_fpm_deep.php?path=/admin/hr"
echo
echo "===== Deep FPM probe warm2 ====="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_fpm_deep.php?path=/admin/hr"
echo
echo "===== Deep FPM probe /admin ====="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_fpm_deep.php?path=/admin"
echo
rm -f "$ROOT/public/_ae_fpm_deep.php"
echo DEEP_REMOVED

# FPM ini via php-fpm or pool
echo "=== Looking for fpm ini ==="
ls /usr/local/php83/etc/ 2>/dev/null || true
ls /usr/local/php83/etc/php-fpm.d/ 2>/dev/null | head || true
# DirectAdmin typically
find /usr/local/php83 -name 'php.ini' 2>/dev/null | head -5
find /usr/local -name '*fpm*.conf' 2>/dev/null | head -10
