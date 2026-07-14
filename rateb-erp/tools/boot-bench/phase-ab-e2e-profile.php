<?php
declare(strict_types=1);

/**
 * Phase AB — end-to-end GET /admin runtime profile (AA.3 selective routes).
 * Evidence only. Run on production CLI:
 *   php tools/boot-bench/phase-ab-e2e-profile.php
 *
 * Mimics public/index.php post-AA.3, then drives DashboardController path
 * with tracing PDO, include wall timing, FS/cache ops, and tick sampling.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('memory_limit', '512M');
declare(ticks=1);

final class AbProf
{
    public static float $t0;
    /** @var array<string,array{id:string,parent:?string,label:string,start_ms:float,end_ms:float,dur_ms:float,self_ms:float,mem_start:int,mem_end:int,mem_delta:int,sql_before:int,sql_after:int,sql_count:int,children:list<string>}> */
    public static array $spans = [];
    /** @var list<string> */
    public static array $stack = [];
    public static string $phase = 'boot';
    /** @var list<array{t_ms:float,dur_ms:float,sql:string,caller:string,file:string,line:int,class:string,function:string,phase:string,fp:string}> */
    public static array $sql = [];
    /** @var list<array{t_ms:float,dur_ms:float,path:string,op:string,phase:string,bytes?:int}> */
    public static array $includes = [];
    /** @var list<array{t_ms:float,dur_ms:float,op:string,path:string,phase:string}> */
    public static array $fs = [];
    /** @var list<array{t_ms:float,dur_ms:float,op:string,key:string,phase:string,hit?:bool}> */
    public static array $cache = [];
    /** @var array<string,array{samples:int,file:string,line:int,class:string,function:string}> */
    public static array $tickSamples = [];
    public static bool $sqlOn = false;
    public static bool $tickOn = false;
    public static int $tickN = 0;
    /** @var array<string,float> */
    public static array $childSum = [];

    public static function ms(): float
    {
        return (hrtime(true) - self::$t0) / 1e6;
    }

    public static function begin(string $id, string $label): void
    {
        $parent = self::$stack !== [] ? self::$stack[count(self::$stack) - 1] : null;
        self::$stack[] = $id;
        self::$phase = $id;
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
            'mem_delta' => 0,
            'sql_before' => count(self::$sql),
            'sql_after' => 0,
            'sql_count' => 0,
            'children' => [],
        ];
        if ($parent !== null && isset(self::$spans[$parent])) {
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
        $s['mem_delta'] = $s['mem_end'] - $s['mem_start'];
        $s['sql_after'] = count(self::$sql);
        $s['sql_count'] = $s['sql_after'] - $s['sql_before'];
        self::$phase = self::$stack !== [] ? self::$stack[count(self::$stack) - 1] : 'idle';
    }

    public static function finalizeSelfTimes(): void
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
        $norm = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;
        $fp = md5(strtolower(preg_replace('/\d+/', 'N', $norm) ?? $norm));
        $file = '';
        $line = 0;
        $class = '';
        $fn = '';
        $caller = 'unknown';
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 24);
        foreach ($bt as $frame) {
            $f = (string) ($frame['file'] ?? '');
            if ($f === '' || str_contains($f, 'phase-ab-e2e-profile.php')) {
                continue;
            }
            $class = (string) ($frame['class'] ?? '');
            $fn = (string) ($frame['function'] ?? '');
            if (str_starts_with($class, 'AbProfile') || $class === 'AbProf') {
                continue;
            }
            if (in_array($fn, ['execute', 'query', 'exec'], true) && ($class === '' || str_contains($class, 'PDO'))) {
                continue;
            }
            $file = $f;
            $line = (int) ($frame['line'] ?? 0);
            $base = basename($file);
            $caller = $class !== '' ? "{$base}:{$line} {$class}::{$fn}" : "{$base}:{$line} {$fn}";
            break;
        }
        self::$sql[] = [
            't_ms' => round(self::ms(), 3),
            'dur_ms' => round($dur, 3),
            'sql' => mb_substr($norm, 0, 500),
            'caller' => $caller,
            'file' => $file,
            'line' => $line,
            'class' => $class,
            'function' => $fn,
            'phase' => self::$phase,
            'fp' => $fp,
        ];
    }

    public static function logInclude(string $path, float $dur, int $bytes = 0): void
    {
        self::$includes[] = [
            't_ms' => round(self::ms(), 3),
            'dur_ms' => round($dur, 3),
            'path' => $path,
            'op' => 'include',
            'phase' => self::$phase,
            'bytes' => $bytes,
        ];
    }

    public static function logFs(string $op, string $path, float $dur): void
    {
        self::$fs[] = [
            't_ms' => round(self::ms(), 3),
            'dur_ms' => round($dur, 3),
            'op' => $op,
            'path' => $path,
            'phase' => self::$phase,
        ];
    }

    public static function logCache(string $op, string $key, float $dur, ?bool $hit = null): void
    {
        self::$cache[] = [
            't_ms' => round(self::ms(), 3),
            'dur_ms' => round($dur, 3),
            'op' => $op,
            'key' => $key,
            'phase' => self::$phase,
            'hit' => $hit,
        ];
    }

    public static function tick(): void
    {
        if (!self::$tickOn) {
            return;
        }
        self::$tickN++;
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $frame = null;
        foreach ($bt as $f) {
            $file = (string) ($f['file'] ?? '');
            if ($file === '' || str_contains($file, 'phase-ab-e2e-profile.php')) {
                continue;
            }
            $frame = $f;
            break;
        }
        if ($frame === null) {
            return;
        }
        $file = (string) ($frame['file'] ?? '');
        $line = (int) ($frame['line'] ?? 0);
        $class = (string) ($frame['class'] ?? '');
        $fn = (string) ($frame['function'] ?? '');
        $key = ($class !== '' ? $class . '::' : '') . $fn . '@' . basename($file) . ':' . $line;
        if (!isset(self::$tickSamples[$key])) {
            self::$tickSamples[$key] = [
                'samples' => 0,
                'file' => $file,
                'line' => $line,
                'class' => $class,
                'function' => $fn,
            ];
        }
        self::$tickSamples[$key]['samples']++;
    }
}

final class AbProfileStmt extends PDOStatement
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
            AbProf::logSql((string) $this->queryString, (hrtime(true) - $t0) / 1e6);
        }
    }
}

final class AbProfilePdo extends PDO
{
    public function __construct(string $dsn, ?string $user = null, ?string $pass = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $pass, $options ?: []);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [AbProfileStmt::class, []]);
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
            AbProf::logSql((string) $query, (hrtime(true) - $t0) / 1e6);
        }
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        $t0 = hrtime(true);
        try {
            return parent::exec($statement);
        } finally {
            AbProf::logSql((string) $statement, (hrtime(true) - $t0) / 1e6);
        }
    }
}

AbProf::$t0 = hrtime(true);

$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_NAME'] = 'rateb.sa';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET = [];
$_POST = [];

$RATEB_ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
if (!is_dir($RATEB_ROOT)) {
    $RATEB_ROOT = dirname(__DIR__, 2);
}

$exts = [
    'xdebug' => extension_loaded('xdebug'),
    'xhprof' => extension_loaded('xhprof'),
    'tideways' => extension_loaded('tideways'),
    'spx' => extension_loaded('spx'),
];

try {
    AbProf::begin('request_total', 'GET /admin complete lifecycle (AA.3)');

    // ---- 1–2 PHP bootstrap + autoload ----
    AbProf::begin('php_bootstrap', 'require Bootstrap.php + Bootstrap::init');
    $files0 = get_included_files();
    AbProf::begin('composer_autoload', 'Bootstrap::init (autoload + config + session start)');
    require_once $RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($RATEB_ROOT);
    AbProf::end('composer_autoload');
    $filesAfterBoot = get_included_files();
    foreach (array_slice($filesAfterBoot, count($files0)) as $inc) {
        AbProf::logInclude($inc, 0.0, is_file($inc) ? (int) @filesize($inc) : 0);
    }
    AbProf::end('php_bootstrap');
    AbProf::$spans['php_bootstrap']['meta'] = [
        'included_delta' => count($filesAfterBoot) - count($files0),
        'included_total' => count($filesAfterBoot),
    ];

    // Install tracing PDO
    AbProf::begin('db_trace_install', 'Install tracing PDO');
    $ref = new ReflectionClass(\Rateb\App\Core\Database::class);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $host = defined('RATEB_DB_HOST') ? (string) RATEB_DB_HOST : '127.0.0.1';
    $port = defined('RATEB_DB_PORT') ? (int) RATEB_DB_PORT : 3306;
    $user = defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : '';
    $pass = defined('RATEB_DB_PASS') ? (string) RATEB_DB_PASS : '';
    $db = defined('RATEB_DB_NAME') ? (string) RATEB_DB_NAME : '';
    try {
        $ex = $prop->getValue(null) ?: \Rateb\App\Core\Database::connection();
        $row = $ex->query('SELECT DATABASE()')->fetch(PDO::FETCH_NUM);
        if (!empty($row[0])) {
            $db = (string) $row[0];
        }
    } catch (Throwable $e) {
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
    $prop->setValue(null, new AbProfilePdo($dsn, $user, $pass));
    AbProf::$sqlOn = true;
    AbProf::end('db_trace_install');

    // ---- 3 Session + mint auth (simulate logged-in admin) ----
    AbProf::begin('session', 'Session already started in Bootstrap; mint admin login');
    $pdo = \Rateb\App\Core\Database::connection();
    $st = $pdo->prepare('SELECT * FROM rateb_users WHERE email = ? LIMIT 1');
    $st->execute(['admin@rateb.sa']);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $user = $pdo->query('SELECT * FROM rateb_users WHERE id=26')->fetch(PDO::FETCH_ASSOC);
    }
    if (!$user) {
        throw new RuntimeException('admin user not found');
    }
    \Rateb\App\Core\Auth::loginUser($user);
    if (function_exists('rateb_adopt_ops_company_id')) {
        rateb_adopt_ops_company_id(22);
    }
    \Rateb\App\Core\SessionManager::set('rateb_branch_schema_ok', date('Y-m-d'));
    // First-paint: clear badge caches
    $tCache = hrtime(true);
    \Rateb\App\Core\SessionManager::forget('rateb_oversight_menu_counts');
    foreach (array_keys($_SESSION ?? []) as $k) {
        if (is_string($k) && str_starts_with($k, 'rateb_ops_nav_counts_')) {
            \Rateb\App\Core\SessionManager::forget($k);
        }
    }
    AbProf::logCache('session_forget', 'nav_badge_caches', (hrtime(true) - $tCache) / 1e6, false);
    AbProf::end('session');

    // ---- POS / Offline (as index.php) ----
    AbProf::begin('pos_module', 'PosModule::init');
    $posModule = $RATEB_ROOT . '/modules/pos/PosModule.php';
    if (is_file($posModule)) {
        $tInc = hrtime(true);
        require_once $posModule;
        AbProf::logInclude($posModule, (hrtime(true) - $tInc) / 1e6, (int) filesize($posModule));
        \Rateb\App\Pos\PosModule::init();
    }
    AbProf::end('pos_module');

    AbProf::begin('offline_module', 'OfflineModule::init');
    $offlineModule = $RATEB_ROOT . '/offline/OfflineModule.php';
    if (is_file($offlineModule)) {
        $tInc = hrtime(true);
        require_once $offlineModule;
        AbProf::logInclude($offlineModule, (hrtime(true) - $tInc) / 1e6, (int) filesize($offlineModule));
        \Rateb\App\Offline\OfflineModule::init();
    }
    AbProf::end('offline_module');

    // ---- 4 Auth bootstrap ----
    AbProf::begin('auth_bootstrap', 'Auth::bootstrapFromSession');
    \Rateb\App\Core\Auth::bootstrapFromSession();
    AbProf::end('auth_bootstrap');

    require_once $RATEB_ROOT . '/app/helpers/Request.php';
    $path = '/admin';
    $method = 'GET';

    // ---- Route load AA.3 ----
    AbProf::begin('routes_load_aa3', 'RouteModuleLoader::loadForPath(/admin)');
    $router = new \Rateb\App\Core\Router();
    $filesBeforeRoutes = count(get_included_files());
    \Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
    if (
        \Rateb\App\Core\RouteModuleLoader::lastMode() === 'selective'
        && !$router->hasMatch($method, $path)
    ) {
        $router = new \Rateb\App\Core\Router();
        \Rateb\App\Core\RouteModuleLoader::loadAll($router);
        \Rateb\App\Core\RouteModuleLoader::markFallbackAll();
    }
    AbProf::end('routes_load_aa3');
    AbProf::$spans['routes_load_aa3']['meta'] = [
        'mode' => \Rateb\App\Core\RouteModuleLoader::lastMode(),
        'modules' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
        'route_count' => $router->routeCount(),
        'included_delta' => count(get_included_files()) - $filesBeforeRoutes,
    ];

    // Tick sampling intentionally DISABLED during measured path — ticks distort wall times.
    // Function attribution comes from span walls + SQL stack frames (authoritative).
    AbProf::$tickOn = false;

    // ---- 5 Middleware ----
    AbProf::begin('middleware', 'ErpAuthMiddleware::handle');
    $mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
    $mwOk = $mw->handle();
    AbProf::end('middleware');
    AbProf::$spans['middleware']['meta']['ok'] = $mwOk;
    if (!$mwOk) {
        throw new RuntimeException('ErpAuthMiddleware rejected');
    }

    // Confirm platform SA path
    $isSa = (bool) \Rateb\App\Core\SessionManager::get('rateb_is_super_admin');
    $isPlatform = function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host();
    if (!$isSa || !$isPlatform) {
        throw new RuntimeException('Expected platform SA path; sa=' . (int) $isSa . ' platform=' . (int) $isPlatform);
    }

    // ---- 6–7 DashboardController / DashboardService ----
    AbProf::begin('dashboard_controller', 'DashboardController render path (platform SA)');
    AbProf::begin('dashboard_service', 'DashboardService::adminBuildLite');
    $service = new \Rateb\App\Services\DashboardService();
    $dash = $service->adminBuildLite();
    AbProf::end('dashboard_service');

    AbProf::begin('csrf_token', 'Csrf::token');
    $csrf = \Rateb\App\Core\Csrf::token();
    AbProf::end('csrf_token');

    $viewData = [
        'title' => __('dashboard'),
        'dash' => $dash,
        'metrics' => $dash['metrics'],
        'charts' => $dash['charts'],
        'csrf' => $csrf,
        'dashboardChartsUrl' => rateb_url('admin/api/dashboard-charts'),
    ];

    // ---- 10–13 View / sidebar / translations / includes ----
    AbProf::begin('view_rendering', 'View: admin/dashboard + layouts/main');

    AbProf::begin('view_dashboard_php', 'include views/admin/dashboard.php');
    $viewFile = RATEB_VIEWS_PATH . '/admin/dashboard.php';
    extract($viewData, EXTR_SKIP);
    AbProf::begin('output_buffering_page', 'ob_start + page include');
    ob_start();
    $tInc = hrtime(true);
    include $viewFile;
    AbProf::logInclude($viewFile, (hrtime(true) - $tInc) / 1e6, (int) @filesize($viewFile));
    $pageContent = (string) ob_get_clean();
    AbProf::end('output_buffering_page');
    AbProf::end('view_dashboard_php');
    AbProf::$spans['view_dashboard_php']['meta']['html_bytes'] = strlen($pageContent);

    // Isolated sidebar badge helpers BEFORE layout (first paint cost)
    AbProf::begin('sidebar_rendering', 'Sidebar badge helpers (first paint)');
    AbProf::begin('oversight_menu_counts', 'rateb_oversight_menu_counts()');
    $tCache = hrtime(true);
    $oversight = function_exists('rateb_oversight_menu_counts') ? rateb_oversight_menu_counts() : [];
    AbProf::logCache('oversight_menu_counts', 'rateb_oversight_menu_counts', (hrtime(true) - $tCache) / 1e6, null);
    AbProf::end('oversight_menu_counts');

    AbProf::begin('cms_leads_count', 'rateb_cms_new_leads_count()');
    $tCache = hrtime(true);
    $cmsLeads = function_exists('rateb_cms_new_leads_count') ? rateb_cms_new_leads_count() : 0;
    AbProf::logCache('cms_leads', 'rateb_cms_new_leads_count', (hrtime(true) - $tCache) / 1e6, null);
    AbProf::end('cms_leads_count');
    AbProf::end('sidebar_rendering');

    AbProf::begin('translation_loading', '__() sample keys (already warm from title)');
    $keys = ['dashboard', 'rateb_erp', 'logout', 'language', 'companies', 'settings', 'admin_oversight_section', 'cms_section'];
    foreach ($keys as $k) {
        __($k);
    }
    AbProf::end('translation_loading');

    AbProf::begin('layout_main_php', 'include views/layouts/main.php');
    $layoutFile = RATEB_VIEWS_PATH . '/layouts/main.php';
    AbProf::begin('output_buffering_layout', 'ob_start + layout include');
    ob_start();
    $tInc = hrtime(true);
    include $layoutFile;
    AbProf::logInclude($layoutFile, (hrtime(true) - $tInc) / 1e6, (int) @filesize($layoutFile));
    $fullHtml = (string) ob_get_clean();
    AbProf::end('output_buffering_layout');
    AbProf::end('layout_main_php');
    AbProf::$spans['layout_main_php']['meta'] = [
        'html_bytes' => strlen($fullHtml),
        'oversight' => $oversight,
        'cms_leads' => $cmsLeads,
    ];

    // Nested includes discovered during layout
    AbProf::begin('blade_php_includes', 'Delta includes during view/layout');
    $allInc = get_included_files();
    $newInc = array_slice($allInc, count($filesAfterBoot));
    foreach ($newInc as $inc) {
        if (str_contains($inc, '/views/') || str_contains($inc, '\\views\\')) {
            AbProf::logInclude($inc, 0.0, is_file($inc) ? (int) @filesize($inc) : 0);
            AbProf::logFs('include_view', $inc, 0.0);
        }
    }
    AbProf::end('blade_php_includes');
    AbProf::$spans['blade_php_includes']['meta']['view_includes'] = count(array_filter(
        $newInc,
        static fn($p) => str_contains($p, '/views/') || str_contains($p, '\\views\\')
    ));

    AbProf::begin('json_encoding', 'json_encode charts sample');
    $tJson = hrtime(true);
    $jsonBytes = strlen(json_encode($dash['charts'] ?? [], JSON_UNESCAPED_UNICODE) ?: '');
    AbProf::end('json_encoding');
    AbProf::$spans['json_encoding']['meta']['bytes'] = $jsonBytes;
    AbProf::$spans['json_encoding']['meta']['wall_inner_ms'] = round((hrtime(true) - $tJson) / 1e6, 3);

    AbProf::begin('response_flush', 'Simulate flush (strlen full HTML)');
    $tFlush = hrtime(true);
    $outLen = strlen($fullHtml);
    // Do not echo in CLI to avoid pipe stall; measure allocation/touch
    $touch = substr($fullHtml, 0, 1) . substr($fullHtml, -1);
    unset($touch);
    AbProf::end('response_flush');
    AbProf::$spans['response_flush']['meta'] = [
        'html_bytes' => $outLen,
        'inner_ms' => round((hrtime(true) - $tFlush) / 1e6, 3),
    ];

    AbProf::end('view_rendering');
    AbProf::end('dashboard_controller');

    AbProf::$tickOn = false;

    // Config loading evidence
    AbProf::begin('config_loading_evidence', 'Config already in bootstrap; list loaded config files');
    $cfgFiles = array_values(array_filter(get_included_files(), static fn($p) => str_contains($p, '/config/') || str_contains($p, '\\config\\')));
    AbProf::end('config_loading_evidence');
    AbProf::$spans['config_loading_evidence']['meta']['config_files'] = array_map('basename', $cfgFiles);

    // Model touch evidence from SQL callers
    AbProf::begin('models_sql_attribution', 'Aggregate SQL by class from stacks');
    AbProf::end('models_sql_attribution');

    AbProf::end('request_total');
    AbProf::finalizeSelfTimes();

    $totalMs = AbProf::$spans['request_total']['dur_ms'];
    $tickTotal = max(1, AbProf::$tickN);

    // Top functions from tick samples
    $funcs = AbProf::$tickSamples;
    uasort($funcs, static fn($a, $b) => $b['samples'] <=> $a['samples']);
    $topFuncs = [];
    $i = 0;
    foreach ($funcs as $key => $row) {
        if ($i++ >= 20) {
            break;
        }
        $share = $row['samples'] / $tickTotal;
        $estMs = round($share * $totalMs, 3); // rough — prefer span evidence for bottleneck
        $topFuncs[] = [
            'key' => $key,
            'file' => $row['file'],
            'line' => $row['line'],
            'class' => $row['class'],
            'function' => $row['function'],
            'samples' => $row['samples'],
            'sample_pct' => round(100 * $share, 2),
            'est_wall_ms_from_samples' => $estMs,
            'call_count_note' => 'tick samples (not entry counts)',
        ];
    }

    // Span wall ranking (authoritative stages)
    $spanRank = array_values(AbProf::$spans);
    usort($spanRank, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);

    // Top SQL
    $sqlSorted = AbProf::$sql;
    usort($sqlSorted, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);
    $topSql = array_slice($sqlSorted, 0, 20);

    // SQL by caller function
    $sqlByFn = [];
    foreach (AbProf::$sql as $q) {
        $k = ($q['class'] !== '' ? $q['class'] . '::' : '') . $q['function'];
        if ($k === '::' || $k === '') {
            $k = $q['caller'];
        }
        if (!isset($sqlByFn[$k])) {
            $sqlByFn[$k] = ['ms' => 0.0, 'n' => 0, 'file' => $q['file'], 'line' => $q['line'], 'class' => $q['class'], 'function' => $q['function'], 'sample' => $q['sql']];
        }
        $sqlByFn[$k]['ms'] += $q['dur_ms'];
        $sqlByFn[$k]['n']++;
    }
    uasort($sqlByFn, static fn($a, $b) => $b['ms'] <=> $a['ms']);

    // Includes top
    $incRank = AbProf::$includes;
    usort($incRank, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);
    // Also by bytes if dur 0
    $incByBytes = AbProf::$includes;
    usort($incByBytes, static fn($a, $b) => ($b['bytes'] ?? 0) <=> ($a['bytes'] ?? 0));

    $fsRank = AbProf::$fs;
    usort($fsRank, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);

    $cacheRank = AbProf::$cache;
    usort($cacheRank, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);

    // Flame tree from spans
    $buildTree = static function (string $id) use (&$buildTree, $totalMs): array {
        $s = AbProf::$spans[$id];
        $node = [
            'id' => $id,
            'label' => $s['label'],
            'wall_ms' => $s['dur_ms'],
            'self_ms' => $s['self_ms'],
            'pct' => $totalMs > 0 ? round(100 * $s['dur_ms'] / $totalMs, 2) : 0,
            'sql_count' => $s['sql_count'],
            'mem_delta' => $s['mem_delta'],
            'children' => [],
        ];
        foreach ($s['children'] as $cid) {
            $node['children'][] = $buildTree($cid);
        }
        return $node;
    };
    $flame = isset(AbProf::$spans['request_total']) ? $buildTree('request_total') : null;

    // Biggest bottleneck: largest exclusive (self) span that isn't the root wrappers,
    // OR largest leaf; prefer actionable child over request_total/view_rendering containers.
    $skip = ['request_total', 'view_rendering', 'dashboard_controller', 'php_bootstrap', 'composer_autoload'];
    $candidates = [];
    foreach (AbProf::$spans as $s) {
        if (in_array($s['id'], $skip, true)) {
            continue;
        }
        $candidates[] = $s;
    }
    usort($candidates, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);
    $biggestSpan = $candidates[0] ?? AbProf::$spans['request_total'];

    // Refine: if biggest is layout_main_php, dig into SQL callers during that phase
    $biggestDetail = null;
    $phaseForBiggest = $biggestSpan['id'];
    $phaseSqlMs = 0.0;
    $phaseSqlTop = null;
    foreach (AbProf::$sql as $q) {
        if ($q['phase'] !== $phaseForBiggest && !str_starts_with($phaseForBiggest, 'layout') && !str_starts_with($phaseForBiggest, 'sidebar') && !str_starts_with($phaseForBiggest, 'oversight')) {
            // also attribute SQL under parent phases
        }
    }
    // Attribute SQL wall inside the biggest span time window
    $winStart = $biggestSpan['start_ms'];
    $winEnd = $biggestSpan['end_ms'];
    $sqlInWindow = [];
    foreach (AbProf::$sql as $q) {
        if ($q['t_ms'] >= $winStart && $q['t_ms'] <= $winEnd) {
            $sqlInWindow[] = $q;
            $phaseSqlMs += $q['dur_ms'];
        }
    }
    usort($sqlInWindow, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);
    $phaseSqlTop = $sqlInWindow[0] ?? null;

    // Function-level bottleneck preference:
    // 1) If SQL dominates biggest span → that SQL caller
    // 2) Else span itself
    $sqlTotalMs = array_sum(array_column(AbProf::$sql, 'dur_ms'));
    $bottleneck = [
        'basis' => 'span_wall',
        'span_id' => $biggestSpan['id'],
        'label' => $biggestSpan['label'],
        'file' => null,
        'class' => null,
        'function' => null,
        'line' => null,
        'ms' => $biggestSpan['dur_ms'],
        'pct_of_request' => $totalMs > 0 ? round(100 * $biggestSpan['dur_ms'] / $totalMs, 2) : 0,
    ];

    // Map known spans to exact symbols
    $spanSymbol = [
        'layout_main_php' => [
            'file' => $RATEB_ROOT . '/views/layouts/main.php',
            'class' => '',
            'function' => 'include',
            'line' => 1,
        ],
        'oversight_menu_counts' => [
            'file' => $RATEB_ROOT . '/config/app.php',
            'class' => '',
            'function' => 'rateb_oversight_menu_counts',
            'line' => 0,
        ],
        'cms_leads_count' => [
            'file' => $RATEB_ROOT . '/config/app.php',
            'class' => '',
            'function' => 'rateb_cms_new_leads_count',
            'line' => 0,
        ],
        'dashboard_service' => [
            'file' => $RATEB_ROOT . '/app/services/DashboardService.php',
            'class' => 'Rateb\\App\\Services\\DashboardService',
            'function' => 'adminBuildLite',
            'line' => 0,
        ],
        'auth_bootstrap' => [
            'file' => $RATEB_ROOT . '/app/Core/Auth.php',
            'class' => 'Rateb\\App\\Core\\Auth',
            'function' => 'bootstrapFromSession',
            'line' => 0,
        ],
        'routes_load_aa3' => [
            'file' => $RATEB_ROOT . '/app/Core/RouteModuleLoader.php',
            'class' => 'Rateb\\App\\Core\\RouteModuleLoader',
            'function' => 'loadForPath',
            'line' => 0,
        ],
        'middleware' => [
            'file' => $RATEB_ROOT . '/app/Core/Middleware/ErpAuthMiddleware.php',
            'class' => 'Rateb\\App\\Core\\Middleware\\ErpAuthMiddleware',
            'function' => 'handle',
            'line' => 0,
        ],
        'sidebar_rendering' => [
            'file' => $RATEB_ROOT . '/views/layouts/main.php',
            'class' => '',
            'function' => 'sidebar_badge_helpers',
            'line' => 0,
        ],
    ];

    // Resolve line numbers via reflection/file scan
    $resolveLine = static function (string $file, string $fn) use ($RATEB_ROOT): int {
        if ($file === '' || !is_file($file)) {
            return 0;
        }
        $src = (string) file_get_contents($file);
        if ($fn === 'include' || $fn === '') {
            return 1;
        }
        if (preg_match('/function\s+' . preg_quote($fn, '/') .'\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
            return substr_count(substr($src, 0, $m[0][1]), "\n") + 1;
        }
        if (preg_match('/function\s+' . preg_quote($fn, '/') .'\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
            return substr_count(substr($src, 0, $m[0][1]), "\n") + 1;
        }
        return 0;
    };

    if (isset($spanSymbol[$biggestSpan['id']])) {
        $sym = $spanSymbol[$biggestSpan['id']];
        $bottleneck['file'] = $sym['file'];
        $bottleneck['class'] = $sym['class'];
        $bottleneck['function'] = $sym['function'];
        $bottleneck['line'] = $resolveLine($sym['file'], $sym['function']);
    }

    // If SQL in window > 50% of biggest span, promote SQL caller as THE bottleneck
    if ($phaseSqlTop !== null && $phaseSqlMs >= 0.45 * $biggestSpan['dur_ms'] && $phaseSqlMs > 1.0) {
        // Prefer aggregated top SQL-by-function inside window
        $winFn = [];
        foreach ($sqlInWindow as $q) {
            $k = ($q['class'] !== '' ? $q['class'] . '::' : '') . $q['function'];
            if (!isset($winFn[$k])) {
                $winFn[$k] = ['ms' => 0.0, 'n' => 0, 'q' => $q];
            }
            $winFn[$k]['ms'] += $q['dur_ms'];
            $winFn[$k]['n']++;
        }
        uasort($winFn, static fn($a, $b) => $b['ms'] <=> $a['ms']);
        $topWin = reset($winFn);
        $q = $topWin['q'];
        $bottleneck = [
            'basis' => 'sql_dominated_span:' . $biggestSpan['id'],
            'span_id' => $biggestSpan['id'],
            'label' => $biggestSpan['label'] . ' → SQL in ' . (($q['class'] !== '' ? $q['class'] . '::' : '') . $q['function']),
            'file' => $q['file'],
            'class' => $q['class'],
            'function' => $q['function'],
            'line' => $q['line'],
            'ms' => round($topWin['ms'], 3),
            'pct_of_request' => $totalMs > 0 ? round(100 * $topWin['ms'] / $totalMs, 2) : 0,
            'sql_count_in_fn' => $topWin['n'],
            'sample_sql' => $q['sql'],
            'parent_span_ms' => $biggestSpan['dur_ms'],
            'parent_span_sql_ms' => round($phaseSqlMs, 3),
        ];
    }

    // Alternate: absolute top SQL-by-function if larger % than span self
    $topSqlFn = null;
    foreach ($sqlByFn as $k => $row) {
        $topSqlFn = array_merge(['key' => $k], $row);
        break;
    }
    if ($topSqlFn !== null && $topSqlFn['ms'] > ($bottleneck['ms'] ?? 0) && $topSqlFn['ms'] / max(0.001, $totalMs) >= 0.15) {
        $bottleneck = [
            'basis' => 'top_sql_by_function',
            'span_id' => null,
            'label' => 'SQL attributed to ' . $topSqlFn['key'],
            'file' => $topSqlFn['file'],
            'class' => $topSqlFn['class'],
            'function' => $topSqlFn['function'],
            'line' => $topSqlFn['line'],
            'ms' => round($topSqlFn['ms'], 3),
            'pct_of_request' => $totalMs > 0 ? round(100 * $topSqlFn['ms'] / $totalMs, 2) : 0,
            'sql_count_in_fn' => $topSqlFn['n'],
            'sample_sql' => $topSqlFn['sample'],
        ];
    }

    // Absolute top exclusive span among leaf-ish ids
    $leafCandidates = [];
    foreach (AbProf::$spans as $s) {
        if ($s['children'] === [] && !in_array($s['id'], ['request_total'], true)) {
            $leafCandidates[] = $s;
        }
    }
    usort($leafCandidates, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);

    $report = [
        'phase' => 'AB',
        'title' => 'End-to-end GET /admin runtime profile after AA.3',
        'measured_at' => gmdate('c'),
        'runtime' => [
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'host' => gethostname() ?: 'unknown',
            'rateb_root' => $RATEB_ROOT,
            'extensions' => $exts,
            'opcache' => function_exists('opcache_get_status') ? @opcache_get_status(false) : null,
        ],
        'request' => [
            'method' => 'GET',
            'path' => '/admin',
            'aa3_modules' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
            'aa3_mode' => \Rateb\App\Core\RouteModuleLoader::lastMode(),
            'route_count' => $router->routeCount(),
        ],
        'totals' => [
            'wall_ms' => $totalMs,
            'sql_count' => count(AbProf::$sql),
            'sql_ms' => round($sqlTotalMs, 3),
            'sql_pct' => $totalMs > 0 ? round(100 * $sqlTotalMs / $totalMs, 2) : 0,
            'include_events' => count(AbProf::$includes),
            'fs_events' => count(AbProf::$fs),
            'cache_events' => count(AbProf::$cache),
            'tick_samples' => AbProf::$tickN,
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'html_bytes' => $outLen,
        ],
        'stages' => array_map(static function ($s) use ($totalMs) {
            return [
                'id' => $s['id'],
                'label' => $s['label'],
                'wall_ms' => $s['dur_ms'],
                'self_ms' => $s['self_ms'],
                'pct' => $totalMs > 0 ? round(100 * $s['dur_ms'] / $totalMs, 2) : 0,
                'sql_count' => $s['sql_count'],
                'mem_delta' => $s['mem_delta'],
                'parent' => $s['parent'],
            ];
        }, $spanRank),
        'flame_tree' => $flame,
        'top20_spans_by_wall' => array_slice($spanRank, 0, 20),
        'top20_leaf_spans' => array_slice($leafCandidates, 0, 20),
        'top20_functions_tick_samples' => $topFuncs,
        'top20_sql_queries' => $topSql,
        'top20_sql_by_function' => array_slice(array_map(static function ($k, $v) {
            return array_merge(['key' => $k], $v);
        }, array_keys($sqlByFn), array_values($sqlByFn)), 0, 20),
        'top20_includes_by_dur' => array_slice($incRank, 0, 20),
        'top20_includes_by_bytes' => array_slice($incByBytes, 0, 20),
        'top20_filesystem' => array_slice($fsRank, 0, 20),
        'top20_cache' => array_slice($cacheRank, 0, 20),
        'single_biggest_bottleneck' => $bottleneck,
        'note' => 'Wall spans are authoritative. Tick samples are approximate (no xhprof/xdebug). SQL ms is execute/query wall on tracing PDO.',
    ];

    $outDir = $RATEB_ROOT . '/tools/boot-bench/reports';
    if (!is_dir($outDir)) {
        @mkdir($outDir, 0775, true);
    }
    $outFile = $outDir . '/phase-ab-e2e-profile.json';
    file_put_contents($outFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

    // Slim stdout summary
    $summary = [
        'wall_ms' => $totalMs,
        'sql_ms' => round($sqlTotalMs, 3),
        'sql_count' => count(AbProf::$sql),
        'route_count' => $router->routeCount(),
        'modules' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
        'top5_spans' => array_map(static fn($s) => [
            'id' => $s['id'],
            'wall_ms' => $s['dur_ms'],
            'self_ms' => $s['self_ms'],
            'pct' => $totalMs > 0 ? round(100 * $s['dur_ms'] / $totalMs, 2) : 0,
            'sql' => $s['sql_count'],
        ], array_slice($spanRank, 0, 8)),
        'top5_sql_fn' => array_slice(array_map(static fn($k, $v) => [
            'fn' => $k,
            'ms' => round($v['ms'], 3),
            'n' => $v['n'],
            'file' => $v['file'],
            'line' => $v['line'],
        ], array_keys($sqlByFn), array_values($sqlByFn)), 0, 5),
        'bottleneck' => $bottleneck,
        'wrote' => $outFile,
    ];
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'AB PROFILE FATAL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
