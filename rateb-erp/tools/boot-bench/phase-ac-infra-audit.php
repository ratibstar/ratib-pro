<?php
declare(strict_types=1);

/**
 * Phase AC — infrastructure bottleneck audit (production). Evidence only.
 *   /usr/local/php83/bin/php tools/boot-bench/phase-ac-infra-audit.php
 */
$ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
if (!is_dir($ROOT)) {
    $ROOT = dirname(__DIR__, 2);
}
$OUT = $ROOT . '/tools/boot-bench/reports/phase-ac-infra.json';
@mkdir(dirname($OUT), 0775, true);

function sh(string $cmd): string
{
    $o = [];
    exec($cmd . ' 2>&1', $o);

    return implode("\n", $o);
}

function curlTiming(string $url, string $extra = ''): array
{
    $fmt = '%{http_code}|%{time_namelookup}|%{time_connect}|%{time_appconnect}|%{time_starttransfer}|%{time_total}|%{size_download}|%{num_connects}|%{http_version}';
    $hdr = tempnam(sys_get_temp_dir(), 'ach');
    $cmd = 'curl -sk ' . $extra . ' -D ' . escapeshellarg($hdr) . ' -o /dev/null -w ' . escapeshellarg($fmt) . ' ' . escapeshellarg($url);
    $raw = trim(sh($cmd));
    $headers = is_file($hdr) ? (string) file_get_contents($hdr) : '';
    @unlink($hdr);
    $p = explode('|', $raw);
    if (count($p) < 6) {
        return ['raw' => $raw, 'headers' => $headers];
    }
    $dns = (float) $p[1];
    $conn = (float) $p[2];
    $tls = (float) $p[3];
    $ttfb = (float) $p[4];
    $total = (float) $p[5];

    return [
        'raw' => $raw,
        'http_code' => (int) $p[0],
        'dns_ms' => round($dns * 1000, 3),
        'tcp_ms' => round(($conn - $dns) * 1000, 3),
        'tls_ms' => round(($tls - $conn) * 1000, 3),
        'ttfb_ms' => round($ttfb * 1000, 3),
        'total_ms' => round($total * 1000, 3),
        'server_think_ms' => round(($ttfb - $tls) * 1000, 3),
        'size' => (int) $p[6],
        'num_connects' => (int) ($p[7] ?? 0),
        'http_version' => $p[8] ?? '',
        'headers' => $headers,
    ];
}

$resolve = '--resolve rateb.sa:443:127.0.0.1';

// Mint session if possible
$cookie = '';
$mintScripts = [
    $ROOT . '/tools/boot-bench/remote-auth.php',
    '/tmp/rateb_remote_auth.php',
];
foreach ($mintScripts as $ms) {
    if (!is_file($ms)) {
        continue;
    }
    $j = json_decode(trim(sh(PHP_BINARY . ' ' . escapeshellarg($ms) . ' mint')), true);
    if (is_array($j) && !empty($j['session_id'])) {
        $cookie = '-b ' . escapeshellarg(($j['session_name'] ?? 'PHPSESSID') . '=' . $j['session_id']);
        break;
    }
}

$net = [
    'loopback_admin_cold' => curlTiming('https://rateb.sa/rateb-erp/public/admin/', "$resolve $cookie"),
    'loopback_admin_warm' => curlTiming('https://rateb.sa/rateb-erp/public/admin/', "$resolve $cookie"),
    'loopback_admin_warm2' => curlTiming('https://rateb.sa/rateb-erp/public/admin/', "$resolve $cookie"),
    'loopback_login' => curlTiming('https://rateb.sa/rateb-erp/public/login', $resolve),
    'loopback_probe' => curlTiming('https://rateb.sa/rateb-erp/public/connectivity-probe.json', $resolve),
    'loopback_main_css' => curlTiming('https://rateb.sa/rateb-erp/public/assets/css/main.css', $resolve),
    'loopback_fa_css' => curlTiming('https://rateb.sa/rateb-erp/public/assets/vendor/fontawesome/6.5.2/css/all.min.css', $resolve),
    'loopback_bootstrap_css' => curlTiming('https://rateb.sa/rateb-erp/public/assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css', $resolve),
    'loopback_tajawal' => curlTiming('https://rateb.sa/rateb-erp/public/assets/vendor/fonts/tajawal/tajawal.css', $resolve),
    'keepalive_probe_1' => curlTiming('https://rateb.sa/rateb-erp/public/connectivity-probe.json', "$resolve -H 'Connection: keep-alive'"),
    'keepalive_probe_2' => curlTiming('https://rateb.sa/rateb-erp/public/connectivity-probe.json', "$resolve -H 'Connection: keep-alive'"),
    'public_dns_admin' => curlTiming('https://rateb.sa/rateb-erp/public/admin/', $cookie),
];

// compression
$gzHdr = tempnam(sys_get_temp_dir(), 'gz');
$gzSize = trim(sh("curl -sk $resolve -H 'Accept-Encoding: gzip, deflate, br' -D " . escapeshellarg($gzHdr) . " -o /dev/null -w '%{size_download}' https://rateb.sa/rateb-erp/public/assets/css/main.css"));
$gzEnc = '';
foreach (file($gzHdr) ?: [] as $l) {
    if (stripos($l, 'content-encoding:') === 0) {
        $gzEnc = trim($l);
    }
}
@unlink($gzHdr);

// OPcache
$opcache = [
    'cli_extension_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
    'cli_status' => function_exists('opcache_get_status') ? @opcache_get_status(false) : null,
    'php_ini' => php_ini_loaded_file(),
];
$ini = (string) php_ini_loaded_file();
if ($ini && is_readable($ini)) {
    foreach (file($ini) as $i => $line) {
        if (stripos($line, 'opcache') !== false && stripos($line, 'zend_extension') !== false) {
            $opcache['ini_zend_opcache_line'] = trim($line);
            $opcache['ini_line_no'] = $i + 1;
            break;
        }
    }
}
// FPM ping via tiny script — check if FPM has opcache by writing probe
$fpmProbe = $ROOT . '/public/_ac_opcache_probe.php';
file_put_contents($fpmProbe, "<?php header('Content-Type: application/json'); echo json_encode(['opcache_loaded'=>extension_loaded('Zend OPcache')||extension_loaded('opcache'),'status'=>function_exists('opcache_get_status')?@opcache_get_status(false):null,'sapi'=>PHP_SAPI]);\n");
$fpmOpc = curlTiming('https://rateb.sa/rateb-erp/public/_ac_opcache_probe.php', $resolve);
$fpmOpcBody = trim(sh("curl -sk $resolve " . escapeshellarg('https://rateb.sa/rateb-erp/public/_ac_opcache_probe.php')));
@unlink($fpmProbe);

// system
$sys = [
    'nproc' => (int) trim(sh('nproc')),
    'loadavg' => trim(sh('cat /proc/loadavg')),
    'uptime' => trim(sh('uptime')),
    'mem' => trim(sh('free -h')),
    'mem_bytes' => trim(sh('free -b')),
    'swap' => trim(sh('swapon --show || echo none')),
    'disk' => trim(sh('df -h /; df -i /')),
    'iostat' => trim(sh('iostat -x 1 2 2>/dev/null | tail -15 || echo unavailable')),
    'top' => trim(sh('top -bn1 | head -18')),
];

// FPM / proxy processes
$procs = trim(sh("ps auxww | grep -E 'php-fpm|lsphp|httpd|apache|nginx|litespeed|openlitespeed' | grep -v grep | head -50"));
$pool = '';
foreach (glob('/usr/local/php83/etc/php-fpm.d/*.conf') ?: [] as $f) {
    $pool .= "FILE $f\n" . sh("grep -iE 'pm\\.|max_children|start_servers|status_path|listen ' " . escapeshellarg($f) . " | head -40") . "\n";
}
$proxyHdr = trim(sh("curl -skI $resolve https://rateb.sa/ | tr -d '\\r' | head -25"));

// MySQL
$mysql = [];
try {
    require $ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($ROOT);
    $pdo = \Rateb\App\Core\Database::connection();
    $t0 = hrtime(true);
    $pdo->query('SELECT 1')->fetch();
    $mysql['ping_ms'] = round((hrtime(true) - $t0) / 1e6, 3);
    $vars = [];
    $st = $pdo->query('SHOW GLOBAL STATUS WHERE Variable_name IN ("Threads_connected","Threads_running","Questions","Slow_queries","Uptime","Connections","Created_tmp_disk_tables")');
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $vars[$r['Variable_name']] = $r['Value'];
    }
    $mysql['status'] = $vars;
    $g = [];
    $st2 = $pdo->query('SHOW GLOBAL VARIABLES WHERE Variable_name IN ("slow_query_log","long_query_time","slow_query_log_file","max_connections")');
    while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
        $g[$r['Variable_name']] = $r['Value'];
    }
    $mysql['variables'] = $g;
} catch (Throwable $e) {
    $mysql['error'] = $e->getMessage();
}

// assets top size
$assets = trim(sh("find " . escapeshellarg($ROOT . '/public/assets') . " -type f \\( -name '*.js' -o -name '*.css' -o -name '*.woff2' \\) -printf '%s %p\\n' 2>/dev/null | sort -nr | head -20"));

// SW
$swFiles = [];
foreach (['rateb-offline-sw.js', 'pos-sw.js', 'sw.js', 'service-worker.js'] as $sw) {
    $p = $ROOT . '/public/' . $sw;
    if (is_file($p)) {
        $swFiles[$sw] = [
            'bytes' => filesize($p),
            'mtime' => date('c', filemtime($p)),
            'head' => implode("\n", array_slice(file($p) ?: [], 0, 40)),
        ];
    }
}

// access log top slow paths if present
$accessHit = '';
foreach ([
    '/var/log/httpd/domains/rateb.sa.log',
    '/var/log/httpd/rateb.sa.bytes.log',
    '/home/admin/logs/rateb.sa',
    '/usr/local/lsws/logs/access.log',
] as $log) {
    if (is_file($log)) {
        $accessHit = $log;
        break;
    }
}

$report = [
    'phase' => 'AC',
    'measured_at' => gmdate('c'),
    'host' => gethostname(),
    'cookie_minted' => $cookie !== '',
    'network' => $net,
    'compression' => [
        'accept_gzip_br_download_size' => $gzSize,
        'content_encoding_header' => $gzEnc,
    ],
    'opcache' => $opcache,
    'fpm_opcache_probe' => [
        'timing' => $fpmOpc,
        'body' => json_decode($fpmOpcBody, true) ?: $fpmOpcBody,
    ],
    'system' => $sys,
    'processes' => $procs,
    'fpm_pool' => $pool,
    'proxy_headers' => $proxyHdr,
    'mysql' => $mysql,
    'cache_extensions' => [
        'redis' => extension_loaded('redis'),
        'memcached' => extension_loaded('memcached'),
        'apcu' => extension_loaded('apcu'),
    ],
    'top_assets_by_bytes' => $assets,
    'service_workers' => $swFiles,
    'access_log_path' => $accessHit,
];

file_put_contents($OUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$summary = [
    'wrote' => $OUT,
    'admin_cold_ttfb_ms' => $net['loopback_admin_cold']['ttfb_ms'] ?? null,
    'admin_warm_ttfb_ms' => $net['loopback_admin_warm']['ttfb_ms'] ?? null,
    'admin_warm_server_think_ms' => $net['loopback_admin_warm']['server_think_ms'] ?? null,
    'probe_ttfb_ms' => $net['loopback_probe']['ttfb_ms'] ?? null,
    'main_css_ttfb_ms' => $net['loopback_main_css']['ttfb_ms'] ?? null,
    'tls_ms_cold' => $net['loopback_admin_cold']['tls_ms'] ?? null,
    'http_version' => $net['loopback_admin_warm']['http_version'] ?? null,
    'fpm_opcache' => is_array($report['fpm_opcache_probe']['body']) ? $report['fpm_opcache_probe']['body'] : null,
    'compression' => $report['compression'],
    'mysql_ping_ms' => $mysql['ping_ms'] ?? null,
    'loadavg' => $sys['loadavg'],
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
