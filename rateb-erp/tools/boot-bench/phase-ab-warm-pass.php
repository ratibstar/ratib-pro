<?php
declare(strict_types=1);

/**
 * Phase AB warm-pass — same process, schema statics already satisfied.
 * Run after / alongside cold profile to find steady-state bottleneck.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

$RATEB_ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';

require_once $RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($RATEB_ROOT);

$pdo = \Rateb\App\Core\Database::connection();
$st = $pdo->prepare('SELECT * FROM rateb_users WHERE email = ? LIMIT 1');
$st->execute(['admin@rateb.sa']);
$user = $st->fetch(PDO::FETCH_ASSOC) ?: $pdo->query('SELECT * FROM rateb_users WHERE id=26')->fetch(PDO::FETCH_ASSOC);
\Rateb\App\Core\Auth::loginUser($user);
if (function_exists('rateb_adopt_ops_company_id')) {
    rateb_adopt_ops_company_id(22);
}
\Rateb\App\Core\SessionManager::set('rateb_branch_schema_ok', date('Y-m-d'));
// Simulate warm schema flag that ApprovalOversight sets after first hit
\Rateb\App\Core\SessionManager::set('rateb_acct_submit_schema_ok', '1');
// Warm oversight session cache path: leave empty to force compute WITHOUT schema ALTERs
\Rateb\App\Core\SessionManager::forget('rateb_oversight_menu_counts');

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

/** @var list<array{name:string,ms:float,sql_n:int}> $stages */
$stages = [];
$sqlN = 0;
$sqlLog = [];

// Lightweight SQL counter via attribute — reuse connection query logging via wrapping not available;
// count by monkeypatching is heavy — time stages only and sample key functions.

$mark = static function (string $name, callable $fn) use (&$stages): mixed {
    $t0 = hrtime(true);
    $r = $fn();
    $stages[] = ['name' => $name, 'ms' => round((hrtime(true) - $t0) / 1e6, 3)];
    return $r;
};

$total0 = hrtime(true);

$mark('Bootstrap::init_ALREADY_DONE_note', static fn() => null);

$router = $mark('RouteModuleLoader::loadForPath', static function () {
    $router = new \Rateb\App\Core\Router();
    \Rateb\App\Core\RouteModuleLoader::loadForPath($router, '/admin');
    return $router;
});

$mark('ErpAuthMiddleware::handle', static function () {
    $mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
    if (!$mw->handle()) {
        throw new RuntimeException('mw fail');
    }
});

// Install SQL tracer for warm path detail
final class WarmStmt extends PDOStatement
{
    public static array $sql = [];
    protected function __construct()
    {
    }
    public function execute($params = null): bool
    {
        $t0 = hrtime(true);
        try {
            return parent::execute($params);
        } finally {
            self::$sql[] = ['ms' => (hrtime(true) - $t0) / 1e6, 'q' => (string) $this->queryString, 'bt' => self::caller()];
        }
    }
    private static function caller(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20) as $f) {
            $c = (string) ($f['class'] ?? '');
            if (str_starts_with($c, 'Warm') || str_contains((string) ($f['file'] ?? ''), 'phase-ab-warm')) {
                continue;
            }
            $fn = (string) ($f['function'] ?? '');
            $file = (string) ($f['file'] ?? '');
            $line = (int) ($f['line'] ?? 0);
            if ($c !== '') {
                return basename($file) . ":{$line} {$c}::{$fn}";
            }
            return basename($file) . ":{$line} {$fn}";
        }
        return '?';
    }
}
final class WarmPdo extends PDO
{
    public function __construct($dsn, $u = null, $p = null, $o = null)
    {
        parent::__construct($dsn, $u, $p, $o ?: []);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [WarmStmt::class, []]);
    }
    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$a)
    {
        $t0 = hrtime(true);
        try {
            return $fetchMode === null && $a === [] ? parent::query($query) : parent::query($query, $fetchMode, ...$a);
        } finally {
            WarmStmt::$sql[] = ['ms' => (hrtime(true) - $t0) / 1e6, 'q' => (string) $query, 'bt' => 'query'];
        }
    }
    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        $t0 = hrtime(true);
        try {
            return parent::exec($statement);
        } finally {
            WarmStmt::$sql[] = ['ms' => (hrtime(true) - $t0) / 1e6, 'q' => (string) $statement, 'bt' => 'exec'];
        }
    }
}

$host = (string) RATEB_DB_HOST;
$port = (int) RATEB_DB_PORT;
$userDb = (string) RATEB_DB_USER;
$pass = (string) RATEB_DB_PASS;
$db = (string) RATEB_DB_NAME;
try {
    $row = \Rateb\App\Core\Database::connection()->query('SELECT DATABASE()')->fetch(PDO::FETCH_NUM);
    if (!empty($row[0])) {
        $db = (string) $row[0];
    }
} catch (Throwable $e) {
}
$ref = new ReflectionClass(\Rateb\App\Core\Database::class);
$prop = $ref->getProperty('pdo');
$prop->setAccessible(true);
$prop->setValue(null, new WarmPdo(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db), $userDb, $pass));

// Prefire schema statics as warm FPM worker would after first request
(new \Rateb\App\Services\AccountingService())->ensureApprovalSubmitColumns();
WarmStmt::$sql = []; // discard schema prime

$dash = $mark('DashboardService::adminBuildLite', static function () {
    return (new \Rateb\App\Services\DashboardService())->adminBuildLite();
});

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

$mark('rateb_oversight_menu_counts', static function () {
    return function_exists('rateb_oversight_menu_counts') ? rateb_oversight_menu_counts() : [];
});
$mark('rateb_cms_new_leads_count', static function () {
    return function_exists('rateb_cms_new_leads_count') ? rateb_cms_new_leads_count() : 0;
});

$layoutMs = $mark('layouts/main.php include', static function () use ($pageContent) {
    ob_start();
    include RATEB_VIEWS_PATH . '/layouts/main.php';
    return (string) ob_get_clean();
});

$totalMs = round((hrtime(true) - $total0) / 1e6, 3);

// Fresh process bootstrap cost measured separately in child
$bootCmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg(
    '$_SERVER["HTTP_HOST"]="rateb.sa";$_SERVER["HTTPS"]="on";$r="' . $RATEB_ROOT . '";$t=hrtime(true);'
    . 'require_once $r."/app/Core/Bootstrap.php";Rateb\App\Core\Bootstrap::init($r);'
    . 'echo json_encode(["ms"=>round((hrtime(true)-$t)/1e6,3),"mem"=>memory_get_peak_usage(true)]);'
);
$bootJson = shell_exec($bootCmd);
$boot = is_string($bootJson) ? json_decode(trim($bootJson), true) : null;

usort($stages, static fn($a, $b) => $b['ms'] <=> $a['ms']);
$sql = WarmStmt::$sql;
usort($sql, static fn($a, $b) => $b['ms'] <=> $a['ms']);

// Reconstruct request-equivalent total = fresh bootstrap + post-boot warm path
$postBootMs = $totalMs; // excludes bootstrap (already done before timer for some) — fix:
// We started timer AFTER bootstrap. So report both.

$out = [
    'mode' => 'warm_post_boot_same_process',
    'post_boot_wall_ms' => $totalMs,
    'fresh_bootstrap_ms' => $boot['ms'] ?? null,
    'reconstructed_full_request_ms' => isset($boot['ms']) ? round($boot['ms'] + $totalMs, 3) : null,
    'route_count' => $router->routeCount(),
    'modules' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
    'stages_sorted' => $stages,
    'top_sql' => array_slice(array_map(static fn($q) => [
        'ms' => round($q['ms'], 3),
        'bt' => $q['bt'],
        'sql' => mb_substr(preg_replace('/\s+/', ' ', $q['q']) ?? $q['q'], 0, 200),
    ], $sql), 0, 15),
    'sql_count' => count($sql),
    'sql_ms' => round(array_sum(array_column($sql, 'ms')), 3),
    'html_bytes' => strlen((string) $layoutMs),
];

// Biggest among post-boot stages
$biggest = $stages[0] ?? null;
// Biggest overall reconstructed: compare fresh bootstrap vs biggest stage
$candidates = [];
if (isset($boot['ms'])) {
    $candidates[] = [
        'file' => $RATEB_ROOT . '/app/Core/Bootstrap.php',
        'class' => 'Rateb\\App\\Core\\Bootstrap',
        'function' => 'init',
        'line' => 0,
        'ms' => $boot['ms'],
        'pct_of_full' => isset($out['reconstructed_full_request_ms']) && $out['reconstructed_full_request_ms'] > 0
            ? round(100 * $boot['ms'] / $out['reconstructed_full_request_ms'], 2) : null,
        'basis' => 'fresh_process_Bootstrap::init',
    ];
}
if ($biggest) {
    $map = [
        'DashboardService::adminBuildLite' => [
            'file' => $RATEB_ROOT . '/app/services/DashboardService.php',
            'class' => 'Rateb\\App\\Services\\DashboardService',
            'function' => 'adminBuildLite',
            'line' => 21,
        ],
        'layouts/main.php include' => [
            'file' => $RATEB_ROOT . '/views/layouts/main.php',
            'class' => '',
            'function' => 'include',
            'line' => 1,
        ],
        'rateb_oversight_menu_counts' => [
            'file' => $RATEB_ROOT . '/config/app.php',
            'class' => '',
            'function' => 'rateb_oversight_menu_counts',
            'line' => 2610,
        ],
        'RouteModuleLoader::loadForPath' => [
            'file' => $RATEB_ROOT . '/app/Core/RouteModuleLoader.php',
            'class' => 'Rateb\\App\\Core\\RouteModuleLoader',
            'function' => 'loadForPath',
            'line' => 0,
        ],
    ];
    $sym = $map[$biggest['name']] ?? [
        'file' => '',
        'class' => '',
        'function' => $biggest['name'],
        'line' => 0,
    ];
    // resolve Bootstrap line
    if (($sym['function'] ?? '') === 'init' && str_contains($sym['file'], 'Bootstrap.php')) {
        $src = (string) file_get_contents($sym['file']);
        if (preg_match('/public static function init\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
            $sym['line'] = substr_count(substr($src, 0, $m[0][1]), "\n") + 1;
        }
    }
    $candidates[] = [
        'file' => $sym['file'],
        'class' => $sym['class'],
        'function' => $sym['function'],
        'line' => $sym['line'],
        'ms' => $biggest['ms'],
        'pct_of_full' => isset($out['reconstructed_full_request_ms']) && $out['reconstructed_full_request_ms'] > 0
            ? round(100 * $biggest['ms'] / $out['reconstructed_full_request_ms'], 2) : null,
        'basis' => 'warm_stage:' . $biggest['name'],
    ];
}
usort($candidates, static fn($a, $b) => $b['ms'] <=> $a['ms']);
$out['single_biggest_bottleneck'] = $candidates[0] ?? null;
$out['candidates'] = $candidates;

$dir = $RATEB_ROOT . '/tools/boot-bench/reports';
file_put_contents($dir . '/phase-ab-warm-pass.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode([
    'reconstructed_full_request_ms' => $out['reconstructed_full_request_ms'],
    'fresh_bootstrap_ms' => $out['fresh_bootstrap_ms'],
    'post_boot_wall_ms' => $out['post_boot_wall_ms'],
    'top_stages' => array_slice($stages, 0, 8),
    'sql_ms' => $out['sql_ms'],
    'sql_count' => $out['sql_count'],
    'top_sql' => array_slice($out['top_sql'], 0, 5),
    'bottleneck' => $out['single_biggest_bottleneck'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
