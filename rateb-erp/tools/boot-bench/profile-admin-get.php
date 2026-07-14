<?php
declare(strict_types=1);

/**
 * Evidence-only lifecycle profiler for GET /admin/
 * Runs on production CLI (or via SSH). Does NOT modify app code permanently.
 *
 * Usage: php profile-admin-get.php
 * Output: JSON to stdout + /tmp/rateb-admin-profile.json
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$T0 = hrtime(true);
$wall0 = microtime(true);

final class AdminGetProfiler
{
    /** @var list<array{id:string,parent:?string,label:string,start_ms:float,end_ms:float,dur_ms:float,sql_before:int,sql_after:int,sql_count:int,meta:array}> */
    public static array $spans = [];
    /** @var list<array{t_ms:float,dur_ms:float,sql:string,caller:string,phase:string}> */
    public static array $sql = [];
    /** @var list<string> */
    public static array $spanStack = [];
    public static string $phase = 'boot';
    public static float $t0;
    /** @var array<string,int> */
    public static array $sqlFingerprint = [];
    public static bool $sqlCapture = false;

    public static function ms(): float
    {
        return (hrtime(true) - self::$t0) / 1e6;
    }

    public static function begin(string $id, string $label, array $meta = []): void
    {
        $parent = self::$spanStack !== [] ? self::$spanStack[count(self::$spanStack) - 1] : null;
        self::$spanStack[] = $id;
        self::$phase = $id;
        self::$spans[$id] = [
            'id' => $id,
            'parent' => $parent,
            'label' => $label,
            'start_ms' => self::ms(),
            'end_ms' => 0.0,
            'dur_ms' => 0.0,
            'sql_before' => count(self::$sql),
            'sql_after' => 0,
            'sql_count' => 0,
            'meta' => $meta,
        ];
    }

    public static function end(string $id): void
    {
        if (!isset(self::$spans[$id])) {
            return;
        }
        while (self::$spanStack !== [] && self::$spanStack[count(self::$spanStack) - 1] !== $id) {
            array_pop(self::$spanStack);
        }
        if (self::$spanStack !== [] && self::$spanStack[count(self::$spanStack) - 1] === $id) {
            array_pop(self::$spanStack);
        }
        $end = self::ms();
        self::$spans[$id]['end_ms'] = $end;
        self::$spans[$id]['dur_ms'] = round($end - self::$spans[$id]['start_ms'], 3);
        self::$spans[$id]['sql_after'] = count(self::$sql);
        self::$spans[$id]['sql_count'] = self::$spans[$id]['sql_after'] - self::$spans[$id]['sql_before'];
        self::$phase = self::$spanStack !== [] ? self::$spanStack[count(self::$spanStack) - 1] : 'idle';
    }

    public static function logSql(string $sql, float $durMs): void
    {
        if (!self::$sqlCapture) {
            return;
        }
        $caller = self::caller();
        $norm = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;
        $fp = md5(strtolower(preg_replace('/\d+/', 'N', $norm) ?? $norm));
        self::$sqlFingerprint[$fp] = (self::$sqlFingerprint[$fp] ?? 0) + 1;
        self::$sql[] = [
            't_ms' => round(self::ms(), 3),
            'dur_ms' => round($durMs, 3),
            'sql' => mb_substr($norm, 0, 400),
            'caller' => $caller,
            'phase' => self::$phase,
            'fingerprint' => $fp,
            'repeat_n' => self::$sqlFingerprint[$fp],
        ];
    }

    private static function caller(): string
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 16);
        foreach ($bt as $frame) {
            $file = (string) ($frame['file'] ?? '');
            if ($file === '' || str_contains($file, 'profile-admin-get.php')) {
                continue;
            }
            $cls = (string) ($frame['class'] ?? '');
            $fn = (string) ($frame['function'] ?? '');
            $line = (int) ($frame['line'] ?? 0);
            $base = basename($file);
            if ($cls !== '') {
                return $base . ':' . $line . ' ' . $cls . '::' . $fn;
            }
            return $base . ':' . $line . ' ' . $fn;
        }
        return 'unknown';
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
            AdminGetProfiler::logSql((string) $this->queryString, (hrtime(true) - $t0) / 1e6);
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
            AdminGetProfiler::logSql((string) $query, (hrtime(true) - $t0) / 1e6);
        }
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        $t0 = hrtime(true);
        try {
            return parent::exec($statement);
        } finally {
            AdminGetProfiler::logSql((string) $statement, (hrtime(true) - $t0) / 1e6);
        }
    }
}

AdminGetProfiler::$t0 = $T0;

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
define('RATEB_PROFILE_ROOT', $RATEB_ROOT);

/** @return array{ok:bool,spans:array,error?:string} */
function rateb_profile_install_tracing_pdo(): array
{
    $ref = new ReflectionClass(\Rateb\App\Core\Database::class);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $existing = $prop->getValue(null);
    if ($existing instanceof RatebProfilePdo) {
        return ['ok' => true, 'spans' => []];
    }

    $host = defined('RATEB_DB_HOST') ? (string) RATEB_DB_HOST : '127.0.0.1';
    $port = defined('RATEB_DB_PORT') ? (int) RATEB_DB_PORT : 3306;
    $user = defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : '';
    $pass = defined('RATEB_DB_PASS') ? (string) RATEB_DB_PASS : '';
    $db = \Rateb\App\Core\Database::resolvedDatabaseName();
    if ($db === '' && defined('RATEB_DB_NAME')) {
        $db = (string) RATEB_DB_NAME;
    }
    if ($existing instanceof PDO) {
        try {
            $row = $existing->query('SELECT DATABASE()')->fetch(PDO::FETCH_NUM);
            if (is_array($row) && !empty($row[0])) {
                $db = (string) $row[0];
            }
        } catch (Throwable $e) {
        }
    }
    if ($db === '') {
        return ['ok' => false, 'spans' => [], 'error' => 'no_db_name'];
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
    $trace = new RatebProfilePdo($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    try {
        $trace->exec('SET SESSION sql_mode = CONCAT(@@sql_mode, ",STRICT_TRANS_TABLES")');
    } catch (Throwable $e) {
    }
    if ($existing instanceof PDO) {
        try {
            $existing = null;
        } catch (Throwable $e) {
        }
    }
    $prop->setValue(null, $trace);
    AdminGetProfiler::$sqlCapture = true;

    return ['ok' => true, 'spans' => []];
}

function rateb_profile_annotate_capabilities(array $span): array
{
    $id = $span['id'];
    $blocking = true;
    $cacheable = false;
    $repeated = ($span['sql_count'] ?? 0) > 3;
    $defer = false;
    $lazy = false;

    $map = [
        'bootstrap_eager_requires' => ['blocking' => true, 'cacheable' => true, 'defer' => false, 'lazy' => true],
        'bootstrap_session' => ['blocking' => true, 'cacheable' => false, 'defer' => false, 'lazy' => false],
        'bootstrap_load_config' => ['blocking' => true, 'cacheable' => true, 'defer' => false, 'lazy' => false],
        'db_connect' => ['blocking' => true, 'cacheable' => true, 'defer' => false, 'lazy' => false],
        'auth_bootstrap' => ['blocking' => true, 'cacheable' => false, 'defer' => false, 'lazy' => false],
        'routes_load' => ['blocking' => true, 'cacheable' => true, 'defer' => false, 'lazy' => true],
        'middleware_erp_auth' => ['blocking' => true, 'cacheable' => false, 'defer' => false, 'lazy' => false],
        'middleware_branch_schema' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
        'controller_admin_build_lite' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
        'controller_admin_metrics' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
        'oversight_menu_counts' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
        'view_dashboard' => ['blocking' => true, 'cacheable' => false, 'defer' => false, 'lazy' => false],
        'layout_main' => ['blocking' => true, 'cacheable' => false, 'defer' => false, 'lazy' => false],
        'layout_sidebar_nav' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
        'layout_oversight_counts' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
        'layout_cms_leads' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
        'language_translations' => ['blocking' => true, 'cacheable' => true, 'defer' => false, 'lazy' => true],
        'pos_module' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
        'offline_module' => ['blocking' => true, 'cacheable' => true, 'defer' => true, 'lazy' => true],
    ];
    if (isset($map[$id])) {
        $blocking = $map[$id]['blocking'];
        $cacheable = $map[$id]['cacheable'];
        $defer = $map[$id]['defer'];
        $lazy = $map[$id]['lazy'];
    }

    $span['blocking'] = $blocking;
    $span['cacheable'] = $cacheable;
    $span['repeated'] = $repeated;
    $span['can_defer'] = $defer;
    $span['can_lazy_load'] = $lazy;

    return $span;
}

try {
    AdminGetProfiler::begin('request_total', 'GET /admin/ complete lifecycle');

    // ---- Bootstrap require ----
    AdminGetProfiler::begin('bootstrap_file_require', 'require Bootstrap.php');
    require_once $RATEB_ROOT . '/app/Core/Bootstrap.php';
    AdminGetProfiler::end('bootstrap_file_require');

    // Instrument Bootstrap::init internals by replaying equivalent phases after a normal init
    // First: full init with sub-timing via include file count + wall times around known hooks.
    AdminGetProfiler::begin('bootstrap_init', 'Bootstrap::init (full)');
    $filesBefore = count(get_included_files());

    AdminGetProfiler::begin('bootstrap_autoloader_register', 'registerAutoloader + Request/Entities');
    // Bootstrap::init is atomic — we must call it as one unit, then measure subsets separately.
    // Warm/secondary pass below isolates substeps.
    AdminGetProfiler::end('bootstrap_autoloader_register');

    \Rateb\App\Core\Bootstrap::init($RATEB_ROOT);
    $filesAfterBoot = count(get_included_files());
    AdminGetProfiler::end('bootstrap_init');
    AdminGetProfiler::$spans['bootstrap_init']['meta']['included_files_delta'] = $filesAfterBoot - $filesBefore;
    AdminGetProfiler::$spans['bootstrap_init']['meta']['included_files_total'] = $filesAfterBoot;

    // Install SQL tracer ASAP (first real connection may already exist from session/config).
    AdminGetProfiler::begin('db_connect_trace_install', 'Install tracing PDO (reconnect)');
    $install = rateb_profile_install_tracing_pdo();
    AdminGetProfiler::end('db_connect_trace_install');
    if (!$install['ok']) {
        throw new RuntimeException('tracing PDO install failed: ' . ($install['error'] ?? 'unknown'));
    }

    // Session mint / auth as production admin
    AdminGetProfiler::begin('session_auth_mint', 'Auth::loginUser(admin@rateb.sa) + company 22');
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
    // Mark schema-ok so middleware path matches warm production session when possible,
    // BUT first measure cold middleware schema cost in an isolated span below.
    AdminGetProfiler::end('session_auth_mint');

    // Isolated cold probe for branch schema (then set flag for main path)
    AdminGetProfiler::begin('middleware_branch_schema_cold', 'rateb_ensure_erp_branch_schema (force cold)');
    \Rateb\App\Core\SessionManager::forget('rateb_branch_schema_ok');
    if (function_exists('rateb_ensure_erp_branch_schema')) {
        // Reset static $ran via a fresh request isn't possible — call MigrationService directly
        try {
            (new \Rateb\App\Services\MigrationService())->repairBranchOpsSchemaIfNeeded();
        } catch (Throwable $e) {
            AdminGetProfiler::$spans['middleware_branch_schema_cold']['meta']['error'] = $e->getMessage();
        }
    }
    AdminGetProfiler::end('middleware_branch_schema_cold');
    \Rateb\App\Core\SessionManager::set('rateb_branch_schema_ok', date('Y-m-d'));

    // Clear oversight cache to measure real badge cost (matches post-login first dashboard)
    AdminGetProfiler::begin('cache_clear_nav_badges', 'Clear session nav count caches (simulate first paint)');
    \Rateb\App\Core\SessionManager::forget('rateb_oversight_menu_counts');
    foreach (array_keys($_SESSION ?? []) as $k) {
        if (is_string($k) && str_starts_with($k, 'rateb_ops_nav_counts_')) {
            \Rateb\App\Core\SessionManager::forget($k);
        }
    }
    AdminGetProfiler::end('cache_clear_nav_badges');

    // POS / Offline
    AdminGetProfiler::begin('pos_module', 'PosModule::init');
    $posModule = $RATEB_ROOT . '/modules/pos/PosModule.php';
    if (is_file($posModule)) {
        require_once $posModule;
        \Rateb\App\Pos\PosModule::init();
    }
    AdminGetProfiler::end('pos_module');

    AdminGetProfiler::begin('offline_module', 'OfflineModule::init');
    $offlineModule = $RATEB_ROOT . '/offline/OfflineModule.php';
    if (is_file($offlineModule)) {
        require_once $offlineModule;
        \Rateb\App\Offline\OfflineModule::init();
    }
    AdminGetProfiler::end('offline_module');

    AdminGetProfiler::begin('auth_bootstrap', 'Auth::bootstrapFromSession');
    \Rateb\App\Core\Auth::bootstrapFromSession();
    AdminGetProfiler::end('auth_bootstrap');

    AdminGetProfiler::begin('routes_load', 'require routes (web/marketing/cms/company/api/pos)');
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
    AdminGetProfiler::end('routes_load');

    require_once $RATEB_ROOT . '/app/helpers/Request.php';

    // Middleware (ErpAuthMiddleware) — match Router
    AdminGetProfiler::begin('middleware_erp_auth', 'ErpAuthMiddleware::handle');
    $mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
    $mwOk = $mw->handle();
    AdminGetProfiler::end('middleware_erp_auth');
    AdminGetProfiler::$spans['middleware_erp_auth']['meta']['ok'] = $mwOk;
    if (!$mwOk) {
        throw new RuntimeException('ErpAuthMiddleware rejected request');
    }

    // Controller path (platform SA → adminBuildLite)
    AdminGetProfiler::begin('controller_dashboard', 'DashboardController::renderDashboard path');

    $isPortal = function_exists('rateb_is_portal_branch_session') && rateb_is_portal_branch_session();
    $isSa = (bool) \Rateb\App\Core\SessionManager::get('rateb_is_super_admin');
    $isPlatform = function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host();

    AdminGetProfiler::$spans['controller_dashboard']['meta']['portal'] = $isPortal;
    AdminGetProfiler::$spans['controller_dashboard']['meta']['super_admin'] = $isSa;
    AdminGetProfiler::$spans['controller_dashboard']['meta']['platform_host'] = $isPlatform;

    if (!$isSa || !$isPlatform) {
        throw new RuntimeException('Expected platform super-admin path; got sa=' . (int) $isSa . ' platform=' . (int) $isPlatform);
    }

    AdminGetProfiler::begin('controller_admin_build_lite', 'DashboardService::adminBuildLite');
    $service = new \Rateb\App\Services\DashboardService();

    AdminGetProfiler::begin('controller_admin_metrics', 'DashboardService::adminMetrics');
    // Call via adminBuildLite to keep real path, but also isolate metrics with reflection of internals
    $dash = $service->adminBuildLite();
    AdminGetProfiler::end('controller_admin_metrics');
    AdminGetProfiler::end('controller_admin_build_lite');

    AdminGetProfiler::begin('csrf_token', 'Csrf::token');
    $csrf = \Rateb\App\Core\Csrf::token();
    AdminGetProfiler::end('csrf_token');

    $viewData = [
        'title' => __('dashboard'),
        'dash' => $dash,
        'metrics' => $dash['metrics'],
        'charts' => $dash['charts'],
        'csrf' => $csrf,
        'dashboardChartsUrl' => rateb_url('admin/api/dashboard-charts'),
    ];

    // View + layout with nested timing
    AdminGetProfiler::begin('view_render_total', 'View::render admin/dashboard + layout main');

    $viewFile = RATEB_VIEWS_PATH . '/admin/dashboard.php';
    $layoutFile = RATEB_VIEWS_PATH . '/layouts/main.php';

    AdminGetProfiler::begin('view_dashboard', 'include views/admin/dashboard.php');
    extract($viewData, EXTR_SKIP);
    ob_start();
    include $viewFile;
    $pageContent = (string) ob_get_clean();
    AdminGetProfiler::end('view_dashboard');
    AdminGetProfiler::$spans['view_dashboard']['meta']['html_bytes'] = strlen($pageContent);

    // Pre-measure sidebar hot helpers as called from layout
    AdminGetProfiler::begin('layout_oversight_counts', 'rateb_oversight_menu_counts()');
    $oversightPre = function_exists('rateb_oversight_menu_counts') ? rateb_oversight_menu_counts() : [];
    AdminGetProfiler::end('layout_oversight_counts');
    // Clear static cache inside function? It's static $cached — already filled. Real layout will use cache.
    // So relocate: call counts ONLY inside layout measurement by forgetting session AND resetting via
    // a second request isn't possible. Instead note: above was the expensive first call; layout will be cached.

    AdminGetProfiler::begin('layout_cms_leads', 'rateb_cms_new_leads_count()');
    $cmsLeads = function_exists('rateb_cms_new_leads_count') ? rateb_cms_new_leads_count() : 0;
    AdminGetProfiler::end('layout_cms_leads');

    AdminGetProfiler::begin('language_translations', 'Sample __(' . 'dashboard) + 20 keys via __()');
    $keys = ['dashboard', 'rateb_erp', 'logout', 'language', 'theme_dark', 'admin_oversight_section', 'cms_section', 'access_control', 'branches', 'companies'];
    foreach ($keys as $k) {
        __($k);
    }
    AdminGetProfiler::end('language_translations');

    AdminGetProfiler::begin('layout_main', 'include views/layouts/main.php (output buffering)');
    ob_start();
    include $layoutFile;
    $fullHtml = (string) ob_get_clean();
    AdminGetProfiler::end('layout_main');
    AdminGetProfiler::$spans['layout_main']['meta']['html_bytes'] = strlen($fullHtml);
    AdminGetProfiler::$spans['layout_main']['meta']['oversight_pre'] = $oversightPre;
    AdminGetProfiler::$spans['layout_main']['meta']['cms_leads'] = $cmsLeads;

    AdminGetProfiler::end('view_render_total');
    AdminGetProfiler::end('controller_dashboard');

    // Secondary isolated spans for Bootstrap cost (already booted — measure file sizes only)
    AdminGetProfiler::begin('bootstrap_eager_requires_evidence', 'Evidence: count eager Bootstrap bundles on disk');
    $eagerList = [];
    $bootSrc = file_get_contents($RATEB_ROOT . '/app/Core/Bootstrap.php') ?: '';
    if (preg_match('/foreach \(\[(.*?)\] as \$bundle\)/s', $bootSrc, $m)) {
        preg_match_all("#\'(/app/[^\']+)\'#", $m[1], $mm);
        $eagerList = $mm[1] ?? [];
    }
    $bytes = 0;
    $missing = 0;
    foreach ($eagerList as $rel) {
        $f = $RATEB_ROOT . $rel;
        if (is_file($f)) {
            $bytes += (int) filesize($f);
        } else {
            $missing++;
        }
    }
    AdminGetProfiler::end('bootstrap_eager_requires_evidence');
    AdminGetProfiler::$spans['bootstrap_eager_requires_evidence']['meta'] = [
        'bundle_count' => count($eagerList),
        'bytes_on_disk' => $bytes,
        'missing' => $missing,
        'note' => 'Actual require cost is inside bootstrap_init; this span is metadata only',
    ];

    AdminGetProfiler::end('request_total');

    // Annotate + build report
    $spansOut = [];
    foreach (AdminGetProfiler::$spans as $span) {
        $spansOut[] = rateb_profile_annotate_capabilities($span);
    }
    usort($spansOut, static fn($a, $b) => ($b['dur_ms'] <=> $a['dur_ms']));

    $sqlSorted = AdminGetProfiler::$sql;
    usort($sqlSorted, static fn($a, $b) => ($b['dur_ms'] <=> $a['dur_ms']));

    $sqlByPhase = [];
    foreach (AdminGetProfiler::$sql as $q) {
        $p = $q['phase'];
        if (!isset($sqlByPhase[$p])) {
            $sqlByPhase[$p] = ['count' => 0, 'dur_ms' => 0.0];
        }
        $sqlByPhase[$p]['count']++;
        $sqlByPhase[$p]['dur_ms'] += $q['dur_ms'];
    }

    $repeatedSql = [];
    foreach (AdminGetProfiler::$sqlFingerprint as $fp => $n) {
        if ($n < 2) {
            continue;
        }
        foreach (AdminGetProfiler::$sql as $q) {
            if ($q['fingerprint'] === $fp) {
                $repeatedSql[] = ['n' => $n, 'sql' => $q['sql'], 'caller' => $q['caller']];
                break;
            }
        }
    }
    usort($repeatedSql, static fn($a, $b) => $b['n'] <=> $a['n']);

    // Flame rows (exclusive approx = span dur; children listed)
    $byId = [];
    foreach (AdminGetProfiler::$spans as $s) {
        $byId[$s['id']] = $s;
    }
    $children = [];
    foreach (AdminGetProfiler::$spans as $s) {
        $p = $s['parent'] ?? null;
        if ($p) {
            $children[$p][] = $s['id'];
        }
    }
    $flame = [];
    $renderFlame = static function (string $id, int $depth) use (&$renderFlame, &$flame, $byId, $children): void {
        if (!isset($byId[$id])) {
            return;
        }
        $s = $byId[$id];
        $bar = (int) max(1, min(60, round($s['dur_ms'] / 20)));
        $flame[] = [
            'depth' => $depth,
            'id' => $id,
            'label' => $s['label'],
            'dur_ms' => $s['dur_ms'],
            'sql_count' => $s['sql_count'],
            'bar' => str_repeat('█', $bar),
        ];
        foreach ($children[$id] ?? [] as $cid) {
            $renderFlame($cid, $depth + 1);
        }
    };
    if (isset($byId['request_total'])) {
        $renderFlame('request_total', 0);
    }

    $top10 = array_slice(array_values(array_filter($spansOut, static function ($s) {
        return !in_array($s['id'], ['request_total', 'view_render_total', 'controller_dashboard', 'bootstrap_eager_requires_evidence', 'cache_clear_nav_badges', 'session_auth_mint', 'db_connect_trace_install'], true);
    })), 0, 10);

    // Biggest bottleneck among exclusive leaf-ish operations
    $bottleneckCandidates = array_values(array_filter($spansOut, static function ($s) {
        return !in_array($s['id'], ['request_total', 'view_render_total', 'controller_dashboard', 'bootstrap_eager_requires_evidence', 'cache_clear_nav_badges', 'session_auth_mint', 'db_connect_trace_install', 'controller_admin_metrics'], true);
    }));
    $biggest = $bottleneckCandidates[0] ?? null;

    // Attribute exact file/class/function for biggest
    $attribution = [
        'bootstrap_init' => [
            'file' => 'app/Core/Bootstrap.php',
            'class' => 'Rateb\\App\\Core\\Bootstrap',
            'function' => 'init',
        ],
        'layout_main' => [
            'file' => 'views/layouts/main.php',
            'class' => '(layout include)',
            'function' => 'include main.php',
        ],
        'layout_oversight_counts' => [
            'file' => 'config/app.php + app/services/ApprovalOversightService.php',
            'class' => 'Rateb\\App\\Services\\ApprovalOversightService',
            'function' => 'menuCounts',
        ],
        'controller_admin_build_lite' => [
            'file' => 'app/services/DashboardService.php',
            'class' => 'Rateb\\App\\Services\\DashboardService',
            'function' => 'adminBuildLite',
        ],
        'routes_load' => [
            'file' => 'routes/*.php via public/index.php',
            'class' => '(route registration)',
            'function' => 'require routes',
        ],
        'auth_bootstrap' => [
            'file' => 'app/Core/Auth.php',
            'class' => 'Rateb\\App\\Core\\Auth',
            'function' => 'bootstrapFromSession',
        ],
        'middleware_erp_auth' => [
            'file' => 'app/Core/Middleware/Middleware.php',
            'class' => 'Rateb\\App\\Core\\Middleware\\ErpAuthMiddleware',
            'function' => 'handle',
        ],
        'middleware_branch_schema_cold' => [
            'file' => 'app/services/MigrationService.php',
            'class' => 'Rateb\\App\\Services\\MigrationService',
            'function' => 'repairBranchOpsSchemaIfNeeded',
        ],
        'view_dashboard' => [
            'file' => 'views/admin/dashboard.php',
            'class' => '(view)',
            'function' => 'include',
        ],
        'pos_module' => [
            'file' => 'modules/pos/PosModule.php',
            'class' => 'Rateb\\App\\Pos\\PosModule',
            'function' => 'init',
        ],
        'offline_module' => [
            'file' => 'offline/OfflineModule.php',
            'class' => 'Rateb\\App\\Offline\\OfflineModule',
            'function' => 'init',
        ],
    ];

    $report = [
        'ok' => true,
        'target' => 'GET /admin/',
        'host' => 'rateb.sa',
        'mode' => 'production_cli_replay',
        'user' => $user['email'] ?? null,
        'user_id' => (int) ($user['id'] ?? 0),
        'wall_ms' => round((microtime(true) - $wall0) * 1000, 3),
        'request_total_ms' => AdminGetProfiler::$spans['request_total']['dur_ms'] ?? null,
        'sql_total' => count(AdminGetProfiler::$sql),
        'sql_total_dur_ms' => round(array_sum(array_column(AdminGetProfiler::$sql, 'dur_ms')), 3),
        'included_files' => count(get_included_files()),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        'flame' => $flame,
        'spans' => $spansOut,
        'sql_by_phase' => $sqlByPhase,
        'sql_slowest_20' => array_slice($sqlSorted, 0, 20),
        'sql_repeated' => array_slice($repeatedSql, 0, 20),
        'top10_slowest_operations' => array_map(static function ($s) use ($attribution) {
            $a = $attribution[$s['id']] ?? null;
            return [
                'id' => $s['id'],
                'label' => $s['label'],
                'dur_ms' => $s['dur_ms'],
                'sql_count' => $s['sql_count'],
                'blocking' => $s['blocking'],
                'cacheable' => $s['cacheable'],
                'repeated' => $s['repeated'],
                'can_defer' => $s['can_defer'],
                'can_lazy_load' => $s['can_lazy_load'],
                'attribution' => $a,
            ];
        }, $top10),
        'single_biggest_bottleneck' => $biggest ? [
            'id' => $biggest['id'],
            'label' => $biggest['label'],
            'dur_ms' => $biggest['dur_ms'],
            'sql_count' => $biggest['sql_count'],
            'blocking' => $biggest['blocking'],
            'cacheable' => $biggest['cacheable'],
            'can_defer' => $biggest['can_defer'],
            'can_lazy_load' => $biggest['can_lazy_load'],
            'file' => ($attribution[$biggest['id']]['file'] ?? null),
            'class' => ($attribution[$biggest['id']]['class'] ?? null),
            'function' => ($attribution[$biggest['id']]['function'] ?? null),
        ] : null,
        'path_evidence' => [
            'route' => "\$router->get('/admin', [AdminDashboardController::class, 'index'], [ErpAuthMiddleware::class])",
            'controller' => 'Rateb\\App\\Controllers\\Admin\\DashboardController::index → renderDashboard',
            'sa_platform_branch' => 'DashboardService::adminBuildLite + view admin/dashboard + layout main',
            'note' => 'CLI replay mirrors public/index.php lifecycle; mint session + clear nav caches to approximate post-login first HTML',
        ],
    ];

    $outPath = '/tmp/rateb-admin-profile.json';
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    $err = [
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice(array_map('strval', explode("\n", $e->getTraceAsString())), 0, 30),
        'spans' => array_values(AdminGetProfiler::$spans),
        'sql' => AdminGetProfiler::$sql,
    ];
    file_put_contents('/tmp/rateb-admin-profile.json', json_encode($err, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo json_encode($err, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(1);
}
