#!/bin/bash
set -u
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
R='--resolve rateb.sa:443:167.233.71.107'

echo "==== pos_v2_all.log ===="
tail -60 /tmp/ag-cert/pos_v2_all.log

echo "==== enterprise summary ===="
$PHP -r '$j=json_decode(file_get_contents("/tmp/ag-cert/enterprise-test.json"),true); echo "passed=".$j["passed"]." failed=".$j["failed"]." total=".$j["total"]."\n"; foreach(($j["suites"]??[]) as $n=>$s){ foreach($s["tests"] as $t){ if(empty($t["passed"])) echo "FAIL $n: ".$t["name"]." ".$t["reason"]."\n"; }}'

cat > "$ROOT/public/_ae_probe2.php" <<'ENDPHP'
<?php
header('Content-Type: application/json');
$ROOT = dirname(__DIR__);
try {
  require_once $ROOT . '/app/Core/Bootstrap.php';
  \Rateb\App\Core\Bootstrap::init($ROOT);
  \Rateb\App\Core\Auth::bootstrapFromSession();
  if (!\Rateb\App\Core\Auth::check()) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
  require_once RATEB_ROOT . '/app/helpers/Request.php';
  $router = new \Rateb\App\Core\Router();
  \Rateb\App\Core\RouteModuleLoader::loadForPath($router, '/admin/hr');
  $ref = new ReflectionClass($router);
  $prop = $ref->getProperty('routes');
  $prop->setAccessible(true);
  $routes = $prop->getValue($router);
  $lines = [];
  foreach ($routes as $r) {
    $h = $r['handler'];
    if (is_array($h)) {
      $hs = (is_object($h[0]) ? get_class($h[0]) : (string)$h[0]) . '::' . (string)$h[1];
    } elseif ($h instanceof Closure) {
      $hs = 'Closure';
    } else {
      $hs = 'callable';
    }
    $mw = [];
    foreach ($r['middleware'] as $m) {
      $mw[] = is_array($m) ? ((string)$m[0] . ':' . (string)($m[1] ?? '')) : (string)$m;
    }
    sort($mw);
    $lines[] = $r['method'] . "\t" . $r['pattern'] . "\t" . $hs . "\t" . implode(',', $mw);
  }
  sort($lines);
  echo json_encode([
    'ok' => true,
    'count' => count($routes),
    'sha256' => hash('sha256', implode("\n", $lines)),
    'pre_ag' => 'bd4081989eca0724497836e9b5dc7bec38e46e21b7429149e2401764ba4b32a8',
    'sha_match' => hash('sha256', implode("\n", $lines)) === 'bd4081989eca0724497836e9b5dc7bec38e46e21b7429149e2401764ba4b32a8',
    'hr' => rateb_app_route('hr'),
    'inventory' => rateb_app_route('inventory'),
    'users' => rateb_app_route('users'),
    'crm' => rateb_app_route('crm'),
    'pos' => rateb_app_route('pos'),
    'legacy_company' => $router->hasMatch('GET', '/company'),
    'legacy_accounting' => $router->hasMatch('GET', '/accounting'),
    'select_hr' => \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin/hr'),
    'select_admin' => \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin'),
    'select_pos' => \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/pos'),
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
}
ENDPHP

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/ag-cert/mint2.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ag-cert/mint2.json"),true); echo $j["session_name"]."=".$j["session_id"];')
echo "==== route probe2 ===="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_probe2.php" | tee /tmp/ag-cert/probe2.json
echo
echo "==== CRM status/title ===="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/admin/crm?company_id=22" -o /tmp/ag-cert/crm.html -w "code=%{http_code} ttfb=%{time_starttransfer}\n"
grep -oE '<title>[^<]+</title>' /tmp/ag-cert/crm.html | head -1
grep -iE 'error|Exception|Fatal|تعذ' /tmp/ag-cert/crm.html | head -5
echo "==== recruitment ===="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/admin/recruitment?company_id=22" -o /tmp/ag-cert/rec.html -w "code=%{http_code}\n"
grep -oE '<title>[^<]+</title>' /tmp/ag-cert/rec.html | head -1
echo "==== POS paths ===="
for p in /pos /pos/ /admin/ops/pos /admin/ops/pos?company_id=22; do
  curl -sk $R -b "$C" -o /dev/null -w "$p %{http_code}\n" "https://rateb.sa/rateb-erp/public$p"
done
rm -f "$ROOT/public/_ae_probe2.php"

# What tests exist on server?
echo "==== tests present on server ===="
ls "$ROOT/tests" 2>/dev/null | head
ls "$ROOT/offline/tests" 2>/dev/null | head
ls "$ROOT/modules/pos/tests" 2>/dev/null | head
