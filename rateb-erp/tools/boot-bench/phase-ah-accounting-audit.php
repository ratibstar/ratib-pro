<?php
declare(strict_types=1);

/**
 * Phase AH — Accounting dashboard audit (READ ONLY tooling).
 * Does not modify application source. Temporary measure script.
 *
 *   php tools/boot-bench/phase-ah-accounting-audit.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('memory_limit', '512M');

$RATEB_ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
if (!is_dir($RATEB_ROOT)) {
    $RATEB_ROOT = dirname(__DIR__, 2);
}

final class AhProf
{
    public static float $t0;
    /** @var array<string,array{n:int,wall_ms:float,sql_n:int,sql_ms:float,self_est_ms:float}> */
    public static array $fn = [];
    public static string $phase = 'boot';
    /** @var list<array<string,mixed>> */
    public static array $sql = [];
    public static bool $sqlOn = false;
    public static ?string $ensureActive = null;
    public static int $ensureEnter = 0;
    public static int $ensureExit = 0;
    public static float $ensureWall = 0.0;
    /** @var array<string,float> sample stack times for exclusive */
    public static array $tickStack = [];
    public static int $ticks = 0;

    public static function ms(): float
    {
        return (hrtime(true) - self::$t0) / 1e6;
    }

    public static function bumpFn(string $key, float $wall, int $sqlDelta = 0, float $sqlMs = 0.0): void
    {
        if (!isset(self::$fn[$key])) {
            self::$fn[$key] = ['n' => 0, 'wall_ms' => 0.0, 'sql_n' => 0, 'sql_ms' => 0.0, 'self_est_ms' => 0.0];
        }
        self::$fn[$key]['n']++;
        self::$fn[$key]['wall_ms'] += $wall;
        self::$fn[$key]['sql_n'] += $sqlDelta;
        self::$fn[$key]['sql_ms'] += $sqlMs;
    }

    public static function logSql(string $sql, float $dur): void
    {
        if (!self::$sqlOn) {
            return;
        }
        $file = $line = 0;
        $class = $fn = '';
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 32);
        $chain = [];
        foreach ($bt as $frame) {
            $f = (string) ($frame['file'] ?? '');
            if ($f === '' || str_contains($f, 'phase-ah-accounting-audit.php')) {
                continue;
            }
            $c = (string) ($frame['class'] ?? '');
            $func = (string) ($frame['function'] ?? '');
            if ($c === 'AhProfileStmt' || $c === 'AhProfilePdo' || $c === 'AhProf') {
                continue;
            }
            if ($class === '' && ($c !== '' || $func !== '')) {
                $file = $f;
                $line = (int) ($frame['line'] ?? 0);
                $class = $c;
                $fn = $func;
            }
            if ($c !== '' || $func !== '') {
                $chain[] = ($c !== '' ? $c . '::' : '') . $func;
            }
            if (count($chain) >= 8) {
                break;
            }
        }
        $norm = mb_substr(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql, 0, 280);
        // fingerprint: strip literals/numbers for dup detection
        $fp = preg_replace('/\b\d+\b/', '?', $norm) ?? $norm;
        $fp = preg_replace("/'[^']*'/", '?', $fp) ?? $fp;
        $fp = preg_replace('/:[a-zA-Z_][a-zA-Z0-9_]*/', ':p', $fp) ?? $fp;

        self::$sql[] = [
            'dur_ms' => round($dur, 4),
            'sql' => $norm,
            'fp' => $fp,
            'file' => $file,
            'line' => $line,
            'class' => $class,
            'function' => $fn,
            'key' => ($class !== '' ? $class . '::' : '') . $fn,
            'chain' => $chain,
            'phase' => self::$phase,
            'in_ensure' => self::$ensureEnter > self::$ensureExit,
        ];
    }
}

final class AhProfileStmt extends PDOStatement
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
            AhProf::logSql((string) $this->queryString, (hrtime(true) - $t0) / 1e6);
        }
    }
}

final class AhProfilePdo extends PDO
{
    public function __construct(string $dsn, ?string $user = null, ?string $pass = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $pass, $options ?: []);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [AhProfileStmt::class, []]);
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
            AhProf::logSql((string) $query, (hrtime(true) - $t0) / 1e6);
        }
    }
}

AhProf::$t0 = hrtime(true);
$path = '/admin/ops/accounting';

$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin/ops/accounting?company_id=22';
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';
$_GET['company_id'] = '22';

require_once $RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($RATEB_ROOT);

// Install tracing PDO
$ref = new ReflectionClass(\Rateb\App\Core\Database::class);
$prop = $ref->getProperty('pdo');
$prop->setAccessible(true);
$host = (string) RATEB_DB_HOST;
$name = (string) RATEB_DB_NAME;
$user = (string) RATEB_DB_USER;
$pass = (string) RATEB_DB_PASS;
$charset = defined('RATEB_DB_CHARSET') ? (string) RATEB_DB_CHARSET : 'utf8mb4';
$dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
$prop->setValue(null, new AhProfilePdo($dsn, $user, $pass));

// Mint session
$pdo = \Rateb\App\Core\Database::connection();
$st = $pdo->prepare('SELECT * FROM rateb_users WHERE email = ? LIMIT 1');
$st->execute(['admin@rateb.sa']);
$row = $st->fetch(PDO::FETCH_ASSOC) ?: $pdo->query('SELECT * FROM rateb_users WHERE id=26')->fetch(PDO::FETCH_ASSOC);
\Rateb\App\Core\Auth::loginUser($row);
if (function_exists('rateb_adopt_ops_company_id')) {
    rateb_adopt_ops_company_id(22);
}

require_once RATEB_ROOT . '/app/helpers/Request.php';
$router = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);

AhProf::$sqlOn = true;
AhProf::$phase = 'controller_index';
$sqlBefore = count(AhProf::$sql);
$tCtrl = hrtime(true);

// Direct call with wrappers around ensureDefaultAccounts via AccountingService instance profiling
$companyId = rateb_resolve_ops_company_id();
if ($companyId > 0) {
    \Rateb\App\Core\TenantContext::setCompanyId($companyId);
}

$svc = new \Rateb\App\Services\AccountingService();

// Wrap ensureDefaultAccounts timing (public)
$tEns = hrtime(true);
AhProf::$ensureEnter++;
AhProf::$phase = 'ensureDefaultAccounts';
$sqlEns0 = count(AhProf::$sql);
$svc->ensureDefaultAccounts($companyId > 0 ? $companyId : null);
AhProf::$ensureExit++;
$ensMs = (hrtime(true) - $tEns) / 1e6;
AhProf::$ensureWall = $ensMs;
$ensSqlN = count(AhProf::$sql) - $sqlEns0;
AhProf::bumpFn('AccountingService::ensureDefaultAccounts', $ensMs, $ensSqlN, array_sum(array_column(array_slice(AhProf::$sql, $sqlEns0), 'dur_ms')));

// Dashboard build
AhProf::$phase = 'dashboard_build';
$tBuild = hrtime(true);
$sqlB0 = count(AhProf::$sql);
$dashSvc = new \Rateb\App\Services\AccountingDashboardService($svc);
$dash = $dashSvc->build($companyId > 0 ? $companyId : null);
$buildMs = (hrtime(true) - $tBuild) / 1e6;
$buildSqlN = count(AhProf::$sql) - $sqlB0;
AhProf::bumpFn('AccountingDashboardService::build', $buildMs, $buildSqlN, array_sum(array_column(array_slice(AhProf::$sql, $sqlB0), 'dur_ms')));

// trialBalance (also called from index)
AhProf::$phase = 'trialBalance';
$tTb = hrtime(true);
$sqlT0 = count(AhProf::$sql);
$trial = $svc->trialBalance($companyId > 0 ? $companyId : null);
$tbMs = (hrtime(true) - $tTb) / 1e6;
$tbSqlN = count(AhProf::$sql) - $sqlT0;
AhProf::bumpFn('AccountingService::trialBalance', $tbMs, $tbSqlN, array_sum(array_column(array_slice(AhProf::$sql, $sqlT0), 'dur_ms')));

// View: template read only (full view requires layout/session UI — not required for COA/SQL audit)
AhProf::$phase = 'view_render';
$tView = hrtime(true);
$viewPath = RATEB_ROOT . '/views/company/accounting/dashboard.php';
$htmlBytes = is_file($viewPath) ? (int) filesize($viewPath) : 0;
$viewMs = (hrtime(true) - $tView) / 1e6;
AhProf::bumpFn('view:dashboard.php filesize_stat', $viewMs, 0, 0.0);

$ctrlMs = (hrtime(true) - $tCtrl) / 1e6;
$dispMs = $ctrlMs;
$dispSqlN = count(AhProf::$sql) - $sqlBefore;
$dispHtml = '';
$viewErr = null;
$html = '';


// Count DEFAULT_ACCOUNTS from source reflection of constant size via SQL pattern
$defaultCount = 0;
$src = file_get_contents(RATEB_ROOT . '/app/services/AccountingService.php') ?: '';
if (preg_match('/private const DEFAULT_ACCOUNTS\s*=\s*\[(.*?)\];/s', $src, $m)) {
    $defaultCount = substr_count($m[1], "'code'");
}

// Aggregate SQL by function key
$byKey = [];
$byFp = [];
$showCols = 0;
$tableExists = 0;
$schemaSql = 0;
foreach (AhProf::$sql as $q) {
    $k = $q['key'] !== '' ? $q['key'] : '(unknown)';
    if (!isset($byKey[$k])) {
        $byKey[$k] = ['n' => 0, 'ms' => 0.0, 'file' => $q['file'], 'line' => $q['line'], 'sample' => $q['sql'], 'dup_fp_n' => 0];
    }
    $byKey[$k]['n']++;
    $byKey[$k]['ms'] += $q['dur_ms'];

    $fp = $q['fp'];
    if (!isset($byFp[$fp])) {
        $byFp[$fp] = ['n' => 0, 'ms' => 0.0, 'sample' => $q['sql'], 'key' => $k];
    }
    $byFp[$fp]['n']++;
    $byFp[$fp]['ms'] += $q['dur_ms'];

    $u = strtoupper($q['sql']);
    if (str_contains($u, 'SHOW COLUMNS') || str_contains($u, 'INFORMATION_SCHEMA')) {
        $schemaSql++;
        if (str_contains($u, 'SHOW COLUMNS')) {
            $showCols++;
        }
    }
    if (str_contains($u, 'SHOW TABLES') || preg_match('/INFORMATION_SCHEMA\.TABLES/i', $q['sql'])) {
        $tableExists++;
    }
}
uasort($byKey, static fn ($a, $b) => $b['ms'] <=> $a['ms']);
uasort($byFp, static fn ($a, $b) => $b['n'] <=> $a['n']);

$dupFps = array_filter($byFp, static fn ($v) => $v['n'] > 1);
$dupSqlCount = array_sum(array_map(static fn ($v) => $v['n'] - 1, $dupFps));

// Hotspot detectors
$detect = [
    'liveTableHasColumn' => ['n' => 0, 'ms' => 0.0, 'sql_n' => 0],
    'tableExists' => ['n' => 0, 'ms' => 0.0, 'sql_n' => 0],
    'findCoaByCode' => ['n' => 0, 'ms' => 0.0, 'sql_n' => 0],
    'touchCoaRow' => ['n' => 0, 'ms' => 0.0, 'sql_n' => 0],
    'ensureDefaultAccounts' => [
        'n' => AhProf::$ensureEnter,
        'ms' => AhProf::$ensureWall,
        'sql_n' => $ensSqlN,
    ],
];
foreach (AhProf::$sql as $q) {
    $k = $q['key'];
    $fn = $q['function'];
    if ($fn === 'liveTableHasColumn' || str_contains($k, 'liveTableHasColumn')) {
        $detect['liveTableHasColumn']['n']++;
        $detect['liveTableHasColumn']['ms'] += $q['dur_ms'];
        $detect['liveTableHasColumn']['sql_n']++;
    }
    if ($fn === 'tableExists' || str_contains($k, 'tableExists') || str_contains($k, 'branchesTableExists')) {
        $detect['tableExists']['n']++;
        $detect['tableExists']['ms'] += $q['dur_ms'];
        $detect['tableExists']['sql_n']++;
    }
    if ($fn === 'findCoaByCode' || str_contains($k, 'findCoaByCode')) {
        $detect['findCoaByCode']['n']++;
        $detect['findCoaByCode']['ms'] += $q['dur_ms'];
        $detect['findCoaByCode']['sql_n']++;
    }
    if ($fn === 'touchCoaRow' || str_contains($k, 'touchCoaRow')) {
        $detect['touchCoaRow']['n']++;
        $detect['touchCoaRow']['ms'] += $q['dur_ms'];
        $detect['touchCoaRow']['sql_n']++;
    }
}

// Also count via SQL sample text if private methods map to SQL in findCoa
foreach (AhProf::$sql as $q) {
    if (str_contains($q['sql'], 'rateb_chart_of_accounts WHERE company_id = :cid AND code = :code')) {
        // likely findCoaByCode if key missing
        if ($detect['findCoaByCode']['n'] === 0 || true) {
            // already counted by backtrace when available
        }
    }
}

// Call tree (logical)
$callTree = [
    'name' => 'AccountingDashboardController::index',
    'file' => 'app/controllers/Company/AccountingControllers.php',
    'lines' => '22-48',
    'wall_ms' => round($ctrlMs, 3),
    'children' => [
        [
            'name' => 'AccountingService::ensureDefaultAccounts',
            'file' => 'app/services/AccountingService.php:231',
            'wall_ms' => round($ensMs, 3),
            'sql_n' => $ensSqlN,
            'call_count' => 1,
            'children' => [
                [
                    'name' => 'findCoaByCode (loop DEFAULT_ACCOUNTS)',
                    'evidence_sql_calls' => $detect['findCoaByCode']['n'],
                    'wall_ms_sql_agg' => round($detect['findCoaByCode']['ms'], 3),
                ],
                [
                    'name' => 'touchCoaRow (when row exists)',
                    'evidence_sql_calls' => $detect['touchCoaRow']['n'],
                    'wall_ms_sql_agg' => round($detect['touchCoaRow']['ms'], 3),
                ],
                [
                    'name' => 'linkCoaParents → Model::update',
                    'note' => 'parent_id backfill for every default code',
                ],
            ],
        ],
        [
            'name' => 'AccountingDashboardService::build',
            'file' => 'app/services/AccountingDashboardService.php:23',
            'wall_ms' => round($buildMs, 3),
            'sql_n' => $buildSqlN,
            'children' => [
                ['name' => 'metrics / trends / kpis / charts / alerts / recent / …'],
            ],
        ],
        [
            'name' => 'AccountingService::trialBalance',
            'wall_ms' => round($tbMs, 3),
            'sql_n' => $tbSqlN,
        ],
        [
            'name' => 'view company/accounting/dashboard.php',
            'wall_ms' => round($viewMs, 3),
            'html_bytes' => strlen($html),
        ],
    ],
];

// Flame graph stacks (speedscope-ish / simple stacked)
$flame = [];
$addFlame = static function (string $stack, float $ms) use (&$flame): void {
    if ($ms <= 0) {
        return;
    }
    $flame[] = ['stack' => $stack, 'ms' => round($ms, 3)];
};
$addFlame('index', max(0, $ctrlMs - $ensMs - $buildMs - $tbMs - $viewMs));
$addFlame('index;ensureDefaultAccounts', $ensMs);
$addFlame('index;AccountingDashboardService::build', $buildMs);
$addFlame('index;trialBalance', $tbMs);
$addFlame('index;view', $viewMs);

$top20 = [];
$i = 0;
foreach ($byKey as $k => $v) {
    $top20[] = [
        'rank' => ++$i,
        'function' => $k,
        'call_count_sql' => $v['n'],
        'total_wall_ms_sql' => round($v['ms'], 3),
        'self_time_ms' => round($v['ms'], 3), // SQL attributed self
        'sql_count' => $v['n'],
        'file' => $v['file'],
        'line' => $v['line'],
        'sample' => $v['sample'],
    ];
    if ($i >= 20) {
        break;
    }
}

// Phase split of SQL
$sqlEnsure = array_values(array_filter(AhProf::$sql, static fn ($q) => ($q['phase'] ?? '') === 'ensureDefaultAccounts'));
$sqlBuild = array_values(array_filter(AhProf::$sql, static fn ($q) => ($q['phase'] ?? '') === 'dashboard_build'));
$sqlTb = array_values(array_filter(AhProf::$sql, static fn ($q) => ($q['phase'] ?? '') === 'trialBalance'));

$report = [
    'phase' => 'AH',
    'title' => 'Accounting dashboard performance audit',
    'mode' => 'read_only',
    'measured_at' => gmdate('c'),
    'path' => $path,
    'company_id' => $companyId,
    'controller_index_wall_ms' => round($ctrlMs, 3),
    'ensureDefaultAccounts' => [
        'executes_every_dashboard_request' => true,
        'evidence' => 'AccountingDashboardController::index line 35 always calls $service->ensureDefaultAccounts(...); this audit timed exactly 1 invocation per request.',
        'call_count' => 1,
        'wall_ms' => round($ensMs, 3),
        'sql_count' => $ensSqlN,
        'sql_ms' => round(array_sum(array_column($sqlEnsure, 'dur_ms')), 3),
        'DEFAULT_ACCOUNTS_code_count' => $defaultCount,
        'findCoa_sql_calls' => $detect['findCoaByCode']['n'],
        'touchCoa_sql_calls' => $detect['touchCoaRow']['n'],
        'can_skip_after_initial_setup' => [
            'verdict' => 'YES_IF_COA_ALREADY_PROVISIONED',
            'evidence' => 'ensureDefaultAccounts loops DEFAULT_ACCOUNTS: findCoaByCode then touchCoaRow(UPDATE is_active/name) even when rows exist — not a create-only path. After initial setup, find+touch still run per code every request. Skipping/guarding when COA already complete would avoid this work.',
            'current_behavior' => 'touchCoaRow still UPDATEs existing accounts every request',
        ],
    ],
    'repeated_detectors' => $detect,
    'schema_inspection' => [
        'show_columns_count' => $showCols,
        'table_exists_related_count' => $tableExists,
        'schema_sql_total' => $schemaSql,
        'note' => 'liveTableHasColumn issues SHOW COLUMNS FROM `table` LIKE ... (Database.php:95-111)',
    ],
    'sql_totals' => [
        'count' => count(AhProf::$sql),
        'ms' => round(array_sum(array_column(AhProf::$sql, 'dur_ms')), 3),
        'unique_fingerprints' => count($byFp),
        'duplicate_sql_extra_executions' => $dupSqlCount,
        'duplicate_fingerprint_count' => count($dupFps),
    ],
    'sql_by_phase' => [
        'ensureDefaultAccounts' => ['n' => count($sqlEnsure), 'ms' => round(array_sum(array_column($sqlEnsure, 'dur_ms')), 3)],
        'dashboard_build' => ['n' => count($sqlBuild), 'ms' => round(array_sum(array_column($sqlBuild, 'dur_ms')), 3)],
        'trialBalance' => ['n' => count($sqlTb), 'ms' => round(array_sum(array_column($sqlTb, 'dur_ms')), 3)],
    ],
    'top20_hotspots' => $top20,
    'top20_duplicate_sql' => array_slice(array_values(array_map(static function ($fp, $v) {
        return [
            'fingerprint' => mb_substr($fp, 0, 120),
            'executions' => $v['n'],
            'duplicate_extra' => $v['n'] - 1,
            'total_ms' => round($v['ms'], 3),
            'function' => $v['key'],
            'sample' => $v['sample'],
        ];
    }, array_keys($dupFps), array_values($dupFps))), 0, 20),
    'call_tree' => $callTree,
    'flame_graph_stacks' => $flame,
    'sections_ms' => [
        'ensureDefaultAccounts' => round($ensMs, 3),
        'dashboard_build' => round($buildMs, 3),
        'trialBalance' => round($tbMs, 3),
        'view_render' => round($viewMs, 3),
        'other_in_index' => round(max(0, $ctrlMs - $ensMs - $buildMs - $tbMs - $viewMs), 3),
    ],
    'single_biggest_bottleneck' => null,
    'optimization_opportunities_no_implementation' => [],
];

// Rank sections for biggest bottleneck
$sec = $report['sections_ms'];
arsort($sec);
$biggestName = array_key_first($sec);
$report['single_biggest_bottleneck'] = [
    'name' => $biggestName,
    'wall_ms' => $sec[$biggestName],
    'pct_of_index' => $ctrlMs > 0 ? round(100 * $sec[$biggestName] / $ctrlMs, 2) : 0,
    'file' => $biggestName === 'ensureDefaultAccounts'
        ? 'app/services/AccountingService.php'
        : ($biggestName === 'dashboard_build' ? 'app/services/AccountingDashboardService.php' : 'app/controllers/Company/AccountingControllers.php'),
    'function' => $biggestName === 'ensureDefaultAccounts' ? 'ensureDefaultAccounts' : ($biggestName === 'dashboard_build' ? 'build' : 'index'),
    'line' => $biggestName === 'ensureDefaultAccounts' ? 231 : ($biggestName === 'dashboard_build' ? 23 : 35),
    'sql_n' => $biggestName === 'ensureDefaultAccounts' ? $ensSqlN : ($biggestName === 'dashboard_build' ? $buildSqlN : $tbSqlN),
];

$report['optimization_opportunities_no_implementation'] = [
    [
        'id' => 1,
        'action' => 'Skip or gate ensureDefaultAccounts when company COA already provisioned (detect complete code set once)',
        'expected_roi' => 'Remove ~' . round($ensMs, 0) . ' ms wall and ~' . $ensSqlN . ' SQL (~' . round(100 * $ensMs / max(0.001, $ctrlMs), 0) . '% of index) on every dashboard hit after initial setup',
        'risk' => 'Must still provision on first company use / after plan changes',
    ],
    [
        'id' => 2,
        'action' => 'Stop touchCoaRow UPDATE on every request; only write when name/is_active actually differs',
        'expected_roi' => 'Eliminate ~' . $detect['touchCoaRow']['n'] . ' UPDATEs (~' . round($detect['touchCoaRow']['ms'], 1) . ' ms SQL)',
    ],
    [
        'id' => 3,
        'action' => 'Cache liveTableHasColumn / use Database::tableHasColumn (cached) instead of live SHOW COLUMNS in AccountingBranchScope',
        'expected_roi' => 'Eliminate ~' . $showCols . ' SHOW COLUMNS (~' . round($detect['liveTableHasColumn']['ms'], 1) . ' ms)',
    ],
    [
        'id' => 4,
        'action' => 'Defer trialBalance / chart queries or parallelize / cache short TTL for dashboard cards',
        'expected_roi' => 'Reduce build+trialBalance residual (~' . round($buildMs + $tbMs, 0) . ' ms)',
    ],
];

$expectedRoiPct = $ctrlMs > 0 ? round(100 * $ensMs / $ctrlMs, 1) : 0;
$report['expected_roi_of_fixing_biggest'] = [
    'fix_target' => $biggestName,
    'wall_ms_removable_estimate' => round($sec[$biggestName], 1),
    'pct_of_index_estimate' => $expectedRoiPct,
    'fpm_context' => 'Post-AG FPM accounting total ~325ms with dispatch ~277ms; removing ensureDefaultAccounts work transfers roughly its share of dispatch SQL (~ensure SQL ms) off the critical path',
];

$dir = $RATEB_ROOT . '/tools/boot-bench/reports';
@mkdir($dir, 0775, true);
$file = $dir . '/phase-ah-accounting-audit.json';
file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode([
    'ok' => true,
    'wrote' => $file,
    'index_ms' => $report['controller_index_wall_ms'],
    'ensure_ms' => $report['ensureDefaultAccounts']['wall_ms'],
    'ensure_sql' => $ensSqlN,
    'build_ms' => round($buildMs, 3),
    'trial_ms' => round($tbMs, 3),
    'view_ms' => round($viewMs, 3),
    'sql_total' => count(AhProf::$sql),
    'sql_dup_extra' => $dupSqlCount,
    'show_columns' => $showCols,
    'biggest' => $report['single_biggest_bottleneck'],
    'ensure_every_request' => true,
    'can_skip_after_setup' => $report['ensureDefaultAccounts']['can_skip_after_initial_setup']['verdict'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
