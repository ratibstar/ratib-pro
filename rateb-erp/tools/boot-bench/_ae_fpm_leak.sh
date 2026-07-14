#!/bin/bash
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
cat > "$ROOT/public/_ae_fpm_leak.php" <<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: application/json');
$ROOT = dirname(__DIR__);
require_once $ROOT.'/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($ROOT);
\Rateb\App\Core\Auth::bootstrapFromSession();
if (!\Rateb\App\Core\Auth::check()) { echo '{"ok":false}'; exit; }

// Mirror rateb_app_route growth measurement via Reflection... can't access static.
// Instrument by calling and measuring per-bucket times.
$buckets = [1,10,50,100,200,500,1000,2000];
$prev = 0;
$times = [];
$tAll = hrtime(true);
$n = 0;
foreach ($buckets as $target) {
    $t = hrtime(true);
    while ($n < $target) {
        rateb_app_route('support-tickets'); // hits conflictRoots+access merge path
        $n++;
    }
    $times["to_$target"] = round((hrtime(true)-$t)/1e6,3);
}
// Estimate ops registration calls
require_once RATEB_ROOT.'/app/helpers/Request.php';
require_once RATEB_ROOT.'/routes/middleware-helpers.php';
$router = new \Rateb\App\Core\Router();
// Reset process... can't reset static. Fresh estimate: count calls via wrapper
$callCount = 0;
$origApp = null;
$tOps = hrtime(true);
// Load ops with counting by redefining is impossible; count $app( in file
$src = file_get_contents(RATEB_ROOT.'/routes/modules/ops.php');
$appCalls = preg_match_all('/\$app\s*\(/', $src);
$directCalls = preg_match_all('/rateb_app_route\s*\(/', $src);
$ca = file_get_contents(RATEB_ROOT.'/routes/company-access.php');
$appCallsCa = preg_match_all('/\$app\s*\(/', $ca);

echo json_encode([
  'ok'=>true,
  'company_access'=>rateb_company_access_routes_enabled(),
  'platform_host'=>rateb_is_platform_oversight_host(),
  'http_host'=>$_SERVER['HTTP_HOST'] ?? null,
  'bucket_times_ms'=>$times,
  'ops_php_app_call_sites'=>$appCalls,
  'ops_php_direct_rateb_app_route'=>$directCalls,
  'company_access_app_call_sites'=>$appCallsCa,
  'estimated_runtime_calls_note'=>'foreach expands sites; route_count after require is better',
], JSON_PRETTY_PRINT);
PHP

# CLI company_access + same buckets
echo "=== CLI leak profile ==="
$PHP <<'PHP'
<?php
$ROOT='/home/admin/domains/rateb.sa/public_html/rateb-erp';
require_once $ROOT.'/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($ROOT);
$buckets=[1,10,50,100,200,500,1000,2000];
$n=0; $times=[];
foreach($buckets as $target){
  $t=hrtime(true);
  while($n<$target){ rateb_app_route('support-tickets'); $n++; }
  $times["to_$target"]=round((hrtime(true)-$t)/1e6,3);
}
echo json_encode([
  'sapi'=>PHP_SAPI,
  'company_access'=>function_exists('rateb_company_access_routes_enabled')?rateb_company_access_routes_enabled():null,
  'platform_host'=>function_exists('rateb_is_platform_oversight_host')?rateb_is_platform_oversight_host():null,
  'bucket_times_ms'=>$times,
], JSON_PRETTY_PRINT),"\n";
PHP

C=$($PHP "$ROOT/tools/boot-bench/remote-auth.php" mint | $PHP -r 'echo ($j=json_decode(stream_get_contents(STDIN),true))["session_name"]."=".$j["session_id"];')
R='--resolve rateb.sa:443:167.233.71.107'
echo "=== FPM leak profile ==="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_fpm_leak.php"
echo
rm -f "$ROOT/public/_ae_fpm_leak.php"
