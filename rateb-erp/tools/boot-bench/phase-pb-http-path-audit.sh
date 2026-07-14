#!/bin/bash
# Phase PB — HTTP path lifecycle audit (READ ONLY). Temporary probe cleaned up.
set -u
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
REPORT=/tmp/phase-pb-http-path.json
R='--resolve rateb.sa:443:127.0.0.1'
URL='https://rateb.sa/rateb-erp/public/admin/ops/pos/register?company_id=22'
OUTDIR=/tmp/phase-pb
mkdir -p "$OUTDIR"

# Ensure remote-auth available
if [ ! -f /tmp/remote-auth-pa.php ] && [ -f "$ROOT/tools/boot-bench/remote-auth.php" ]; then
  cp "$ROOT/tools/boot-bench/remote-auth.php" /tmp/remote-auth-pa.php
fi
if [ ! -f /tmp/remote-auth-pa.php ]; then
  echo '{"ok":false,"error":"remote-auth missing"}'
  exit 1
fi

$PHP /tmp/remote-auth-pa.php mintpos > "$OUTDIR/mint.json"
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/phase-pb/mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
echo "COOKIE=$C" > "$OUTDIR/cookie.txt"

# --- curl network lifecycle (loopback resolve = no DNS from client; measures TLS+server) ---
: > "$OUTDIR/curl-loopback.txt"
for i in 1 2 3 4 5; do
  curl -sk $R -b "$C" -o "$OUTDIR/body-$i.html" -D "$OUTDIR/hdr-$i.txt" -w \
    "run=$i dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} pretransfer=%{time_pretransfer} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download} code=%{http_code} speed=%{speed_download}\n" \
    "$URL" | tee -a "$OUTDIR/curl-loopback.txt"
done

# --- curl via public IP (same machine to public) ---
: > "$OUTDIR/curl-public.txt"
for i in 1 2 3; do
  curl -sk -b "$C" -o /dev/null -w \
    "run=$i dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} pretransfer=%{time_pretransfer} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download} code=%{http_code}\n" \
    "$URL" | tee -a "$OUTDIR/curl-public.txt"
done

# static asset baseline
curl -sk $R -o /dev/null -w "static_probe dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
  "https://rateb.sa/rateb-erp/public/connectivity-probe.json" | tee "$OUTDIR/static.txt"

# headers from last
grep -iE "^(HTTP/|server:|x-powered|cache|content-encoding|content-length|server-timing|x-rateb|connection:|transfer-encoding)" "$OUTDIR/hdr-5.txt" > "$OUTDIR/headers.txt" || true
HAS_REG=$(grep -c 'data-pos-register' "$OUTDIR/body-5.html" || true)
echo "has_register=$HAS_REG bytes=$(wc -c < "$OUTDIR/body-5.html")" | tee "$OUTDIR/body-meta.txt"

# --- FPM instrumented probe (temporary public file; deleted after) ---
cat > "$ROOT/public/_pb_fpm_lifecycle.php" <<'PHPEND'
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Rateb-Phase: PB');

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

$opcache_cli = [
    'sapi' => PHP_SAPI,
    'opcache_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
    'opcache_enabled' => null,
    'opcache_status' => null,
];
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    if (is_array($st)) {
        $opcache_cli['opcache_enabled'] = $st['opcache_enabled'] ?? null;
        $opcache_cli['opcache_status'] = [
            'cached_scripts' => $st['opcache_statistics']['num_cached_scripts'] ?? null,
            'hits' => $st['opcache_statistics']['hits'] ?? null,
            'misses' => $st['opcache_statistics']['misses'] ?? null,
            'memory_used' => $st['memory_usage']['used_memory'] ?? null,
            'memory_free' => $st['memory_usage']['free_memory'] ?? null,
        ];
    }
}

$sessionBefore = [
    'status' => session_status(),
    'name' => session_name(),
    'id' => session_id() ?: null,
    'save_path' => ini_get('session.save_path'),
    'save_handler' => ini_get('session.save_handler'),
];

$mark('probe_start');
require_once $ROOT . '/app/Core/Bootstrap.php';
$mark('after_bootstrap_require');

$tBoot = hrtime(true);
\Rateb\App\Core\Bootstrap::init($ROOT);
$bootMs = round((hrtime(true) - $tBoot) / 1e6, 3);
$mark('after_bootstrap_init');

$sessionAfter = [
    'status' => session_status(),
    'name' => session_name(),
    'id' => session_id() ?: null,
    'cookie_present' => isset($_COOKIE[session_name()]),
];

$pos = $ROOT . '/modules/pos/PosModule.php';
if (is_file($pos)) {
    require_once $pos;
    \Rateb\App\Pos\PosModule::init();
}
$mark('after_pos_module');

$off = $ROOT . '/offline/OfflineModule.php';
if (is_file($off)) {
    require_once $off;
    \Rateb\App\Offline\OfflineModule::init();
}
$mark('after_offline_module');

$tAuth = hrtime(true);
\Rateb\App\Core\Auth::bootstrapFromSession();
$authMs = round((hrtime(true) - $tAuth) / 1e6, 3);
$mark('after_auth');

if (!\Rateb\App\Core\Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'auth', 'marks' => $marks], JSON_PRETTY_PRINT);
    exit;
}

require_once RATEB_ROOT . '/app/helpers/Request.php';
$router = new \Rateb\App\Core\Router();
$tSel = hrtime(true);
$selected = \Rateb\App\Core\RouteModuleLoader::selectModuleIds($path);
$selMs = round((hrtime(true) - $tSel) / 1e6, 3);
$mark('after_route_select');

$tRoutes = hrtime(true);
\Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
$routesMs = round((hrtime(true) - $tRoutes) / 1e6, 3);
$mark('after_routes_load');

$tMw = hrtime(true);
$mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
$mwOk = $mw->handle();
$mwMs = round((hrtime(true) - $tMw) / 1e6, 3);
$mark('after_middleware');

ob_start();
$tDisp = hrtime(true);
$router->dispatch('GET', $path);
$html = (string) ob_get_clean();
$dispMs = round((hrtime(true) - $tDisp) / 1e6, 3);
$mark('after_dispatch');

$total = round((hrtime(true) - $t0) / 1e6, 3);
$prev = 0.0;
$durs = [];
foreach ($marks as $n => $abs) {
    $durs[$n] = round($abs - $prev, 3);
    $prev = $abs;
}

$included = get_included_files();
$includeBytes = 0;
foreach ($included as $f) {
    $includeBytes += is_file($f) ? (int) @filesize($f) : 0;
}

echo json_encode([
    'ok' => true,
    'path' => $path,
    'total_ms' => $total,
    'bootstrap_init_ms' => $bootMs,
    'auth_ms' => $authMs,
    'route_select_ms' => $selMs,
    'routes_load_ms' => $routesMs,
    'middleware_ms' => $mwMs,
    'dispatch_ms' => $dispMs,
    'stage_ms' => $durs,
    'marks_abs_ms' => $marks,
    'html_bytes' => strlen($html),
    'has_register' => (bool) preg_match('/data-pos-register/i', $html),
    'route_count' => $router->routeCount(),
    'selected' => $selected,
    'loaded' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
    'middleware_ok' => $mwOk,
    'opcache' => $opcache_cli,
    'session_before' => $sessionBefore,
    'session_after' => $sessionAfter,
    'included_files' => count($included),
    'include_bytes' => $includeBytes,
    'memory_peak' => memory_get_peak_usage(true),
    'ini' => [
        'realpath_cache_size' => ini_get('realpath_cache_size'),
        'realpath_cache_ttl' => ini_get('realpath_cache_ttl'),
        'session.save_handler' => ini_get('session.save_handler'),
        'session.save_path' => ini_get('session.save_path'),
        'zlib.output_compression' => ini_get('zlib.output_compression'),
        'output_buffering' => ini_get('output_buffering'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHPEND

: > "$OUTDIR/fpm-curl.txt"
for i in 1 2 3 4 5; do
  curl -sk $R -b "$C" -o "$OUTDIR/fpm-$i.json" -w \
    "run=$i dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code} size=%{size_download}\n" \
    "https://rateb.sa/rateb-erp/public/_pb_fpm_lifecycle.php?company_id=22" | tee -a "$OUTDIR/fpm-curl.txt"
done

# --- Session lock probe ---
cat > /tmp/_pb_session_lock.php <<'PHPS'
<?php
declare(strict_types=1);
$ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
require_once $ROOT . '/app/Core/Bootstrap.php';
$t0 = hrtime(true);
\Rateb\App\Core\Bootstrap::init($ROOT);
$afterInit = (hrtime(true) - $t0) / 1e6;
$sid = session_id();
$path = ini_get('session.save_path') ?: '/tmp';
$file = rtrim($path, '/') . '/sess_' . $sid;
$tLock = hrtime(true);
$fp = @fopen($file, 'c+');
$got = false;
if ($fp) {
    $got = flock($fp, LOCK_EX | LOCK_NB);
    if ($got) {
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}
$lockProbe = (hrtime(true) - $tLock) / 1e6;
echo json_encode([
    'bootstrap_init_ms' => round($afterInit, 3),
    'session_id' => $sid,
    'session_file' => $file,
    'file_exists' => is_file($file),
    'file_size' => is_file($file) ? filesize($file) : null,
    'lock_nb_acquired' => $got,
    'lock_probe_ms' => round($lockProbe, 3),
    'save_handler' => ini_get('session.save_handler'),
    'save_path' => ini_get('session.save_path'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
PHPS
$PHP /tmp/_pb_session_lock.php > "$OUTDIR/session-lock.json" 2>&1 || true

# --- Apache/FPM config snapshot ---
{
  echo "=== SERVER PROCESS ==="
  ps aux | grep -iE 'httpd|apache|litespeed|php-fpm' | grep -v grep | head -40
  echo "=== FPM CONF ==="
  for f in /usr/local/directadmin/data/users/admin/php/php-fpm83.conf /usr/local/php83/etc/php-fpm.d/*.conf; do
    [ -f "$f" ] && echo "--- $f ---" && grep -E '^(pm|pm\.|listen|security)' "$f" 2>/dev/null | head -40
  done
  echo "=== OPCACHE INI ==="
  $PHP -i 2>/dev/null | grep -iE 'opcache\.(enable|memory|revalidate|validate)' | head -30
  echo "=== APCU ==="
  $PHP -m 2>/dev/null | grep -iE 'apcu|redis|opcache' || true
  echo "=== WORKERS ==="
  echo "fpm_workers=$(ps -eo args= | grep -c '[p]hp-fpm' || true)"
} > "$OUTDIR/infra.txt" 2>&1

# Clean temporary public probe
rm -f "$ROOT/public/_pb_fpm_lifecycle.php"

# Build consolidated JSON via PHP
$PHP -r '
$d="/tmp/phase-pb";
$parseCurl=function($file){
  $rows=[];
  foreach(file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
    if(!preg_match("/run=(\d+)/",$line,$m)) continue;
    $r=["raw"=>$line];
    foreach(["dns","tcp","tls","pretransfer","ttfb","total","size","code"] as $k){
      if(preg_match("/".$k."=([0-9.]+)/",$line,$mm)) $r[$k]=(float)$mm[1];
    }
    $rows[]=$r;
  }
  return $rows;
};
$fpmBodies=[];
for($i=1;$i<=5;$i++){
  $p="$d/fpm-$i.json";
  if(is_file($p)) $fpmBodies[]=json_decode(file_get_contents($p),true);
}
$avg=function($rows,$k){
  $vals=array_values(array_filter(array_map(fn($r)=>$r[$k]??null,$rows),fn($v)=>$v!==null));
  return $vals? round(array_sum($vals)/count($vals),4) : null;
};
$loop=$parseCurl("$d/curl-loopback.txt");
$pub=$parseCurl("$d/curl-public.txt");
$fpmCurl=$parseCurl("$d/fpm-curl.txt");
$out=[
  "phase"=>"PB",
  "measured_at"=>gmdate("c"),
  "cookie"=>trim(@file_get_contents("$d/cookie.txt")?:""),
  "has_register_body"=>trim(@file_get_contents("$d/body-meta.txt")?:""),
  "headers"=>@file_get_contents("$d/headers.txt")?:null,
  "curl_loopback"=>["runs"=>$loop,"avg"=>["dns"=>$avg($loop,"dns"),"tcp"=>$avg($loop,"tcp"),"tls"=>$avg($loop,"tls"),"ttfb"=>$avg($loop,"ttfb"),"total"=>$avg($loop,"total")]],
  "curl_public"=>["runs"=>$pub,"avg"=>["dns"=>$avg($pub,"dns"),"tcp"=>$avg($pub,"tcp"),"tls"=>$avg($pub,"tls"),"ttfb"=>$avg($pub,"ttfb"),"total"=>$avg($pub,"total")]],
  "fpm_probe_http"=>["runs"=>$fpmCurl,"avg"=>["ttfb"=>$avg($fpmCurl,"ttfb"),"total"=>$avg($fpmCurl,"total")]],
  "fpm_probe_bodies"=>$fpmBodies,
  "session_lock"=>@json_decode(@file_get_contents("$d/session-lock.json")?: "null", true),
  "static"=>trim(@file_get_contents("$d/static.txt")?:""),
  "infra_excerpt"=>substr(@file_get_contents("$d/infra.txt")?: "",0,8000),
];
file_put_contents("/tmp/phase-pb-http-path.json", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo "WROTE /tmp/phase-pb-http-path.json\n";
echo json_encode([
  "loopback_ttfb_avg"=>$out["curl_loopback"]["avg"]["ttfb"],
  "public_ttfb_avg"=>$out["curl_public"]["avg"]["ttfb"],
  "fpm_http_ttfb_avg"=>$out["fpm_probe_http"]["avg"]["ttfb"],
  "fpm_internal_ms"=>$fpmBodies[0]["total_ms"]??null,
  "opcache"=>$fpmBodies[0]["opcache"]??null,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
'

echo DONE
