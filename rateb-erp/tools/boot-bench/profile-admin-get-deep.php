<?php
declare(strict_types=1);

/**
 * Deeper layout SQL attribution + HTTP TTFB calibration for GET /admin/
 * Evidence only.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$RATEB_ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';

final class DeepProf
{
    public static float $t0;
    /** @var list<array{t_ms:float,dur_ms:float,sql:string,stack:string,fn:string}> */
    public static array $sql = [];
    public static bool $on = false;
    public static string $phase = '';

    public static function ms(): float
    {
        return (hrtime(true) - self::$t0) / 1e6;
    }

    public static function log(string $sql, float $dur): void
    {
        if (!self::$on) {
            return;
        }
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 22);
        $frames = [];
        $fn = 'unknown';
        foreach ($bt as $frame) {
            $file = (string) ($frame['file'] ?? '');
            if ($file === '' || str_contains($file, 'profile-admin-get-deep.php')) {
                continue;
            }
            $cls = (string) ($frame['class'] ?? '');
            $func = (string) ($frame['function'] ?? '');
            $base = basename($file);
            $line = (int) ($frame['line'] ?? 0);
            $label = $cls !== '' ? "{$cls}::{$func}" : $func;
            $frames[] = "{$base}:{$line} {$label}";
            if ($fn === 'unknown' && !str_contains($label, 'RatebProfile') && !str_contains($label, 'PDO')) {
                $fn = $label;
            }
        }
        self::$sql[] = [
            't_ms' => round(self::ms(), 3),
            'dur_ms' => round($dur, 3),
            'phase' => self::$phase,
            'sql' => mb_substr(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql, 0, 240),
            'fn' => $fn,
            'stack' => implode(' ← ', array_slice($frames, 0, 8)),
        ];
    }
}

final class RatebProfileStmt extends PDOStatement
{
    protected function __construct()
    {
    }

    public function execute($params = null): bool
    {
        $t0 = hrtime(true);
        try {
            return parent::execute($params);
        } finally {
            DeepProf::log((string) $this->queryString, (hrtime(true) - $t0) / 1e6);
        }
    }
}

final class RatebProfilePdo extends PDO
{
    public function __construct(string $dsn, ?string $user = null, ?string $pass = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $pass, $options ?: []);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RatebProfileStmt::class, []]);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetchModeArgs)
    {
        $t0 = hrtime(true);
        try {
            if ($fetchMode === null && $fetchModeArgs === []) {
                return parent::query($query);
            }
            return parent::query($query, $fetchMode, ...$fetchModeArgs);
        } finally {
            DeepProf::log((string) $query, (hrtime(true) - $t0) / 1e6);
        }
    }
}

DeepProf::$t0 = hrtime(true);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_NAME'] = 'rateb.sa';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once $RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($RATEB_ROOT);

// Install tracing PDO
$ref = new ReflectionClass(\Rateb\App\Core\Database::class);
$prop = $ref->getProperty('pdo');
$prop->setAccessible(true);
$host = (string) RATEB_DB_HOST;
$port = (int) RATEB_DB_PORT;
$user = (string) RATEB_DB_USER;
$pass = (string) RATEB_DB_PASS;
$db = (string) RATEB_DB_NAME;
try {
    $ex = $prop->getValue(null) ?: \Rateb\App\Core\Database::connection();
    $row = $ex->query('SELECT DATABASE()')->fetch(PDO::FETCH_NUM);
    if (!empty($row[0])) {
        $db = (string) $row[0];
    }
} catch (Throwable $e) {
}
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
$prop->setValue(null, new RatebProfilePdo($dsn, $user, $pass));
DeepProf::$on = true;

$pdo = \Rateb\App\Core\Database::connection();
$st = $pdo->prepare('SELECT * FROM rateb_users WHERE email = ? LIMIT 1');
$st->execute(['admin@rateb.sa']);
$u = $st->fetch(PDO::FETCH_ASSOC);
\Rateb\App\Core\Auth::loginUser($u);
if (function_exists('rateb_adopt_ops_company_id')) {
    rateb_adopt_ops_company_id(22);
}
\Rateb\App\Core\SessionManager::set('rateb_branch_schema_ok', date('Y-m-d'));
\Rateb\App\Core\SessionManager::forget('rateb_oversight_menu_counts');
foreach (array_keys($_SESSION ?? []) as $k) {
    if (is_string($k) && str_starts_with($k, 'rateb_ops_nav_counts_')) {
        \Rateb\App\Core\SessionManager::forget($k);
    }
}

$posModule = $RATEB_ROOT . '/modules/pos/PosModule.php';
if (is_file($posModule)) {
    require_once $posModule;
    \Rateb\App\Pos\PosModule::init();
}
$offlineModule = $RATEB_ROOT . '/offline/OfflineModule.php';
if (is_file($offlineModule)) {
    require_once $offlineModule;
    \Rateb\App\Offline\OfflineModule::init();
}
\Rateb\App\Core\Auth::bootstrapFromSession();

$router = new \Rateb\App\Core\Router();
require $RATEB_ROOT . '/routes/web.php';
require $RATEB_ROOT . '/routes/marketing.php';
require $RATEB_ROOT . '/routes/cms.php';
require $RATEB_ROOT . '/routes/company.php';
require $RATEB_ROOT . '/routes/api.php';
require $RATEB_ROOT . '/modules/pos/routes/pos.php';
if (is_file($RATEB_ROOT . '/modules/pos/routes/pos-v2.php')) {
    require $RATEB_ROOT . '/modules/pos/routes/pos-v2.php';
}

$mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
$mw->handle();

DeepProf::$phase = 'admin_build_lite';
$tLite0 = hrtime(true);
$dash = (new \Rateb\App\Services\DashboardService())->adminBuildLite();
$liteMs = (hrtime(true) - $tLite0) / 1e6;
$sqlLite = count(DeepProf::$sql);

DeepProf::$phase = 'layout_main';
$viewData = [
    'title' => __('dashboard'),
    'dash' => $dash,
    'metrics' => $dash['metrics'],
    'charts' => $dash['charts'],
    'csrf' => \Rateb\App\Core\Csrf::token(),
    'dashboardChartsUrl' => rateb_url('admin/api/dashboard-charts'),
];
extract($viewData, EXTR_SKIP);
ob_start();
include RATEB_VIEWS_PATH . '/admin/dashboard.php';
$pageContent = (string) ob_get_clean();
$tLay0 = hrtime(true);
$sqlBeforeLay = count(DeepProf::$sql);
ob_start();
include RATEB_VIEWS_PATH . '/layouts/main.php';
$html = (string) ob_get_clean();
$layMs = (hrtime(true) - $tLay0) / 1e6;
$sqlLay = count(DeepProf::$sql) - $sqlBeforeLay;

// Aggregate layout company queries by top app frame
$byFn = [];
$companyStacks = [];
foreach (DeepProf::$sql as $q) {
    if ($q['phase'] !== 'layout_main') {
        continue;
    }
    $fn = $q['fn'];
    if (!isset($byFn[$fn])) {
        $byFn[$fn] = ['n' => 0, 'ms' => 0.0, 'sql' => $q['sql']];
    }
    $byFn[$fn]['n']++;
    $byFn[$fn]['ms'] += $q['dur_ms'];
    if (str_contains($q['sql'], 'rateb_companies WHERE id')) {
        $companyStacks[$q['stack']] = ($companyStacks[$q['stack']] ?? 0) + 1;
    }
}
uasort($byFn, static fn($a, $b) => $b['ms'] <=> $a['ms']);
arsort($companyStacks);

// Mint cookie + real HTTP TTFB (loopback)
$cookieName = session_name();
$cookieVal = session_id();
session_write_close();

$http = [];
$url = 'https://127.0.0.1/rateb-erp/public/admin';
// Try public URL via curl from server
$cmd = sprintf(
    'curl -sk -o /tmp/rateb-admin-http-body.html -w "%%{http_code} %%{time_namelookup} %%{time_connect} %%{time_appconnect} %%{time_pretransfer} %%{time_starttransfer} %%{time_total} %%{size_download}" '
    . '-H %s -H %s -b %s %s',
    escapeshellarg('Host: rateb.sa'),
    escapeshellarg('Accept: text/html'),
    escapeshellarg($cookieName . '=' . $cookieVal),
    escapeshellarg('https://rateb.sa/rateb-erp/public/admin')
);
$out = [];
exec($cmd . ' 2>/dev/null', $out, $code);
$line = $out[0] ?? '';
$parts = preg_split('/\s+/', trim($line)) ?: [];
$http = [
    'curl_exit' => $code,
    'raw' => $line,
    'http_code' => (int) ($parts[0] ?? 0),
    'time_namelookup_ms' => isset($parts[1]) ? round(((float) $parts[1]) * 1000, 3) : null,
    'time_connect_ms' => isset($parts[2]) ? round(((float) $parts[2]) * 1000, 3) : null,
    'time_appconnect_ms' => isset($parts[3]) ? round(((float) $parts[3]) * 1000, 3) : null,
    'time_pretransfer_ms' => isset($parts[4]) ? round(((float) $parts[4]) * 1000, 3) : null,
    'ttfb_ms' => isset($parts[5]) ? round(((float) $parts[5]) * 1000, 3) : null,
    'total_ms' => isset($parts[6]) ? round(((float) $parts[6]) * 1000, 3) : null,
    'bytes' => (int) ($parts[7] ?? 0),
    'cookie' => $cookieName,
];

// Cold-ish second HTTP hit vs warm
$out2 = [];
exec($cmd . ' 2>/dev/null', $out2, $code2);
$line2 = $out2[0] ?? '';
$p2 = preg_split('/\s+/', trim($line2)) ?: [];
$http2 = [
    'http_code' => (int) ($p2[0] ?? 0),
    'ttfb_ms' => isset($p2[5]) ? round(((float) $p2[5]) * 1000, 3) : null,
    'total_ms' => isset($p2[6]) ? round(((float) $p2[6]) * 1000, 3) : null,
];

$topCompany = [];
$i = 0;
foreach ($companyStacks as $stack => $n) {
    if ($i++ >= 8) {
        break;
    }
    $topCompany[] = ['n' => $n, 'stack' => $stack];
}

$topFn = [];
$i = 0;
foreach ($byFn as $fn => $v) {
    if ($i++ >= 20) {
        break;
    }
    $topFn[] = ['fn' => $fn, 'n' => $v['n'], 'ms' => round($v['ms'], 3), 'sample' => $v['sql']];
}

$report = [
    'ok' => true,
    'cli_admin_build_lite_ms' => round($liteMs, 3),
    'cli_admin_build_lite_sql' => $sqlLite,
    'cli_layout_main_ms' => round($layMs, 3),
    'cli_layout_main_sql' => $sqlLay,
    'cli_html_bytes' => strlen($html),
    'layout_sql_by_fn' => $topFn,
    'company_find_stacks_in_layout' => $topCompany,
    'http_get_admin_first' => $http,
    'http_get_admin_second' => $http2,
    'note' => 'HTTP uses same PHP session cookie minted in this process; TTFB is production FPM path',
];

file_put_contents('/tmp/rateb-admin-profile-deep.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
