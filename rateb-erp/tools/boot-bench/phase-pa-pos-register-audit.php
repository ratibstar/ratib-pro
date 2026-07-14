<?php
declare(strict_types=1);

/**
 * Phase PA — POS register server-side profile (READ ONLY).
 * Usage: php tools/boot-bench/phase-pa-pos-register-audit.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('memory_limit', '512M');

$path = '/admin/ops/pos/register';
$RATEB_ROOT = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
if (!is_dir($RATEB_ROOT)) {
    $RATEB_ROOT = dirname(__DIR__, 2);
}

final class PaProf
{
    public static float $t0;
    /** @var array<string,array<string,mixed>> */
    public static array $spans = [];
    /** @var list<string> */
    public static array $stack = [];
    public static string $phase = 'boot';
    /** @var list<array<string,mixed>> */
    public static array $sql = [];
    public static bool $sqlOn = false;

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
            'sql_before' => count(self::$sql),
            'sql_count' => 0,
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
        $s['sql_count'] = count(self::$sql) - $s['sql_before'];
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
        $file = $line = 0;
        $class = $fn = '';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 28) as $frame) {
            $f = (string) ($frame['file'] ?? '');
            if ($f === '' || str_contains($f, 'phase-pa-pos-register-audit.php')) {
                continue;
            }
            $c = (string) ($frame['class'] ?? '');
            if (str_starts_with($c, 'PaProfile')) {
                continue;
            }
            $file = $f;
            $line = (int) ($frame['line'] ?? 0);
            $class = $c;
            $fn = (string) ($frame['function'] ?? '');
            break;
        }
        $norm = mb_substr(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql, 0, 400);
        $fp = preg_replace('/\b\d+\b/', '?', $norm) ?? $norm;
        $fp = preg_replace("/'[^']*'/", '?', $fp) ?? $fp;
        self::$sql[] = [
            'dur_ms' => round($dur, 4),
            'sql' => $norm,
            'fp' => $fp,
            'file' => $file,
            'line' => $line,
            'class' => $class,
            'function' => $fn,
            'key' => ($class !== '' ? $class . '::' : '') . $fn,
            'phase' => self::$phase,
            'kind' => self::sqlKind($norm),
        ];
    }

    private static function sqlKind(string $sql): string
    {
        $u = strtoupper(ltrim($sql));
        if (str_starts_with($u, 'SHOW COLUMNS')) {
            return 'show_columns';
        }
        if (str_starts_with($u, 'SHOW TABLES')) {
            return 'show_tables';
        }
        if (preg_match('/\bCOUNT\s*\(\s*\*\s*\)/i', $sql)) {
            return 'count_star';
        }
        if (preg_match('/\bSUM\s*\(/i', $sql)) {
            return 'sum';
        }
        if (preg_match('/\bAVG\s*\(/i', $sql)) {
            return 'avg';
        }
        if (preg_match('/tableExists|information_schema/i', $sql)) {
            return 'schema_check';
        }
        return 'query';
    }
}

final class PaProfileStmt extends PDOStatement
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
            PaProf::logSql((string) $this->queryString, (hrtime(true) - $t0) / 1e6);
        }
    }
}

final class PaProfilePdo extends PDO
{
    public function __construct(string $dsn, ?string $user = null, ?string $pass = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $pass, $options ?: []);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PaProfileStmt::class, []]);
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
            PaProf::logSql((string) $query, (hrtime(true) - $t0) / 1e6);
        }
    }
}

PaProf::$t0 = hrtime(true);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_NAME'] = 'rateb.sa';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin/ops/pos/register?company_id=22';
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = is_dir('/home/admin/domains/rateb.sa/public_html')
    ? '/home/admin/domains/rateb.sa/public_html'
    : dirname($RATEB_ROOT);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET = ['company_id' => '22'];

try {
    PaProf::begin('request_total', 'Complete PHP request');
    PaProf::begin('1_php_bootstrap', 'PHP Bootstrap');
    PaProf::begin('bootstrap_init', 'Bootstrap::init');
    require_once $RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($RATEB_ROOT);
    PaProf::end('bootstrap_init');

    PaProf::begin('db_trace_install', 'Install tracing PDO');
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
    $prop->setValue(null, new PaProfilePdo($dsn, $user, $pass));
    PaProf::$sqlOn = true;
    PaProf::end('db_trace_install');
    PaProf::end('1_php_bootstrap');

    PaProf::begin('2_authentication', 'Authentication');
    PaProf::begin('session_auth_mint', 'Auth::loginUser');
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
    $bio = new \Rateb\App\Services\BiometricAuthService();
    $bio->markPosVerified((int) $u['id']);
    PaProf::end('session_auth_mint');

    PaProf::begin('auth_bootstrap', 'Auth::bootstrapFromSession');
    \Rateb\App\Core\SessionManager::set('rateb_branch_schema_ok', date('Y-m-d'));
    \Rateb\App\Core\Auth::bootstrapFromSession();
    PaProf::end('auth_bootstrap');
    PaProf::end('2_authentication');

    PaProf::begin('pos_module', 'PosModule::init');
    $posModule = $RATEB_ROOT . '/modules/pos/PosModule.php';
    if (is_file($posModule)) {
        require_once $posModule;
        \Rateb\App\Pos\PosModule::init();
    }
    PaProf::end('pos_module');

    PaProf::begin('offline_module', 'OfflineModule::init');
    $offlineModule = $RATEB_ROOT . '/offline/OfflineModule.php';
    if (is_file($offlineModule)) {
        require_once $offlineModule;
        \Rateb\App\Offline\OfflineModule::init();
    }
    PaProf::end('offline_module');

    require_once RATEB_ROOT . '/app/helpers/Request.php';

    PaProf::begin('3_tenant', 'Tenant bootstrap (ops company)');
    if (function_exists('rateb_bootstrap_ops_tenant')) {
        rateb_bootstrap_ops_tenant();
    }
    PaProf::end('3_tenant');

    PaProf::begin('routes_load', 'RouteModuleLoader::loadForPath');
    $router = new \Rateb\App\Core\Router();
    \Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
    PaProf::end('routes_load');

    PaProf::begin('4_rbac', 'ErpAuthMiddleware + POS guard');
    $mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
    $ok = $mw->handle();
    if (!$ok) {
        throw new RuntimeException('middleware rejected');
    }
    PaProf::end('4_rbac');

    PaProf::begin('5_controller', 'PosRegisterController::index');
    PaProf::begin('6_pos_dashboard_build', 'registerConfig + view data');
    ob_start();
    $router->dispatch('GET', $path);
    $html = (string) ob_get_clean();
    PaProf::end('6_pos_dashboard_build');
    PaProf::end('5_controller');

    PaProf::end('request_total');

    PaProf::finalizeSelf();

    $total = PaProf::$spans['request_total']['dur_ms'] ?? PaProf::ms();
    $sqlMs = array_sum(array_column(PaProf::$sql, 'dur_ms'));

    $byFn = [];
    foreach (PaProf::$sql as $q) {
        $k = $q['key'] ?: 'unknown';
        if (!isset($byFn[$k])) {
            $byFn[$k] = ['wall_ms' => 0.0, 'calls' => 0, 'file' => $q['file'], 'line' => $q['line'], 'class' => $q['class'], 'function' => $q['function'], 'sample' => $q['sql']];
        }
        $byFn[$k]['wall_ms'] += $q['dur_ms'];
        $byFn[$k]['calls']++;
    }
    uasort($byFn, static fn($a, $b) => $b['wall_ms'] <=> $a['wall_ms']);

    $byFp = [];
    foreach (PaProf::$sql as $q) {
        $fp = $q['fp'];
        if (!isset($byFp[$fp])) {
            $byFp[$fp] = ['n' => 0, 'ms' => 0.0, 'sample' => $q['sql']];
        }
        $byFp[$fp]['n']++;
        $byFp[$fp]['ms'] += $q['dur_ms'];
    }
    uasort($byFp, static fn($a, $b) => $b['ms'] <=> $a['ms']);
    $dupes = array_values(array_filter($byFp, static fn($v) => $v['n'] > 1));
    usort($dupes, static fn($a, $b) => $b['ms'] <=> $a['ms']);

    $slow = PaProf::$sql;
    usort($slow, static fn($a, $b) => $b['dur_ms'] <=> $a['dur_ms']);

    $kinds = [];
    foreach (PaProf::$sql as $q) {
        $k = $q['kind'];
        $kinds[$k] = ($kinds[$k] ?? 0) + 1;
    }

    $stageMap = [
        '1_php_bootstrap' => 'PHP Bootstrap',
        '2_authentication' => 'Authentication',
        '3_tenant' => 'Tenant',
        '4_rbac' => 'RBAC',
        '5_controller' => 'Controller',
        '6_pos_dashboard_build' => 'POS Dashboard Build',
        'routes_load' => 'Routes Load',
        'pos_module' => 'POS Module Init',
        'offline_module' => 'Offline Module Init',
    ];

    $stages = [];
    foreach ($stageMap as $id => $label) {
        if (!isset(PaProf::$spans[$id])) {
            continue;
        }
        $s = PaProf::$spans[$id];
        $stages[] = [
            'id' => $id,
            'label' => $label,
            'wall_ms' => $s['dur_ms'],
            'self_ms' => $s['self_ms'],
            'sql_count' => $s['sql_count'],
            'pct' => $total > 0 ? round(100 * $s['dur_ms'] / $total, 2) : 0,
        ];
    }
    usort($stages, static fn($a, $b) => $b['wall_ms'] <=> $a['wall_ms']);

    $top20 = [];
    $rank = 0;
    foreach ($byFn as $k => $v) {
        $rank++;
        $top20[] = array_merge(['rank' => $rank, 'key' => $k], $v, [
            'pct' => $total > 0 ? round(100 * $v['wall_ms'] / $total, 2) : 0,
        ]);
        if ($rank >= 20) {
            break;
        }
    }

    $biggest = $stages[0] ?? null;
    foreach ($byFn as $k => $v) {
        if ($v['wall_ms'] > ($biggest['wall_ms'] ?? 0)) {
            $biggest = [
                'id' => 'sql_fn',
                'label' => $k,
                'wall_ms' => round($v['wall_ms'], 3),
                'self_ms' => round($v['wall_ms'], 3),
                'file' => $v['file'],
                'class' => $v['class'],
                'function' => $v['function'],
                'line' => $v['line'],
                'calls' => $v['calls'],
                'pct' => $total > 0 ? round(100 * $v['wall_ms'] / $total, 2) : 0,
            ];
        }
    }

    $report = [
        'phase' => 'PA',
        'mode' => 'READ_ONLY_SERVER_PROFILE',
        'path' => $path,
        'measured_at' => gmdate('c'),
        'host' => gethostname(),
        'sapi' => PHP_SAPI,
        'totals' => [
            'wall_ms' => round($total, 3),
            'sql_ms' => round($sqlMs, 3),
            'sql_count' => count(PaProf::$sql),
            'html_bytes' => strlen($html),
            'has_data_pos_register' => (bool) preg_match('/data-pos-register/i', $html),
            'route_count' => $router->routeCount(),
        ],
        'stages' => $stages,
        'sql_kinds' => $kinds,
        'top_50_slow_queries' => array_slice($slow, 0, 50),
        'duplicate_queries' => array_slice($dupes, 0, 30),
        'top_20_functions' => $top20,
        'single_biggest_bottleneck' => $biggest,
        'spans' => array_values(PaProf::$spans),
    ];

    $dir = __DIR__ . '/reports';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $out = $dir . '/phase-pa-pos-register-php-' . gmdate('Ymd-His') . '.json';
    file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo json_encode(['ok' => true, 'out' => $out, 'totals' => $report['totals'], 'biggest' => $biggest], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
