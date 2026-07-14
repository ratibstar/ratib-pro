#!/bin/bash
# Phase Y — OPcache & company.php evidence (read-only product code).
set -eu

ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
PUB=$ROOT/public
REPORT=/tmp/phase-y-opcache-evidence.json
OUT=/tmp/phase-y-report.txt

cleanup() {
  rm -f "$PUB/__phase_y_opcache.php" "$PUB/__phase_y_company_profile.php" 2>/dev/null || true
}
trap cleanup EXIT

# --- FPM probe: opcache + ini ---
cat > "$PUB/__phase_y_opcache.php" <<'PHP'
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$ext = get_loaded_extensions();
sort($ext);
$status = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
$config = function_exists('opcache_get_configuration') ? @opcache_get_configuration() : null;
$iniFiles = [];
if (function_exists('php_ini_loaded_file')) {
    $iniFiles['loaded'] = php_ini_loaded_file();
}
if (function_exists('php_ini_scanned_files')) {
    $iniFiles['scanned'] = php_ini_scanned_files();
}
echo json_encode([
    'sapi' => PHP_SAPI,
    'php_version' => PHP_VERSION,
    'opcache_extension_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
    'opcache_get_status_exists' => function_exists('opcache_get_status'),
    'opcache_get_configuration_exists' => function_exists('opcache_get_configuration'),
    'status' => $status,
    'configuration' => $config,
    'ini' => [
        'opcache.enable' => ini_get('opcache.enable'),
        'opcache.enable_cli' => ini_get('opcache.enable_cli'),
        'opcache.validate_timestamps' => ini_get('opcache.validate_timestamps'),
        'opcache.revalidate_freq' => ini_get('opcache.revalidate_freq'),
        'opcache.max_accelerated_files' => ini_get('opcache.max_accelerated_files'),
        'opcache.memory_consumption' => ini_get('opcache.memory_consumption'),
        'opcache.interned_strings_buffer' => ini_get('opcache.interned_strings_buffer'),
        'opcache.file_cache' => ini_get('opcache.file_cache'),
        'opcache.file_cache_only' => ini_get('opcache.file_cache_only'),
        'opcache.blacklist_filename' => ini_get('opcache.blacklist_filename'),
        'realpath_cache_size' => ini_get('realpath_cache_size'),
        'realpath_cache_ttl' => ini_get('realpath_cache_ttl'),
    ],
    'ini_files' => $iniFiles,
    'extensions_matching' => array_values(array_filter($ext, static function ($e) {
        return (bool) preg_match('/opcache|Zend|apcu|redis/i', $e);
    })),
    'extension_count' => count($ext),
    'open_basedir' => ini_get('open_basedir'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

# --- FPM probe: company.php structural + timed profile ---
cat > "$PUB/__phase_y_company_profile.php" <<'PHP'
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$path = $root . '/routes/company.php';
$src = (string) file_get_contents($path);

$struct = [
    'bytes' => strlen($src),
    'lines' => substr_count($src, "\n") + 1,
    'require_include_count' => preg_match_all('/\b(require|include)(_once)?\b/i', $src),
    'function_declarations' => preg_match_all('/^\s*function\s+\w+/m', $src),
    'closures_fn' => preg_match_all('/\b(?:static\s+)?fn\s*\(/', $src),
    'closures_function' => preg_match_all('/\bfunction\s*\(/', $src),
    'router_get' => preg_match_all('/\$router->get\s*\(/', $src),
    'router_post' => preg_match_all('/\$router->post\s*\(/', $src),
    'router_put' => preg_match_all('/\$router->put\s*\(/', $src),
    'router_patch' => preg_match_all('/\$router->patch\s*\(/', $src),
    'router_delete' => preg_match_all('/\$router->delete\s*\(/', $src),
    'rateb_erp_mw_calls' => preg_match_all('/rateb_erp_mw\s*\(/', $src),
    'app_path_calls' => preg_match_all('/\$app\s*\(/', $src),
    'use_statements' => preg_match_all('/^use\s+/m', $src),
];
$struct['route_registrations'] =
    $struct['router_get'] + $struct['router_post'] + $struct['router_put']
    + $struct['router_patch'] + $struct['router_delete'];
$struct['closures_total'] = $struct['closures_fn'] + $struct['closures_function'];

$t0 = hrtime(true);
$mark = static function (string $n) use (&$marks, $t0): void {
    $marks[$n] = round((hrtime(true) - $t0) / 1e6, 3);
};
$marks = [];
$mark('start');

require $root . '/app/Core/Bootstrap.php';
$mark('after_require_bootstrap');
Rateb\App\Core\Bootstrap::init($root);
$mark('after_bootstrap_init');

$incBefore = get_included_files();
$router = new Rateb\App\Core\Router();
$mark('after_router_new');

$a = hrtime(true);
$tokenMs = null;
$tokStart = hrtime(true);
token_get_all($src);
$tokenMs = round((hrtime(true) - $tokStart) / 1e6, 3);

$reqStart = hrtime(true);
require $path;
$requireMs = round((hrtime(true) - $reqStart) / 1e6, 3);
$mark('after_company_require');

$incAfter = get_included_files();
$newIncludes = array_values(array_diff($incAfter, $incBefore));

// Section timing via regex split is approximate — timed re-parse of logical blocks by line ranges
$lines = explode("\n", $src);
$sections = [
    'use_imports' => [1, 137],
    'middleware_helpers_require' => [138, 141],
    'early_redirects_and_aliases' => [142, 206],
    'core_ops_routes' => [207, 400],
    'hr_recruitment_crm' => [401, 700],
    'projects_eam_mid' => [701, 1000],
    'accounting_control_tail' => [1001, 1155],
    'offline_and_access' => [1156, count($lines)],
];

echo json_encode([
    'sapi' => PHP_SAPI,
    'opcache_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
    'structure' => $struct,
    'token_get_all_ms' => $tokenMs,
    'require_company_ms' => $requireMs,
    'marks_ms' => $marks,
    'includes_before' => count($incBefore),
    'includes_after' => count($incAfter),
    'new_includes_count' => count($newIncludes),
    'new_includes' => array_map('basename', $newIncludes),
    'new_includes_full' => $newIncludes,
    'section_line_ranges' => $sections,
    'wall_ms' => round((hrtime(true) - $t0) / 1e6, 3),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

echo "=== CLI OPcache / ini ===" | tee "$OUT"
/usr/local/php83/bin/php -r '
$ext=get_loaded_extensions(); sort($ext);
echo json_encode([
  "sapi"=>PHP_SAPI,
  "php_version"=>PHP_VERSION,
  "binary"=>PHP_BINARY,
  "opcache_extension_loaded"=>extension_loaded("Zend OPcache")||extension_loaded("opcache"),
  "opcache_get_status_exists"=>function_exists("opcache_get_status"),
  "ini_loaded"=>php_ini_loaded_file(),
  "ini_scanned"=>php_ini_scanned_files(),
  "ini"=>[
    "opcache.enable"=>ini_get("opcache.enable"),
    "opcache.enable_cli"=>ini_get("opcache.enable_cli"),
  ],
  "extensions_matching"=>array_values(array_filter($ext, fn($e)=>preg_match("/opcache|Zend|apcu|redis/i",$e))),
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
' | tee -a "$OUT"

echo "=== php.ini zend_extension / opcache lines ===" | tee -a "$OUT"
grep -nE 'zend_extension|\[opcache\]|^[\s;]*opcache\.' /usr/local/php83/lib/php.ini | head -80 | tee -a "$OUT"

echo "=== scanned conf.d ===" | tee -a "$OUT"
ls -la /usr/local/php83/lib/php.conf.d 2>/dev/null | tee -a "$OUT" || true
ls -la /usr/local/php83/etc/php 2>/dev/null | head | tee -a "$OUT" || true
find /usr/local/php83 -name '*opcache*' 2>/dev/null | head -40 | tee -a "$OUT"

echo "=== FPM pool php_admin / php_value opcache ===" | tee -a "$OUT"
grep -rniE 'opcache' /usr/local/php83/etc/php-fpm.conf /usr/local/php83/etc/php-fpm.d /usr/local/directadmin/data/users/admin/php 2>/dev/null | head -40 | tee -a "$OUT" || true
cat /usr/local/directadmin/data/users/admin/php/php-fpm83.conf 2>/dev/null | tee -a "$OUT"

echo "=== FPM HTTP opcache probe ===" | tee -a "$OUT"
curl -sk "https://rateb.sa/rateb-erp/public/__phase_y_opcache.php" | tee /tmp/phase-y-fpm-opcache.json | tee -a "$OUT"

echo "=== FPM company profile x3 ===" | tee -a "$OUT"
for i in 1 2 3; do
  curl -sk "https://rateb.sa/rateb-erp/public/__phase_y_company_profile.php" > /tmp/phase-y-company-$i.json
  php -r '$j=json_decode(file_get_contents("/tmp/phase-y-company-".$argv[1].".json"),true); echo "run".$argv[1]." require=".($j["require_company_ms"]??"?")." token=".($j["token_get_all_ms"]??"?")." wall=".($j["wall_ms"]??"?")." new_inc=".($j["new_includes_count"]??"?")." routes=".($j["structure"]["route_registrations"]??"?")."\n";' "$i" | tee -a "$OUT"
done

echo "=== CLI company require x3 ===" | tee -a "$OUT"
php -r '
$_SERVER["HTTP_HOST"]="rateb.sa"; $_SERVER["HTTPS"]="on";
$_SERVER["DOCUMENT_ROOT"]="/home/admin/domains/rateb.sa/public_html";
define("RATEB_ROOT","/home/admin/domains/rateb.sa/public_html/rateb-erp");
require RATEB_ROOT."/app/Core/Bootstrap.php";
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
$path=RATEB_ROOT."/routes/company.php";
$src=file_get_contents($path);
$a=hrtime(true); token_get_all($src); $tok=(hrtime(true)-$a)/1e6;
for($i=1;$i<=3;$i++){
  $router=new Rateb\App\Core\Router();
  $a=hrtime(true);
  // cannot re-require same file — simulate by include once measured separately
}
echo "cli_token_ms=".round($tok,3)."\n";
' | tee -a "$OUT"

# dedicated CLI require (fresh process each time)
for i in 1 2 3; do
  /usr/local/php83/bin/php -r '
  $_SERVER["HTTP_HOST"]="rateb.sa"; $_SERVER["HTTPS"]="on";
  $_SERVER["DOCUMENT_ROOT"]="/home/admin/domains/rateb.sa/public_html";
  define("RATEB_ROOT","/home/admin/domains/rateb.sa/public_html/rateb-erp");
  require RATEB_ROOT."/app/Core/Bootstrap.php";
  Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
  $router=new Rateb\App\Core\Router();
  $path=RATEB_ROOT."/routes/company.php";
  $src=file_get_contents($path);
  $a=hrtime(true); token_get_all($src); $tok=(hrtime(true)-$a)/1e6;
  $a=hrtime(true); require $path; $req=(hrtime(true)-$a)/1e6;
  echo "cli_run require=".round($req,3)." token=".round($tok,3)."\n";
  ' | tee -a "$OUT"
done

echo "=== admin TTFB warm x3 ===" | tee -a "$OUT"
php /tmp/mint-admin-cookie.php >/dev/null 2>&1 || true
for i in 1 2 3; do
  curl -sk -o /dev/null -w "admin_$i ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
    -b /tmp/rateb-admin.cookie "https://rateb.sa/rateb-erp/public/admin/" | tee -a "$OUT"
done

echo "=== assemble JSON report ==="
php -r '
$fpm=json_decode(@file_get_contents("/tmp/phase-y-fpm-opcache.json"), true);
$co=json_decode(@file_get_contents("/tmp/phase-y-company-1.json"), true);
$co2=json_decode(@file_get_contents("/tmp/phase-y-company-2.json"), true);
$co3=json_decode(@file_get_contents("/tmp/phase-y-company-3.json"), true);
$ini=file_get_contents("/usr/local/php83/lib/php.ini");
$zendLine=null; $zendRaw=null;
foreach (explode("\n",$ini) as $n=>$line) {
  if (preg_match("/zend_extension\s*=\s*opcache/i", $line) || preg_match("/;zend_extension=opcache/", $line)) {
    $zendLine=$n+1; $zendRaw=trim($line); break;
  }
}
// also find any active zend_extension=opcache without comment
$active=[];
foreach (explode("\n",$ini) as $n=>$line) {
  if (preg_match("/^\s*zend_extension\s*=\s*.*opcache/i", $line)) $active[]=["line"=>$n+1,"raw"=>trim($line)];
  if (preg_match("/^\s*;\s*zend_extension\s*=\s*.*opcache/i", $line)) $active[]=["line"=>$n+1,"raw"=>trim($line),"commented"=>true];
}
$report=[
  "phase"=>"Y",
  "generated_at"=>gmdate("c"),
  "hypothesis"=>"PHP-FPM recompiles company.php every request because OPcache disabled/ineffective",
  "cli"=>[
    "ini_loaded"=>php_ini_loaded_file(),
    "opcache_extension_loaded"=>extension_loaded("Zend OPcache")||extension_loaded("opcache"),
    "sapi"=>PHP_SAPI,
  ],
  "fpm"=>$fpm,
  "why_disabled"=>[
    "php_ini"=>"/usr/local/php83/lib/php.ini",
    "zend_extension_opcache_lines"=>$active,
    "note"=>"If zend_extension=opcache is commented (;zend_extension=opcache), the Zend OPcache module is never loaded for CLI or FPM sharing this ini.",
  ],
  "company_profile_runs"=>[$co,$co2,$co3],
  "same_ini_cli_fpm"=> ($fpm["ini_files"]["loaded"]??null) === php_ini_loaded_file(),
];
file_put_contents("/tmp/phase-y-opcache-evidence.json", json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "wrote /tmp/phase-y-opcache-evidence.json\n";
echo json_encode([
  "opcache_fpm"=>$fpm["opcache_extension_loaded"]??null,
  "opcache_cli"=>extension_loaded("Zend OPcache")||extension_loaded("opcache"),
  "same_ini"=>$report["same_ini_cli_fpm"],
  "require_ms"=>[$co["require_company_ms"]??null,$co2["require_company_ms"]??null,$co3["require_company_ms"]??null],
  "routes"=>$co["structure"]["route_registrations"]??null,
  "closures"=>$co["structure"]["closures_total"]??null,
  "new_includes"=>$co["new_includes_count"]??null,
], JSON_PRETTY_PRINT),"\n";
'
