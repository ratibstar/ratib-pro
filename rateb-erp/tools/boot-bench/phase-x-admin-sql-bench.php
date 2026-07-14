<?php
declare(strict_types=1);
/**
 * Phase X before/after evidence for GET /admin/ SQL optimization.
 * Expects optimized code deployed (or SCPed) on production.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

$RATEB_ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
$BEFORE = [
    'cli_html_ms' => 251.172,
    'cli_sql_total' => 236,
    'cli_sql_ms' => 110.741,
    'layout_sql' => 154,
    'company_find_repeats' => 99,
    'show_tables_layout' => 27,
    'information_schema_layout' => 18,
    'http_ttfb_ms' => 573.034,
    'http_ttfb_warm_ms' => 547.581,
];

final class BenchProf
{
    public static float $t0;
    /** @var list<array{dur_ms:float,sql:string,phase:string}> */
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
        self::$sql[] = [
            'dur_ms' => round($dur, 3),
            'sql' => mb_substr(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql, 0, 220),
            'phase' => self::$phase,
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
            BenchProf::log((string) $this->queryString, (hrtime(true) - $t0) / 1e6);
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
            BenchProf::log((string) $query, (hrtime(true) - $t0) / 1e6);
        }
    }
}

BenchProf::$t0 = hrtime(true);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';

require_once $RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($RATEB_ROOT);

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
BenchProf::$on = true;

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
// Reset request memo that may have been warmed during mint (fair /admin recreate)
if (function_exists('rateb_ops_company_request_state_reset')) {
    rateb_ops_company_request_state_reset();
    // Re-adopt without counting as lifecycle (or count it — reset then let lifecycle resolve once)
    rateb_adopt_ops_company_id(22);
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

BenchProf::$sql = []; // measure HTML path only after mint
BenchProf::$phase = 'auth_bootstrap';
$tAuth = hrtime(true);
\Rateb\App\Core\Auth::bootstrapFromSession();
$authMs = (hrtime(true) - $tAuth) / 1e6;

BenchProf::$phase = 'routes';
$tRoutes = hrtime(true);
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
$routesMs = (hrtime(true) - $tRoutes) / 1e6;

BenchProf::$phase = 'middleware';
$mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
$mw->handle();

BenchProf::$phase = 'admin_build_lite';
$tLite = hrtime(true);
$sqlBeforeLite = count(BenchProf::$sql);
$dash = (new \Rateb\App\Services\DashboardService())->adminBuildLite();
$liteMs = (hrtime(true) - $tLite) / 1e6;
$sqlLite = count(BenchProf::$sql) - $sqlBeforeLite;

BenchProf::$phase = 'view_dashboard';
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

BenchProf::$phase = 'layout_main';
$tLay = hrtime(true);
$sqlBeforeLay = count(BenchProf::$sql);
ob_start();
include RATEB_VIEWS_PATH . '/layouts/main.php';
$html = (string) ob_get_clean();
$layMs = (hrtime(true) - $tLay) / 1e6;
$sqlLay = count(BenchProf::$sql) - $sqlBeforeLay;

$htmlMs = $liteMs + $layMs + ((hrtime(true) - $tLite) / 1e6 - $liteMs - $layMs);
// More accurate: from lite start through layout end
$tHtml0 = $tLite;
$htmlGenMs = (hrtime(true) - $tHtml0) / 1e6;

$companyFinds = 0;
$showTables = 0;
$infoSchema = 0;
$showCols = 0;
foreach (BenchProf::$sql as $q) {
    $s = strtolower($q['sql']);
    if (str_contains($s, 'from rateb_companies where id')) {
        $companyFinds++;
    }
    if (str_starts_with($s, 'show tables')) {
        $showTables++;
    }
    if (str_contains($s, 'information_schema')) {
        $infoSchema++;
    }
    if (str_starts_with($s, 'show columns')) {
        $showCols++;
    }
}

$slow = BenchProf::$sql;
usort($slow, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);

$cookieName = session_name();
$cookieVal = session_id();
session_write_close();
file_put_contents('/tmp/rateb-admin.cookie', "rateb.sa\tFALSE\t/\tTRUE\t0\t{$cookieName}\t{$cookieVal}\n");

$http = [];
foreach (['first', 'warm'] as $i => $label) {
    $out = [];
    exec(
        'curl -sk -L -o /tmp/admin-bench.html -w "%{http_code} %{time_starttransfer} %{time_total} %{size_download}" '
        . '-b /tmp/rateb-admin.cookie -c /tmp/rateb-admin.cookie '
        . '--resolve rateb.sa:443:167.233.71.107 '
        . '-H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/" 2>/dev/null',
        $out
    );
    $p = preg_split('/\s+/', trim($out[0] ?? '')) ?: [];
    $http[$label] = [
        'code' => (int) ($p[0] ?? 0),
        'ttfb_ms' => isset($p[1]) ? round(((float) $p[1]) * 1000, 3) : null,
        'total_ms' => isset($p[2]) ? round(((float) $p[2]) * 1000, 3) : null,
        'bytes' => (int) ($p[3] ?? 0),
    ];
}

$after = [
    'cli_sql_total' => count(BenchProf::$sql),
    'cli_sql_ms' => round(array_sum(array_column(BenchProf::$sql, 'dur_ms')), 3),
    'cli_html_ms' => round($htmlGenMs, 3),
    'admin_build_lite_ms' => round($liteMs, 3),
    'admin_build_lite_sql' => $sqlLite,
    'layout_main_ms' => round($layMs, 3),
    'layout_sql' => $sqlLay,
    'company_find_repeats' => $companyFinds,
    'show_tables_total' => $showTables,
    'information_schema_total' => $infoSchema,
    'show_columns_total' => $showCols,
    'html_bytes' => strlen($html),
    'http_ttfb_ms' => $http['first']['ttfb_ms'] ?? null,
    'http_ttfb_warm_ms' => $http['warm']['ttfb_ms'] ?? null,
    'http' => $http,
    'auth_ms' => round($authMs, 3),
    'routes_ms' => round($routesMs, 3),
    'memo_helpers_present' => function_exists('rateb_ops_company_request_state'),
];

$pct = static function ($before, $after) {
    if ($before <= 0) {
        return null;
    }

    return round((($before - $after) / $before) * 100, 1);
};

$report = [
    'ok' => true,
    'phase' => 'X',
    'target' => 'GET /admin/',
    'before' => $BEFORE,
    'after' => $after,
    'delta' => [
        'sql_count_reduction_pct' => $pct($BEFORE['cli_sql_total'], $after['cli_sql_total']),
        'sql_ms_reduction_pct' => $pct($BEFORE['cli_sql_ms'], $after['cli_sql_ms']),
        'html_ms_reduction_pct' => $pct($BEFORE['cli_html_ms'], $after['cli_html_ms']),
        'layout_sql_reduction_pct' => $pct($BEFORE['layout_sql'], $after['layout_sql']),
        'company_find_reduction_pct' => $pct($BEFORE['company_find_repeats'], $after['company_find_repeats']),
        'ttfb_reduction_pct' => $pct($BEFORE['http_ttfb_ms'], (float) ($after['http_ttfb_ms'] ?? 0)),
        'success_sql_50pct' => ($after['cli_sql_total'] <= $BEFORE['cli_sql_total'] * 0.5),
    ],
    'slowest_queries' => array_slice($slow, 0, 15),
];

file_put_contents('/tmp/rateb-admin-phase-x-bench.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
