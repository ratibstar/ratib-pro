#!/bin/bash
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp

# FPM pool
echo "=== FPM POOL ==="
ls /usr/local/php83/etc/php-fpm.d/ || true
grep -R "pm\|request\|nice\|cpu\|rlimit\|chdir\|user" /usr/local/php83/etc/php-fpm.d/*.conf 2>/dev/null | head -60 || true
# DirectAdmin domain fpm
find /usr/local/directadmin /etc -name '*rateb*' 2>/dev/null | head -20 || true
find /usr/local/php83/etc -type f 2>/dev/null | head -40

cat > "$ROOT/public/_ae_fpm_why.php" <<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($ROOT);
\Rateb\App\Core\Auth::bootstrapFromSession();
if (!\Rateb\App\Core\Auth::check()) {
    echo json_encode(['ok'=>false,'error'=>'auth']); exit;
}
require_once RATEB_ROOT . '/app/helpers/Request.php';

// Bench A: rateb_app_route x 5000
$t = hrtime(true);
for ($i=0;$i<5000;$i++) { rateb_app_route('hr/employees'); }
$app_route_5000_ms = round((hrtime(true)-$t)/1e6,3);

// Bench B: rateb_erp_mw x 5000
require_once RATEB_ROOT . '/routes/middleware-helpers.php';
$t = hrtime(true);
for ($i=0;$i<5000;$i++) { rateb_erp_mw('hr','','hr'); }
$erp_mw_5000_ms = round((hrtime(true)-$t)/1e6,3);

// Bench C: tokenize ops.php (parse approximation)
$src = file_get_contents(RATEB_ROOT.'/routes/modules/ops.php');
$t = hrtime(true);
token_get_all($src);
$tokenize_ms = round((hrtime(true)-$t)/1e6,3);

// Bench D: file_get + include of ops into fresh router (full execute)
$router = new \Rateb\App\Core\Router();
$t = hrtime(true);
require RATEB_ROOT.'/routes/modules/ops.php';
$ops_require_ms = round((hrtime(true)-$t)/1e6,3);

// Bench E: second require of same file (should be ~0 because already in opcache... wait no opcache, but require once? require will re-exec!)
$router2 = new \Rateb\App\Core\Router();
$t = hrtime(true);
// can't re-require same path easily without rename - measure reading+compile via eval of stripped?
$ops_require2_skip = 'use require again would redefine';

// getrusage
$ru = getrusage();

echo json_encode([
  'ok'=>true,
  'sapi'=>PHP_SAPI,
  'opcache'=>extension_loaded('Zend OPcache'),
  'app_route_5000_ms'=>$app_route_5000_ms,
  'erp_mw_5000_ms'=>$erp_mw_5000_ms,
  'tokenize_ops_ms'=>$tokenize_ms,
  'ops_require_ms'=>$ops_require_ms,
  'route_count'=>$router->routeCount(),
  'src_bytes'=>strlen($src),
  'ru_utime_ms'=> ($ru['ru_utime.tv_sec']*1000)+($ru['ru_utime.tv_usec']/1000),
  'ru_stime_ms'=> ($ru['ru_stime.tv_sec']*1000)+($ru['ru_stime.tv_usec']/1000),
  'company_access_enabled'=> function_exists('rateb_company_access_routes_enabled') ? rateb_company_access_routes_enabled() : null,
], JSON_PRETTY_PRINT);
PHP

# CLI same benches
echo "=== CLI Benches ==="
$PHP <<'PHP'
<?php
$ROOT='/home/admin/domains/rateb.sa/public_html/rateb-erp';
require_once $ROOT.'/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($ROOT);
require_once RATEB_ROOT.'/app/helpers/Request.php';
require_once RATEB_ROOT.'/routes/middleware-helpers.php';
$t=hrtime(true); for($i=0;$i<5000;$i++){ rateb_app_route('hr/employees'); }
$a=round((hrtime(true)-$t)/1e6,3);
$t=hrtime(true); for($i=0;$i<5000;$i++){ rateb_erp_mw('hr','','hr'); }
$b=round((hrtime(true)-$t)/1e6,3);
$src=file_get_contents(RATEB_ROOT.'/routes/modules/ops.php');
$t=hrtime(true); token_get_all($src); $c=round((hrtime(true)-$t)/1e6,3);
$router=new \Rateb\App\Core\Router();
$t=hrtime(true); require RATEB_ROOT.'/routes/modules/ops.php'; $d=round((hrtime(true)-$t)/1e6,3);
echo json_encode(['sapi'=>PHP_SAPI,'app_route_5000_ms'=>$a,'erp_mw_5000_ms'=>$b,'tokenize_ops_ms'=>$c,'ops_require_ms'=>$d,'route_count'=>$router->routeCount()], JSON_PRETTY_PRINT),"\n";
PHP

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/ae_mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ae_mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
R='--resolve rateb.sa:443:167.233.71.107'
echo "=== FPM Benches ==="
curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_fpm_why.php"
echo
rm -f "$ROOT/public/_ae_fpm_why.php"
echo WHY_REMOVED
