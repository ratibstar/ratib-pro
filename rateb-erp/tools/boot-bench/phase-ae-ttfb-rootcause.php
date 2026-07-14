<?php
declare(strict_types=1);

/**
 * Phase AE — Real production TTFB root cause (READ ONLY).
 * Usage:
 *   php tools/boot-bench/phase-ae-ttfb-rootcause.php /admin/ops/inventory
 *   php tools/boot-bench/phase-ae-ttfb-rootcause.php /admin/hr
 *   php tools/boot-bench/phase-ae-ttfb-rootcause.php /admin
 *
 * Mimics public/index.php with authenticated session. Does not modify app code.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('memory_limit', '512M');

$pathArg = $argv[1] ?? '/admin/ops/inventory';
$pathArg = '/' . trim(str_replace('\\', '/', $pathArg), '/');
if ($pathArg !== '/') {
    $pathArg = rtrim($pathArg, '/') ?: '/';
}

$RATEB_ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
if (!is_dir($RATEB_ROOT)) {
    $RATEB_ROOT = dirname(__DIR__, 2);
}

final class AeProf
{
    public static float $t0;
    /** @var array<string,array<string,mixed>> */
    public static array $spans = [];
    /** @var list<string> */
    public static array $stack = [];
    public static string $phase = 'boot';
    /** @var list<array<string,mixed>> */
    public static array $sql = [];
    /** @var list<array{path:string,bytes:int,ms:float,phase:string}> */
    public static array $includes = [];
    /** @var array<string,int> */
    public static array $includeCounts = [];
    public static bool $sqlOn = false;
    public static int $filesAtPhaseStart = 0;

    public static function ms(): float
    {
        return (hrtime(true) - self::$t0) / 1e6;
    }

    public static function begin(string $id, string $label): void
    {
        $parent = self::$stack !== [] ? self::$stack[count(self::$stack) - 1] : null;
        self::$stack[] = $id;
        self::$phase = $id;
        self::$filesAtPhaseStart = count(get_included_files());
        self::$spans[$id] = [
            'id' => $id,
            'parent' => $parent,
            'label' => $label,
            'start_ms' => self::ms(),
            'end_ms' => 0.0,
            'dur_ms' => 0.0,
            'self_ms' => 0.0,
            'mem_start' => memory_get_usage(true),
            'mem_end' => 0,
            'sql_before' => count(self::$sql),
            'sql_count' => 0,
            'includes_before' => self::$filesAtPhaseStart,
            'includes_delta' => 0,
            'include_bytes' => 0,
            'children' => [],
        ];
        if ($parent !== null) {
            self::$spans[$parent]['children'][] = $id;
        }
    }

    public static function end(string $id): void
    {
        if (!isset(self::$spans[$id])) {
            return;
        }
        while (self::$stack !== [] && self::$stack[count(self::$stack) - 1] !== $id) {
            array_pop(self::$stack);
        }
        if (self::$stack !== [] && self::$stack[count(self::$stack) - 1] === $id) {
            array_pop(self::$stack);
        }
        $end = self::ms();
        $s = &self::$spans[$id];
        $s['end_ms'] = $end;
        $s['dur_ms'] = round($end - $s['start_ms'], 3);
        $s['mem_end'] = memory_get_usage(true);
        $s['sql_count'] = count(self::$sql) - $s['sql_before'];
        $files = get_included_files();
        $delta = array_slice($files, $s['includes_before']);
        $s['includes_delta'] = count($delta);
        $bytes = 0;
        foreach ($delta as $f) {
            $sz = is_file($f) ? (int) @filesize($f) : 0;
            $bytes += $sz;
            self::$includes[] = [
                'path' => $f,
                'bytes' => $sz,
                'ms' => 0.0,
                'phase' => $id,
            ];
            $base = basename($f);
            self::$includeCounts[$base] = (self::$includeCounts[$base] ?? 0) + 1;
        }
        $s['include_bytes'] = $bytes;
        self::$phase = self::$stack !== [] ? self::$stack[count(self::$stack) - 1] : 'idle';
    }

    public static function finalizeSelf(): void
    {
        foreach (self::$spans as $id => &$s) {
            $child = 0.0;
            foreach ($s['children'] as $cid) {
                $child += self::$spans[$cid]['dur_ms'] ?? 0.0;
            }
            $s['self_ms'] = round(max(0.0, $s['dur_ms'] - $child), 3);
        }
        unset($s);
    }

    public static function logSql(string $sql, float $dur): void
    {
        if (!self::$sqlOn) {
            return;
        }
        $file = '';
        $line = 0;
        $class = '';
        $fn = '';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 24) as $frame) {
            $f = (string) ($frame['file'] ?? '');
            if ($f === '' || str_contains($f, 'phase-ae-ttfb-rootcause.php')) {
                continue;
            }
            $c = (string) ($frame['class'] ?? '');
            if (str_starts_with($c, 'AeProfile')) {
                continue;
            }
            $file = $f;
            $line = (int) ($frame['line'] ?? 0);
            $class = $c;
            $fn = (string) ($frame['function'] ?? '');
            break;
        }
        self::$sql[] = [
            'dur_ms' => round($dur, 3),
            'sql' => mb_substr(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql, 0, 240),
            'file' => $file,
            'line' => $line,
            'class' => $class,
            'function' => $fn,
            'phase' => self::$phase,
        ];
    }
}

final class AeProfileStmt extends PDOStatement
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
            AeProf::logSql((string) $this->queryString, (hrtime(true) - $t0) / 1e6);
        }
    }
}

final class AeProfilePdo extends PDO
{
    public function __construct(string $dsn, ?string $user = null, ?string $pass = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $pass, $options ?: []);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [AeProfileStmt::class, []]);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetchModeArgs)
    {
        $t0 = hrtime(true);
        try {
            return $fetchMode === null && $fetchModeArgs === []
                ? parent::query($query)
                : parent::query($query, $fetchMode, ...$fetchModeArgs);
        } finally {
            AeProf::logSql((string) $query, (hrtime(true) - $t0) / 1e6);
        }
    }
}

AeProf::$t0 = hrtime(true);

$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_NAME'] = 'rateb.sa';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public' . ($pathArg === '/' ? '/' : $pathArg)
    . (str_contains($pathArg, '/ops/') || str_contains($pathArg, '/hr') || str_contains($pathArg, '/crm')
        ? (str_contains($pathArg, '?') ? '&' : '?') . 'company_id=22'
        : '');
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET = [];
if (str_contains($_SERVER['REQUEST_URI'], 'company_id=22')) {
    $_GET['company_id'] = '22';
}

try {
    AeProf::begin('request_to_first_byte', 'Complete PHP work until response body start');

    AeProf::begin('bootstrap_require', 'require Bootstrap.php');
    require_once $RATEB_ROOT . '/app/Core/Bootstrap.php';
    AeProf::end('bootstrap_require');

    AeProf::begin('bootstrap_init', 'Bootstrap::init (autoload+eager requires+config+session)');
    $files0 = count(get_included_files());
    \Rateb\App\Core\Bootstrap::init($RATEB_ROOT);
    AeProf::end('bootstrap_init');
    AeProf::$spans['bootstrap_init']['meta'] = [
        'includes_after' => count(get_included_files()),
        'includes_delta' => count(get_included_files()) - $files0,
        'opcache_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
        'realpath_cache_size' => realpath_cache_size(),
        'realpath_cache_ttl' => (int) ini_get('realpath_cache_ttl'),
        'include_path' => get_include_path(),
    ];

    // Tracing PDO
    AeProf::begin('db_trace_install', 'Install tracing PDO');
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
    $prop->setValue(null, new AeProfilePdo($dsn, $user, $pass));
    AeProf::$sqlOn = true;
    AeProf::end('db_trace_install');

    AeProf::begin('session_auth_mint', 'Auth::loginUser + adopt company 22 (warm session simulation)');
    $pdo = \Rateb\App\Core\Database::connection();
    $st = $pdo->prepare('SELECT * FROM rateb_users WHERE email = ? LIMIT 1');
    $st->execute(['admin@rateb.sa']);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: $pdo->query('SELECT * FROM rateb_users WHERE id=26')->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        throw new RuntimeException('admin user missing');
    }
    \Rateb\App\Core\Auth::loginUser($u);
    if (function_exists('rateb_adopt_ops_company_id')) {
        rateb_adopt_ops_company_id(22);
    }
    \Rateb\App\Core\SessionManager::set('rateb_branch_schema_ok', date('Y-m-d'));
    \Rateb\App\Core\SessionManager::set('rateb_acct_submit_schema_ok', '1');
    AeProf::end('session_auth_mint');

    AeProf::begin('pos_module', 'PosModule::init');
    $posModule = $RATEB_ROOT . '/modules/pos/PosModule.php';
    if (is_file($posModule)) {
        require_once $posModule;
        \Rateb\App\Pos\PosModule::init();
    }
    AeProf::end('pos_module');

    AeProf::begin('offline_module', 'OfflineModule::init');
    $offlineModule = $RATEB_ROOT . '/offline/OfflineModule.php';
    if (is_file($offlineModule)) {
        require_once $offlineModule;
        \Rateb\App\Offline\OfflineModule::init();
    }
    AeProf::end('offline_module');

    AeProf::begin('auth_bootstrap', 'Auth::bootstrapFromSession');
    \Rateb\App\Core\Auth::bootstrapFromSession();
    AeProf::end('auth_bootstrap');

    require_once RATEB_ROOT . '/app/helpers/Request.php';
    // Force path (CLI REQUEST_URI can be messy)
    $path = $pathArg;
    $method = 'GET';

    AeProf::begin('route_module_select', 'RouteModuleLoader::selectModuleIds');
    $selected = \Rateb\App\Core\RouteModuleLoader::selectModuleIds($path);
    AeProf::end('route_module_select');

    AeProf::begin('routes_load', 'RouteModuleLoader::loadForPath');
    $router = new \Rateb\App\Core\Router();
    \Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
    if (
        \Rateb\App\Core\RouteModuleLoader::lastMode() === 'selective'
        && !$router->hasMatch($method, $path)
    ) {
        AeProf::begin('routes_fallback_all', 'fallback loadAll');
        $router = new \Rateb\App\Core\Router();
        \Rateb\App\Core\RouteModuleLoader::loadAll($router);
        \Rateb\App\Core\RouteModuleLoader::markFallbackAll();
        AeProf::end('routes_fallback_all');
    }
    AeProf::end('routes_load');
    AeProf::$spans['routes_load']['meta'] = [
        'selected' => $selected,
        'loaded' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
        'mode' => \Rateb\App\Core\RouteModuleLoader::lastMode(),
        'route_count' => $router->routeCount(),
        'files' => \Rateb\App\Core\RouteModuleLoader::lastLoadedFiles(),
    ];

    // Break out ops file cost if present
    if (in_array('ops', \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(), true)) {
        // already included inside routes_load; meta only
        AeProf::$spans['routes_load']['meta']['ops_file'] = 'routes/modules/ops.php';
    }

    AeProf::begin('middleware_erp_auth', 'ErpAuthMiddleware::handle');
    $mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
    $ok = $mw->handle();
    AeProf::end('middleware_erp_auth');
    AeProf::$spans['middleware_erp_auth']['meta']['ok'] = $ok;
    if (!$ok) {
        throw new RuntimeException('middleware rejected');
    }

    // Extra route middleware simulation is inside dispatch; call dispatch with OB capture
    AeProf::begin('dispatch_controller_view', 'Router::dispatch → controller → view');
    ob_start();
    $tFlush0 = hrtime(true);
    $router->dispatch($method, $path);
    $html = (string) ob_get_clean();
    $flushMs = (hrtime(true) - $tFlush0) / 1e6;
    AeProf::end('dispatch_controller_view');
    AeProf::$spans['dispatch_controller_view']['meta'] = [
        'html_bytes' => strlen($html),
        'ob_wall_ms' => round($flushMs, 3),
    ];

    AeProf::begin('output_buffer_complete', 'Response body ready (first byte proxy)');
    // body already in $html — first byte equivalent for CLI
    AeProf::end('output_buffer_complete');

    AeProf::end('request_to_first_byte');
    AeProf::finalizeSelf();

    $total = AeProf::$spans['request_to_first_byte']['dur_ms'];
    $sqlMs = array_sum(array_column(AeProf::$sql, 'dur_ms'));

    // Top includes by bytes in routes_load / bootstrap
    $incByPhase = [];
    foreach (AeProf::$includes as $inc) {
        $p = $inc['phase'];
        if (!isset($incByPhase[$p])) {
            $incByPhase[$p] = ['n' => 0, 'bytes' => 0];
        }
        $incByPhase[$p]['n']++;
        $incByPhase[$p]['bytes'] += $inc['bytes'];
    }

    $topIncludes = AeProf::$includes;
    usort($topIncludes, static fn($a, $b) => $b['bytes'] <=> $a['bytes']);

    $sqlByFn = [];
    foreach (AeProf::$sql as $q) {
        $k = ($q['class'] !== '' ? $q['class'] . '::' : '') . $q['function'];
        if (!isset($sqlByFn[$k])) {
            $sqlByFn[$k] = ['ms' => 0.0, 'n' => 0, 'file' => $q['file'], 'line' => $q['line'], 'sample' => $q['sql']];
        }
        $sqlByFn[$k]['ms'] += $q['dur_ms'];
        $sqlByFn[$k]['n']++;
    }
    uasort($sqlByFn, static fn($a, $b) => $b['ms'] <=> $a['ms']);

    $spanRank = array_values(AeProf::$spans);
    usort($spanRank, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);

    $leafRank = array_values(array_filter($spanRank, static fn($s) => ($s['children'] ?? []) === []));

    // Biggest leaf (self) excluding request wrapper
    $candidates = array_values(array_filter(
        $spanRank,
        static fn($s) => !in_array($s['id'], ['request_to_first_byte', 'dispatch_controller_view'], true)
    ));
    $biggest = $candidates[0] ?? null;

    $report = [
        'phase' => 'AE',
        'path' => $path,
        'measured_at' => gmdate('c'),
        'host' => gethostname(),
        'sapi' => PHP_SAPI,
        'opcache' => [
            'loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
            'enabled' => null,
        ],
        'totals' => [
            'wall_ms_to_body' => $total,
            'sql_ms' => round($sqlMs, 3),
            'sql_count' => count(AeProf::$sql),
            'include_events' => count(AeProf::$includes),
            'include_bytes' => array_sum(array_column(AeProf::$includes, 'bytes')),
            'included_files_total' => count(get_included_files()),
            'memory_peak' => memory_get_peak_usage(true),
            'html_bytes' => strlen($html),
            'route_count' => $router->routeCount(),
            'modules' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
        ],
        'includes_by_phase' => $incByPhase,
        'stages' => array_map(static function ($s) use ($total) {
            return [
                'id' => $s['id'],
                'label' => $s['label'],
                'wall_ms' => $s['dur_ms'],
                'self_ms' => $s['self_ms'],
                'pct' => $total > 0 ? round(100 * $s['dur_ms'] / $total, 2) : 0,
                'sql_count' => $s['sql_count'],
                'includes_delta' => $s['includes_delta'],
                'include_bytes' => $s['include_bytes'],
                'meta' => $s['meta'] ?? null,
            ];
        }, $spanRank),
        'top30_spans' => array_slice($spanRank, 0, 30),
        'top30_leaf_spans' => array_slice($leafRank, 0, 30),
        'top30_includes_by_bytes' => array_slice($topIncludes, 0, 30),
        'top30_sql' => array_slice(AeProf::$sql, 0, 30),
        'top30_sql_by_function' => array_slice(array_map(
            static fn($k, $v) => array_merge(['key' => $k], $v, ['ms' => round($v['ms'], 3)]),
            array_keys($sqlByFn),
            array_values($sqlByFn)
        ), 0, 30),
        'require_once_count_approx' => count(get_included_files()),
        'single_biggest_stage' => $biggest ? [
            'id' => $biggest['id'],
            'label' => $biggest['label'],
            'wall_ms' => $biggest['dur_ms'],
            'self_ms' => $biggest['self_ms'],
            'pct' => $total > 0 ? round(100 * $biggest['dur_ms'] / $total, 2) : 0,
            'includes_delta' => $biggest['includes_delta'],
            'include_bytes' => $biggest['include_bytes'],
            'meta' => $biggest['meta'] ?? null,
        ] : null,
    ];

    $dir = $RATEB_ROOT . '/tools/boot-bench/reports';
    @mkdir($dir, 0775, true);
    $slug = trim(str_replace(['/', '\\', '?'], '-', $path), '-') ?: 'root';
    $file = $dir . '/phase-ae-' . $slug . '.json';
    file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    echo json_encode([
        'path' => $path,
        'wall_ms' => $total,
        'modules' => $report['totals']['modules'],
        'route_count' => $report['totals']['route_count'],
        'sql_ms' => $report['totals']['sql_ms'],
        'include_bytes' => $report['totals']['include_bytes'],
        'top5_stages' => array_slice($report['stages'], 0, 8),
        'biggest' => $report['single_biggest_stage'],
        'wrote' => $file,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'AE FATAL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
