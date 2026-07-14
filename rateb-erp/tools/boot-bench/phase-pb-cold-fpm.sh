#!/bin/bash
# Phase PB follow-up: cold FPM spawn vs warm + stage probe (READ ONLY)
set -u
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
OUT=/tmp/phase-pb2
mkdir -p "$OUT"
URL='https://rateb.sa/rateb-erp/public/admin/ops/pos/register?company_id=22'

$PHP /tmp/remote-auth-pa.php mintpos > "$OUT/mint.json"
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/phase-pb2/mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
echo "COOKIE=$C"

# Working FPM stage probe (public IP, not 127.0.0.1)
cat > "$ROOT/public/_pb_fpm_lifecycle.php" <<'PHPEND'
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$path = '/admin/ops/pos/register';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public' . $path . '?company_id=22';
$_GET['company_id'] = '22';
$ROOT = dirname(__DIR__);
$t0 = hrtime(true);
$marks = [];
$mark = static function (string $n) use (&$marks, $t0): void {
    $marks[$n] = round((hrtime(true) - $t0) / 1e6, 3);
};
$opcache = null;
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    if (is_array($st)) {
        $opcache = [
            'enabled' => $st['opcache_enabled'] ?? null,
            'cached_scripts' => $st['opcache_statistics']['num_cached_scripts'] ?? null,
            'hits' => $st['opcache_statistics']['hits'] ?? null,
            'misses' => $st['opcache_statistics']['misses'] ?? null,
            'oom' => $st['cache_full'] ?? null,
        ];
    }
}
$mark('start');
require_once $ROOT . '/app/Core/Bootstrap.php';
$mark('after_bootstrap_require');
$tB = hrtime(true);
\Rateb\App\Core\Bootstrap::init($ROOT);
$boot = round((hrtime(true) - $tB) / 1e6, 3);
$mark('after_bootstrap_init');
if (is_file($ROOT . '/modules/pos/PosModule.php')) {
    require_once $ROOT . '/modules/pos/PosModule.php';
    \Rateb\App\Pos\PosModule::init();
}
$mark('after_pos');
if (is_file($ROOT . '/offline/OfflineModule.php')) {
    require_once $ROOT . '/offline/OfflineModule.php';
    \Rateb\App\Offline\OfflineModule::init();
}
$mark('after_offline');
$tA = hrtime(true);
\Rateb\App\Core\Auth::bootstrapFromSession();
$auth = round((hrtime(true) - $tA) / 1e6, 3);
$mark('after_auth');
if (!\Rateb\App\Core\Auth::check()) {
    echo json_encode(['ok'=>false,'error'=>'auth','marks'=>$marks]); exit;
}
require_once RATEB_ROOT . '/app/helpers/Request.php';
$router = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
$mark('after_routes');
$mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
$mw->handle();
$mark('after_mw');
ob_start();
$tD = hrtime(true);
$router->dispatch('GET', $path);
$html = (string) ob_get_clean();
$disp = round((hrtime(true) - $tD) / 1e6, 3);
$mark('after_dispatch');
$total = round((hrtime(true) - $t0) / 1e6, 3);
$prev = 0.0; $durs = [];
foreach ($marks as $n => $abs) { $durs[$n] = round($abs - $prev, 3); $prev = $abs; }
echo json_encode([
  'ok'=>true,'total_ms'=>$total,'bootstrap_init_ms'=>$boot,'auth_ms'=>$auth,'dispatch_ms'=>$disp,
  'stage_ms'=>$durs,'marks_abs_ms'=>$marks,'html_bytes'=>strlen($html),
  'has_register'=>(bool)preg_match('/data-pos-register/i',$html),
  'opcache'=>$opcache,
  'fpm_pm'=>'ondemand','workers'=>null,
  'ini'=>[
    'session.save_handler'=>ini_get('session.save_handler'),
    'zlib.output_compression'=>ini_get('zlib.output_compression'),
  ],
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
PHPEND

echo "=== workers before idle wait ==="
ps -eo args= | grep -c '[p]hp-fpm: pool' || true
ps -eo pid,etime,args | grep '[p]hp-fpm: pool' || true

echo "=== WAIT 25s for ondemand idle kill (pm.process_idle_timeout=20) ==="
sleep 25

echo "=== workers after idle wait ==="
ps -eo pid,etime,args | grep '[p]hp-fpm: pool' || true
BEFORE=$(ps -eo args= | grep -c '[p]hp-fpm: pool' || true)
echo "workers=$BEFORE"

echo "=== COLD spawn register ==="
curl -sk -b "$C" -o "$OUT/cold.html" -w "cold dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code} size=%{size_download}\n" "$URL" | tee "$OUT/cold.txt"
echo "workers_after_cold=$(ps -eo args= | grep -c '[p]hp-fpm: pool' || true)"
grep -c data-pos-register "$OUT/cold.html" || true

echo "=== WARM1 ==="
curl -sk -b "$C" -o /dev/null -w "warm1 dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" "$URL" | tee "$OUT/warm1.txt"
echo "=== WARM2 ==="
curl -sk -b "$C" -o /dev/null -w "warm2 dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" "$URL" | tee "$OUT/warm2.txt"
echo "=== WARM3 ==="
curl -sk -b "$C" -o /dev/null -w "warm3 dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" "$URL" | tee "$OUT/warm3.txt"

echo "=== FPM stage probe warm ==="
for i in 1 2 3; do
  curl -sk -b "$C" -o "$OUT/fpm-$i.json" -w "fpm$i ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code} size=%{size_download}\n" \
    "https://rateb.sa/rateb-erp/public/_pb_fpm_lifecycle.php?company_id=22"
done

echo "=== FPM stage probe after 25s idle (cold spawn) ==="
sleep 25
curl -sk -b "$C" -o "$OUT/fpm-cold.json" -w "fpm_cold ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code} size=%{size_download}\n" \
  "https://rateb.sa/rateb-erp/public/_pb_fpm_lifecycle.php?company_id=22"

# DNS from server
echo "=== dig ==="
dig +time=2 +tries=1 rateb.sa A +stats 2>&1 | awk '/Query time:|ANSWER SECTION/; /^rateb/' | head -10

rm -f "$ROOT/public/_pb_fpm_lifecycle.php"

# summarize
$PHP -r '
$d="/tmp/phase-pb2";
$parse=function($s){$r=[]; foreach(["dns","tcp","tls","ttfb","total"] as $k){ if(preg_match("/$k=([0-9.]+)/",$s,$m)) $r[$k]=(float)$m[1]; } return $r;};
$out=[
  "cold"=>$parse(@file_get_contents("$d/cold.txt")?:""),
  "warm1"=>$parse(@file_get_contents("$d/warm1.txt")?:""),
  "warm2"=>$parse(@file_get_contents("$d/warm2.txt")?:""),
  "warm3"=>$parse(@file_get_contents("$d/warm3.txt")?:""),
  "fpm_bodies"=>[
    @json_decode(@file_get_contents("$d/fpm-1.json")?: "null", true),
    @json_decode(@file_get_contents("$d/fpm-2.json")?: "null", true),
    @json_decode(@file_get_contents("$d/fpm-3.json")?: "null", true),
  ],
  "fpm_cold"=>@json_decode(@file_get_contents("$d/fpm-cold.json")?: "null", true),
  "fpm_cold_raw_head"=>substr(@file_get_contents("$d/fpm-cold.json")?: "",0,200),
];
file_put_contents("$d/summary.json", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
'
echo DONE
