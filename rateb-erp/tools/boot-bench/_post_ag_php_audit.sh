#!/bin/bash
# Post-AG PHP lifecycle + SQL audit (read-only probes; temporary public file removed).
set -u
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
REPORT="$ROOT/tools/boot-bench/reports"
mkdir -p "$REPORT" /tmp/post-ag
R='--resolve rateb.sa:443:167.233.71.107'

cat > "$ROOT/public/_post_ag_fpm.php" <<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$path = '/' . trim(str_replace('\\', '/', (string)($_GET['path'] ?? '/admin/hr')), '/');
if ($path !== '/') { $path = rtrim($path, '/') ?: '/'; }
$ROOT = dirname(__DIR__);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public' . $path
  . ((str_contains($path, '/ops/') || str_contains($path, '/hr') || str_contains($path, '/crm') || str_contains($path, '/pos'))
    ? '?company_id=22' : '');
if (str_contains($_SERVER['REQUEST_URI'], 'company_id=')) { $_GET['company_id'] = '22'; }

$t0 = hrtime(true);
$marks = [];
$mark = static function (string $n) use (&$marks, $t0): void {
  $marks[$n] = round((hrtime(true) - $t0) / 1e6, 3);
};

require_once $ROOT . '/app/Core/Bootstrap.php';
$mark('after_bootstrap_require');
\Rateb\App\Core\Bootstrap::init($ROOT);
$mark('after_bootstrap_init');
$pos = $ROOT . '/modules/pos/PosModule.php';
if (is_file($pos)) { require_once $pos; \Rateb\App\Pos\PosModule::init(); }
$mark('after_pos_module');
$off = $ROOT . '/offline/OfflineModule.php';
if (is_file($off)) { require_once $off; \Rateb\App\Offline\OfflineModule::init(); }
$mark('after_offline_module');
\Rateb\App\Core\Auth::bootstrapFromSession();
$mark('after_auth');
if (!\Rateb\App\Core\Auth::check()) {
  echo json_encode(['ok'=>false,'error'=>'auth','marks'=>$marks]); exit;
}
require_once RATEB_ROOT . '/app/helpers/Request.php';
$router = new \Rateb\App\Core\Router();
$selected = \Rateb\App\Core\RouteModuleLoader::selectModuleIds($path);
$mark('after_select');
\Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
$mark('after_routes_load');
$mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
$mwOk = $mw->handle();
$mark('after_middleware');
ob_start();
$router->dispatch('GET', $path);
$html = (string) ob_get_clean();
$mark('after_dispatch');
$total = round((hrtime(true) - $t0) / 1e6, 3);
$prev = 0.0; $durs = [];
foreach ($marks as $n => $abs) { $durs[$n] = round($abs - $prev, 3); $prev = $abs; }
echo json_encode([
  'ok'=>true, 'sapi'=>PHP_SAPI, 'path'=>$path, 'total_ms'=>$total,
  'stage_ms'=>$durs, 'marks_abs_ms'=>$marks,
  'selected'=>$selected, 'loaded'=>\Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
  'route_count'=>$router->routeCount(), 'middleware_ok'=>$mwOk,
  'html_bytes'=>strlen($html),
  'opcache'=>extension_loaded('Zend OPcache')||extension_loaded('opcache'),
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
PHP

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/post-ag/mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/post-ag/mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')

: > /tmp/post-ag/fpm.jsonl
: > /tmp/post-ag/ttfb.txt
for p in /admin /admin/hr /admin/crm /admin/ops/inventory /admin/ops/accounting /admin/ops/purchase-requests /admin/ops/pos; do
  echo "===== FPM $p ====="
  curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_post_ag_fpm.php?path=$p" | tee -a /tmp/post-ag/fpm.jsonl
  echo >> /tmp/post-ag/fpm.jsonl
  curl -sk $R -b "$C" -o /dev/null -w "$p ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
    "https://rateb.sa/rateb-erp/public$p$( [[ "$p" == /admin ]] && echo '' || echo '?company_id=22' )" | tee -a /tmp/post-ag/ttfb.txt
done

# AE SQL/span profiles (CLI — understates FPM but gives SQL attribution)
: > /tmp/post-ag/ae.jsonl
for p in /admin /admin/hr /admin/ops/inventory /admin/ops/accounting /admin/ops/purchase-requests; do
  echo "===== AE CLI $p ====="
  $PHP "$ROOT/tools/boot-bench/phase-ae-ttfb-rootcause.php" "$p" > "/tmp/post-ag/ae-$(echo $p | tr '/' '_').json" 2>/tmp/post-ag/ae.err
  # copy key fields
  $PHP -r '
  $f=$argv[1]; $j=@json_decode(@file_get_contents($f),true);
  if(!$j){fwrite(STDERR,"bad $f\n"); exit(0);}
  $spans=$j["spans"]??$j["report"]["spans"]??[];
  // normalize
  if(isset($j["spans_by_id"])) $spans=$j["spans_by_id"];
  echo json_encode([
    "path"=>$j["path"]??$argv[2],
    "total_ms"=>$j["total_ms"]??$j["wall_ms"]??null,
    "top_spans"=>array_slice(array_values(array_map(function($s){
      return ["id"=>$s["id"]??"","label"=>$s["label"]??"","dur_ms"=>$s["dur_ms"]??0,"self_ms"=>$s["self_ms"]??0,"sql_count"=>$s["sql_count"]??0];
    }, is_array($spans)?$spans:[])), 0, 30),
    "sql_top"=>array_slice($j["sql_top"]??$j["top_sql"]??[],0,20),
    "keys"=>array_keys($j),
  ], JSON_UNESCAPED_SLASHES)."\n";
  ' "/tmp/post-ag/ae-$(echo $p | tr '/' '_').json" "$p" | tee -a /tmp/post-ag/ae.jsonl
done

rm -f "$ROOT/public/_post_ag_fpm.php"
cp /tmp/post-ag/fpm.jsonl "$REPORT/phase-post-ag-fpm.jsonl"
cp /tmp/post-ag/ttfb.txt "$REPORT/phase-post-ag-ttfb.txt"
cp /tmp/post-ag/ae.jsonl "$REPORT/phase-post-ag-ae-summary.jsonl"
# copy full AE accounting for SQL detail
cp /tmp/post-ag/ae-_admin_ops_accounting.json "$REPORT/phase-post-ag-ae-accounting.json" 2>/dev/null || true
cp /tmp/post-ag/ae-_admin_hr.json "$REPORT/phase-post-ag-ae-hr.json" 2>/dev/null || true
echo DONE
