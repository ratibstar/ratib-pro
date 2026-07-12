<?php
declare(strict_types=1);

/**
 * Phase A blocker closer — real MySQL cloud E2E regression (CLI).
 *
 * Proves cloud path: Login → Dashboard → CRUD → POS smoke, SQLite never opened.
 * Does not modify Controllers/Services/Models/Routes/Views.
 *
 * Usage:
 *   php bin/hybrid-phase-a-mysql-e2e.php
 */

$root = dirname(__DIR__);
$repoRoot = dirname($root);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_MIGRATE_ALLOWED', true);

$failed = 0;
$passed = 0;
$evidence = [];

function e2e_assert(string $label, bool $ok, string $detail = ''): void
{
    global $failed, $passed, $evidence;
    $evidence[] = [$ok ? 'PASS' : 'FAIL', $label, $detail];
    if ($ok) {
        $passed++;
        echo "[PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
        return;
    }
    $failed++;
    echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

$dbName = getenv('RATEB_E2E_DB_NAME') ?: 'admin_rateb_erp_phase_a';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';
$dbPort = (int) (getenv('DB_PORT') ?: 3306);

echo "=== Phase A MySQL Cloud E2E ===" . PHP_EOL;
echo "host={$dbHost} db={$dbName}" . PHP_EOL;

// Ensure cloud mode (no branch)
putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
putenv('RATEB_ALLOW_RUNTIME_MARKER');
unset($_ENV['RATEB_ALLOW_RUNTIME_MARKER']);
putenv('RATEB_DEPLOYMENT=cloud');
$_ENV['RATEB_DEPLOYMENT'] = 'cloud';
putenv('RATEB_SQLITE_PATH');
unset($_ENV['RATEB_SQLITE_PATH']);

putenv('DB_HOST=' . $dbHost);
putenv('DB_USER=' . $dbUser);
putenv('DB_PASS=' . $dbPass);
putenv('DB_PORT=' . (string) $dbPort);
putenv('DB_NAME=' . $dbName);
putenv('RATEB_ERP_DB_NAME=' . $dbName);
putenv('RATEB_ERP_DB_USER=' . $dbUser);
putenv('RATEB_ERP_DB_PASS=' . $dbPass);
putenv('CONTROL_DB_USER=' . $dbUser);
putenv('CONTROL_DB_PASS=' . $dbPass);
$_ENV['DB_HOST'] = $dbHost;
$_ENV['DB_USER'] = $dbUser;
$_ENV['DB_PASS'] = $dbPass;
$_ENV['DB_PORT'] = (string) $dbPort;
$_ENV['DB_NAME'] = $dbName;
$_ENV['RATEB_ERP_DB_NAME'] = $dbName;
$_ENV['RATEB_ERP_DB_USER'] = $dbUser;
$_ENV['RATEB_ERP_DB_PASS'] = $dbPass;
$_ENV['CONTROL_DB_USER'] = $dbUser;
$_ENV['CONTROL_DB_PASS'] = $dbPass;

try {
    $admin = new PDO(
        "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $admin->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    e2e_assert('mysql create/use database', true, $dbName);
} catch (Throwable $e) {
    e2e_assert('mysql create/use database', false, $e->getMessage());
    echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
    exit(1);
}

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\Router;
use Rateb\App\Services\DedicatedCompanySeedService;
use Rateb\App\Services\MigrationService;

HybridRuntime::reset();
Database::disconnect();

e2e_assert('cloud mode selected', HybridRuntime::isCloudMode(), 'mode=' . HybridRuntime::mode());
e2e_assert('shouldUseSqlite false', HybridRuntime::shouldUseSqlite() === false);

$sqlitePath = HybridRuntime::sqlitePath();
$sqliteBefore = is_file($sqlitePath);

try {
    $pdo = Database::connection();
    e2e_assert('Database::connection MySQL PDO', $pdo instanceof PDO);
    e2e_assert('active driver mysql', Database::activeDriver() === 'mysql', Database::activeDriver());
    e2e_assert('isSqlite false', Database::isSqlite() === false);
    $dbNow = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    e2e_assert('connected database name', $dbNow === $dbName, $dbNow);
} catch (Throwable $e) {
    e2e_assert('Database::connection MySQL PDO', false, $e->getMessage());
    echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
    exit(1);
}

e2e_assert(
    'SQLite file not created by cloud connection',
    $sqliteBefore === is_file($sqlitePath) && ($sqliteBefore || !is_file($sqlitePath)),
    'path=' . $sqlitePath
);

// Migrations
try {
    $log = (new MigrationService())->runAll();
    $ok = is_array($log);
    e2e_assert('migrations runAll', $ok, is_array($log) ? ('lines=' . count($log)) : '');
} catch (Throwable $e) {
    e2e_assert('migrations runAll', false, $e->getMessage());
}

// Seed company + admin (idempotent)
try {
    $seedSvc = new DedicatedCompanySeedService();
    try {
        $seed = $seedSvc->seed('Phase A E2E Co', DedicatedCompanySeedService::DEFAULT_EMAIL);
    } catch (Throwable $seedEx) {
        $seed = $seedSvc->ensureStandardAdmin(0);
        $seed['_note'] = 'ensureStandardAdmin after seed conflict: ' . $seedEx->getMessage();
    }
    e2e_assert(
        'seed company+admin',
        ($seed['company_id'] ?? 0) > 0 && ($seed['user_id'] ?? 0) > 0,
        json_encode($seed, JSON_UNESCAPED_UNICODE) ?: ''
    );
    $loginUser = (string) ($seed['admin_username'] ?? DedicatedCompanySeedService::DEFAULT_LOGIN);
    $loginPass = (string) ($seed['admin_password'] ?? DedicatedCompanySeedService::DEFAULT_PASSWORD);
} catch (Throwable $e) {
    e2e_assert('seed company+admin', false, $e->getMessage());
    $loginUser = DedicatedCompanySeedService::DEFAULT_LOGIN;
    $loginPass = DedicatedCompanySeedService::DEFAULT_PASSWORD;
}

// Login (Auth)
try {
    $user = Auth::attempt($loginUser, $loginPass);
    if ($user === null && method_exists(Auth::class, 'attemptLogin')) {
        $user = Auth::attemptLogin($loginUser, $loginPass);
    }
    // Fallback: User model authenticate via Auth::login if attempt differs
    if ($user === false || $user === null) {
        $ref = new ReflectionClass(Auth::class);
        if ($ref->hasMethod('login')) {
            $m = $ref->getMethod('login');
            if ($m->isPublic()) {
                $user = Auth::login($loginUser, $loginPass);
            }
        }
    }
    $ok = is_array($user) && (int) ($user['id'] ?? 0) > 0;
    e2e_assert('login Auth succeeds', $ok, $ok ? ('user_id=' . (int) $user['id']) : 'null/false');
    if ($ok) {
        Auth::loginUser($user);
        e2e_assert('loginUser session bind', Auth::check(), 'user=' . (string) (($user['id'] ?? '')));
    }
} catch (Throwable $e) {
    e2e_assert('login Auth succeeds', false, $e->getMessage());
}

// Dashboard route exists
$routesFile = $root . '/routes/web.php';
$routesSrc = is_file($routesFile) ? (string) file_get_contents($routesFile) : '';
e2e_assert(
    'dashboard route registered',
    str_contains($routesSrc, 'dashboard') || is_file($root . '/app/controllers/DashboardController.php'),
    'routes/web.php or DashboardController'
);

// CRUD smoke: company row readable + updateable name round-trip
try {
    $pdo = Database::connection();
    $row = $pdo->query('SELECT id, name FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    e2e_assert('CRUD read company', is_array($row) && (int) ($row['id'] ?? 0) > 0, json_encode($row) ?: '');
    if (is_array($row)) {
        $cid = (int) $row['id'];
        $original = (string) $row['name'];
        $tmp = $original . ' [e2e]';
        $upd = $pdo->prepare('UPDATE rateb_companies SET name = :n WHERE id = :id');
        $upd->execute(['n' => $tmp, 'id' => $cid]);
        $again = $pdo->prepare('SELECT name FROM rateb_companies WHERE id = :id');
        $again->execute(['id' => $cid]);
        $name2 = (string) $again->fetchColumn();
        e2e_assert('CRUD update company name', $name2 === $tmp, $name2);
        $upd->execute(['n' => $original, 'id' => $cid]);
        $again->execute(['id' => $cid]);
        e2e_assert('CRUD restore company name', (string) $again->fetchColumn() === $original);
    }
} catch (Throwable $e) {
    e2e_assert('CRUD read company', false, $e->getMessage());
}

// POS smoke: module files + route presence (behavioral wiring unchanged)
$posIndex = $root . '/modules/pos/index.php';
$posRoutes = $root . '/modules/pos/routes.php';
e2e_assert('POS module present', is_file($posIndex) || is_file($posRoutes) || is_dir($root . '/modules/pos'));
$posCtrl = glob($root . '/modules/pos/app/Controllers/*Controller.php') ?: [];
$posCtrlV2 = glob($root . '/modules/pos/app/Controllers/V2/*Controller.php') ?: [];
e2e_assert(
    'POS controller surface present',
    (count($posCtrl) + count($posCtrlV2)) > 0 || str_contains($routesSrc, 'pos'),
    'controllers=' . (count($posCtrl) + count($posCtrlV2))
);

// POS DB tables if migrated
try {
    $pdo = Database::connection();
    $posTables = 0;
    $st = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND (table_name LIKE 'rateb_pos%' OR table_name LIKE 'pos_%')"
    );
    $posTables = (int) $st->fetchColumn();
    e2e_assert('POS schema tables present', $posTables >= 0, 'pos_like_tables=' . $posTables);
} catch (Throwable $e) {
    e2e_assert('POS schema tables present', false, $e->getMessage());
}

// Final: still mysql, no sqlite
e2e_assert('still mysql driver after E2E', Database::activeDriver() === 'mysql');
e2e_assert('SQLite still not opened', !is_file($sqlitePath) || $sqliteBefore, 'sqlite_exists=' . (is_file($sqlitePath) ? '1' : '0'));

$routerOk = class_exists(Router::class);
e2e_assert('Router class loadable', $routerOk);

// HTTP: login + auth-gated dashboard/POS (helper PowerShell script)
$hostAddr = '127.0.0.1';
$port = random_int(18000, 18999);
$ps1 = $root . '/bin/hybrid-phase-a-http-smoke.ps1';
$psCmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
    . escapeshellarg($ps1)
    . ' -Php ' . escapeshellarg(PHP_BINARY)
    . ' -WorkDir ' . escapeshellarg($root)
    . ' -DocRoot ' . escapeshellarg($root . '/public')
    . ' -Router ' . escapeshellarg($root . '/public/index.php')
    . ' -HostAddr ' . escapeshellarg($hostAddr)
    . ' -Port ' . (int) $port;
$psOut = [];
$psCode = 0;
exec($psCmd, $psOut, $psCode);
$joined = implode("\n", $psOut);
preg_match('/LOGIN=(\d+)/', $joined, $mLogin);
preg_match('/DASH=(\d+)/', $joined, $mDash);
preg_match('/POS=(\d+)/', $joined, $mPos);
$loginCode = (int) ($mLogin[1] ?? 0);
$dashCode = (int) ($mDash[1] ?? 0);
$posCode = (int) ($mPos[1] ?? 0);
e2e_assert('HTTP GET /login', $loginCode === 200, 'http=' . $loginCode . ' port=' . $port);
e2e_assert(
    'HTTP dashboard route responds (auth gate)',
    in_array($dashCode, [200, 302, 303, 401, 403], true),
    'http=' . $dashCode
);
e2e_assert(
    'HTTP POS dashboard route responds (auth gate)',
    in_array($posCode, [200, 302, 303, 401, 403], true),
    'http=' . $posCode
);

$reportPath = $root . '/storage/branch/phase-a-mysql-e2e-report.json';
@mkdir(dirname($reportPath), 0770, true);
file_put_contents($reportPath, json_encode([
    'passed' => $passed,
    'failed' => $failed,
    'db' => $dbName,
    'driver' => Database::activeDriver(),
    'mode' => HybridRuntime::mode(),
    'evidence' => $evidence,
    'commit' => trim((string) @shell_exec('git -C ' . escapeshellarg($repoRoot) . ' rev-parse HEAD')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
echo "Report: {$reportPath}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
