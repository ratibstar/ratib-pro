#!/bin/bash
# Phase Y part 2 — section timing + CLI with forced OPcache (no FPM/php.ini change)
set -eu
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
PUB=$ROOT/public
OPC=/usr/local/php83/lib/php/extensions/no-debug-non-zts-20230831/opcache.so

cleanup() { rm -f "$PUB/__phase_y_sections.php"; }
trap cleanup EXIT

cat > "$PUB/__phase_y_sections.php" <<'PHP'
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
require $root . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init($root);

/**
 * Duck-typed profiling router (Rateb\App\Core\Router is final — cannot extend).
 * Probe-only; does not alter on-disk routes.
 */
$real = new Rateb\App\Core\Router();
$adds = [];
$t0prof = hrtime(true);
$router = new class($real, $adds, $t0prof) {
    public function __construct(
        private Rateb\App\Core\Router $inner,
        private array &$adds,
        private float $t0,
    ) {}
    public function get(string $pattern, $handler, array $middleware = []): void
    {
        $this->inner->get($pattern, $handler, $middleware);
        $this->adds[] = ['t_ms' => round((hrtime(true) - $this->t0) / 1e6, 3), 'method' => 'GET', 'pattern' => $pattern];
    }
    public function post(string $pattern, $handler, array $middleware = []): void
    {
        $this->inner->post($pattern, $handler, $middleware);
        $this->adds[] = ['t_ms' => round((hrtime(true) - $this->t0) / 1e6, 3), 'method' => 'POST', 'pattern' => $pattern];
    }
    public function put(string $pattern, $handler, array $middleware = []): void
    {
        $this->inner->put($pattern, $handler, $middleware);
        $this->adds[] = ['t_ms' => round((hrtime(true) - $this->t0) / 1e6, 3), 'method' => 'PUT', 'pattern' => $pattern];
    }
    public function patch(string $pattern, $handler, array $middleware = []): void
    {
        $this->inner->patch($pattern, $handler, $middleware);
        $this->adds[] = ['t_ms' => round((hrtime(true) - $this->t0) / 1e6, 3), 'method' => 'PATCH', 'pattern' => $pattern];
    }
    public function delete(string $pattern, $handler, array $middleware = []): void
    {
        $this->inner->delete($pattern, $handler, $middleware);
        $this->adds[] = ['t_ms' => round((hrtime(true) - $this->t0) / 1e6, 3), 'method' => 'DELETE', 'pattern' => $pattern];
    }
};

$path = $root . '/routes/company.php';
$a = hrtime(true);
require $path;
$requireMs = round((hrtime(true) - $a) / 1e6, 3);

$n = count($adds);
$buckets = [];
if ($n > 0) {
    $chunk = max(1, (int) ceil($n / 8));
    for ($i = 0; $i < $n; $i += $chunk) {
        $slice = array_slice($adds, $i, $chunk);
        $t0 = $i === 0 ? 0.0 : (float) $adds[$i - 1]['t_ms'];
        $t1 = (float) $slice[count($slice) - 1]['t_ms'];
        $buckets[] = [
            'routes' => count($slice),
            'from_idx' => $i,
            'to_idx' => $i + count($slice) - 1,
            'start_ms' => $t0,
            'end_ms' => $t1,
            'delta_ms' => round($t1 - $t0, 3),
            'first_pattern' => $slice[0]['pattern'],
            'last_pattern' => $slice[count($slice) - 1]['pattern'],
        ];
    }
}
usort($buckets, static fn($x, $y) => $y['delta_ms'] <=> $x['delta_ms']);

// Slowest individual gaps between consecutive registrations
$gaps = [];
for ($i = 1; $i < $n; $i++) {
    $dt = $adds[$i]['t_ms'] - $adds[$i - 1]['t_ms'];
    if ($dt >= 1.0) {
        $gaps[] = [
            'dt_ms' => round($dt, 3),
            'after' => $adds[$i - 1]['pattern'],
            'before' => $adds[$i]['pattern'],
        ];
    }
}
usort($gaps, static fn($x, $y) => $y['dt_ms'] <=> $x['dt_ms']);

echo json_encode([
    'require_ms' => $requireMs,
    'route_count' => $n,
    'slowest_buckets' => array_slice($buckets, 0, 8),
    'largest_gaps_ms' => array_slice($gaps, 0, 15),
    'first_5' => array_slice($adds, 0, 5),
    'last_5' => array_slice($adds, -5),
    'opcache_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
PHP

echo "=== FPM section profile ==="
curl -sk "https://rateb.sa/rateb-erp/public/__phase_y_sections.php" | tee /tmp/phase-y-sections.json
echo

echo "=== conf.d contents ==="
echo '--- 10-directadmin.ini ---'
cat /usr/local/php83/lib/php.conf.d/10-directadmin.ini
echo '--- 50-webapps.ini ---'
cat /usr/local/php83/lib/php.conf.d/50-webapps.ini

echo "=== opcache.so exists? ==="
ls -la "$OPC"
file "$OPC"

echo "=== CLI company.php WITH forced OPcache (process-local -d, no php.ini write) ==="
# First request = compile into SHM; second should be cache hit within same process if enable_cli
for i in 1 2 3; do
  /usr/local/php83/bin/php \
    -d "zend_extension=$OPC" \
    -d opcache.enable=1 \
    -d opcache.enable_cli=1 \
    -d opcache.validate_timestamps=1 \
    -d opcache.revalidate_freq=0 \
    -r '
    $_SERVER["HTTP_HOST"]="rateb.sa"; $_SERVER["HTTPS"]="on";
    $_SERVER["DOCUMENT_ROOT"]="/home/admin/domains/rateb.sa/public_html";
    define("RATEB_ROOT","/home/admin/domains/rateb.sa/public_html/rateb-erp");
    $loaded = extension_loaded("Zend OPcache") || extension_loaded("opcache");
    $st = $loaded && function_exists("opcache_get_status") ? @opcache_get_status(false) : null;
    require RATEB_ROOT."/app/Core/Bootstrap.php";
    Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    $router = new Rateb\App\Core\Router();
    $path = RATEB_ROOT."/routes/company.php";
    $src = file_get_contents($path);
    $a = hrtime(true); token_get_all($src); $tok = (hrtime(true)-$a)/1e6;
    $a = hrtime(true); require $path; $req = (hrtime(true)-$a)/1e6;
    $cached = null;
    if ($loaded && function_exists("opcache_is_script_cached")) {
      $cached = opcache_is_script_cached($path);
    }
    echo json_encode([
      "run"=>(int)$argv[1],
      "opcache_loaded"=>$loaded,
      "opcache_enabled"=>$st["opcache_enabled"]??null,
      "require_ms"=>round($req,3),
      "token_ms"=>round($tok,3),
      "script_cached"=>$cached,
      "cached_scripts"=>$st["opcache_statistics"]["num_cached_scripts"]??null,
      "hits"=>$st["opcache_statistics"]["hits"]??null,
      "misses"=>$st["opcache_statistics"]["misses"]??null,
    ], JSON_UNESCAPED_SLASHES),"\n";
    ' "$i"
done

# Same process: require twice to see second-hit within one CLI process
echo "=== CLI same-process double require with OPcache ==="
/usr/local/php83/bin/php \
  -d "zend_extension=$OPC" \
  -d opcache.enable=1 \
  -d opcache.enable_cli=1 \
  -r '
  $_SERVER["HTTP_HOST"]="rateb.sa"; $_SERVER["HTTPS"]="on";
  $_SERVER["DOCUMENT_ROOT"]="/home/admin/domains/rateb.sa/public_html";
  define("RATEB_ROOT","/home/admin/domains/rateb.sa/public_html/rateb-erp");
  require RATEB_ROOT."/app/Core/Bootstrap.php";
  Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
  $path = RATEB_ROOT."/routes/company.php";
  // First compile via include into a fresh symbol? require once only works once.
  // Measure compile via opcache_compile_file
  $a=hrtime(true);
  $ok = opcache_compile_file($path);
  $compile1=(hrtime(true)-$a)/1e6;
  $a=hrtime(true);
  $ok2 = opcache_compile_file($path);
  $compile2=(hrtime(true)-$a)/1e6;
  $st=opcache_get_status(true);
  $scripts=$st["scripts"]??[];
  $company=null;
  foreach ($scripts as $k=>$v) {
    if (str_ends_with($k, "/routes/company.php") || str_contains($k, "routes/company.php")) {
      $company=$v; break;
    }
  }
  echo json_encode([
    "opcache_compile_file_1_ms"=>round($compile1,3),
    "opcache_compile_file_2_ms"=>round($compile2,3),
    "compile_ok"=>[$ok,$ok2],
    "company_script"=>$company,
    "num_cached_scripts"=>$st["opcache_statistics"]["num_cached_scripts"]??null,
  ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
  '

echo "=== WITHOUT opcache: opcache_compile_file unavailable baseline token+filesize ==="
/usr/local/php83/bin/php -r '
$path="/home/admin/domains/rateb.sa/public_html/rateb-erp/routes/company.php";
$src=file_get_contents($path);
$a=hrtime(true); token_get_all($src); echo "token_ms=".round((hrtime(true)-$a)/1e6,3)." bytes=".strlen($src)."\n";
'

# Merge into evidence JSON
php -r '
$base=json_decode(file_get_contents("/tmp/phase-y-opcache-evidence.json"), true) ?: [];
$base["sections"]=json_decode(file_get_contents("/tmp/phase-y-sections.json"), true);
$base["conf_d"]=[
  "10"=>@file_get_contents("/usr/local/php83/lib/php.conf.d/10-directadmin.ini"),
  "50"=>@file_get_contents("/usr/local/php83/lib/php.conf.d/50-webapps.ini"),
];
$base["forced_opcache_cli_note"]="Measured with php -d zend_extension=opcache.so only in CLI process; production FPM php.ini NOT modified.";
$base["fpm_after"]="NOT_APPLIED — evidence only, production OPcache remains disabled";
file_put_contents("/tmp/phase-y-opcache-evidence.json", json_encode($base, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "updated evidence json\n";
'
