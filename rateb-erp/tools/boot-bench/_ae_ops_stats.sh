#!/bin/bash
set -eu
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
PHP=/usr/local/php83/bin/php
echo "=== FILE SIZES ==="
wc -l -c "$ROOT/routes/modules/ops.php" "$ROOT/routes/modules/auth.php" "$ROOT/routes/modules/dashboard.php"
ls -la "$ROOT/routes/modules/"
echo "=== OPCACHE ==="
$PHP -r 'echo "loaded=". (extension_loaded("Zend OPcache")||extension_loaded("opcache")?"yes":"no")."\n";'
$PHP -i 2>/dev/null | grep -E '^opcache\.(enable|enable_cli|memory_consumption|validate_timestamps|revalidate_freq|jit)' | head -20
echo "=== ROUTER ADD TIMING (CLI require ops) ==="
$PHP <<'PHP'
<?php
$ROOT='/home/admin/domains/rateb.sa/public_html/rateb-erp';
require_once $ROOT.'/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($ROOT);
$t0=hrtime(true);
require_once RATEB_ROOT.'/app/helpers/Request.php';
$router=new \Rateb\App\Core\Router();
$t1=hrtime(true);
$before=get_included_files();
$nBefore=count($before);
\Rateb\App\Core\RouteModuleLoader::loadForPath($router,'/admin/hr');
$t2=hrtime(true);
$after=get_included_files();
$new=array_values(array_diff($after,$before));
$sizes=[];
foreach($new as $f){$sizes[$f]=@filesize($f)?:0;}
arsort($sizes);
echo json_encode([
  'bootstrap_already'=>true,
  'router_new_ms'=>round(($t1-$t0)/1e6,3),
  'loadForPath_ms'=>round(($t2-$t1)/1e6,3),
  'route_count'=>$router->routeCount(),
  'loaded'=>\Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
  'new_includes'=>count($new),
  'new_include_bytes'=>array_sum($sizes),
  'top_new_includes'=>array_slice($sizes,0,20,true),
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
PHP

echo "=== ISOLATE ops.php REQUIRE ONLY ==="
$PHP <<'PHP'
<?php
$ROOT='/home/admin/domains/rateb.sa/public_html/rateb-erp';
require_once $ROOT.'/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($ROOT);
require_once RATEB_ROOT.'/app/helpers/Request.php';
$router=new \Rateb\App\Core\Router();
// auth module first
$tA=hrtime(true);
require RATEB_ROOT.'/routes/modules/auth.php';
$tB=hrtime(true);
require RATEB_ROOT.'/routes/modules/ops.php';
$tC=hrtime(true);
echo json_encode([
  'auth_ms'=>round(($tB-$tA)/1e6,3),
  'ops_ms'=>round(($tC-$tB)/1e6,3),
  'route_count'=>$router->routeCount(),
  'ops_filesize'=>filesize(RATEB_ROOT.'/routes/modules/ops.php'),
  'ops_lines'=>substr_count(file_get_contents(RATEB_ROOT.'/routes/modules/ops.php'),"\n")+1,
], JSON_PRETTY_PRINT),"\n";
PHP
