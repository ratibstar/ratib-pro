<?php
declare(strict_types=1);

/**
 * Phase B smoke — install to temp DB, schema, seed, Auth login, WAL, cloud untouched.
 *
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b-smoke.php
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\BranchSeedService;
use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\SqliteSchemaBootstrap;

$failed = 0;
$passed = 0;
function assert_true(string $label, bool $ok, string $detail = ''): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "[PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
        return;
    }
    $failed++;
    echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

echo "=== Phase B Branch Foundation Smoke ===" . PHP_EOL;

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "pdo_sqlite required\n");
    exit(1);
}

$smokeDb = HybridRuntime::branchStorageDir() . '/phase-b-smoke.sqlite';
HybridRuntime::ensureBranchStorage();
foreach ([$smokeDb, $smokeDb . '-wal', $smokeDb . '-shm'] as $f) {
    if (is_file($f)) {
        @unlink($f);
    }
}

putenv('RATEB_RUNTIME=branch');
$_ENV['RATEB_RUNTIME'] = 'branch';
putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
$_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
putenv('RATEB_SQLITE_PATH=' . $smokeDb);
$_ENV['RATEB_SQLITE_PATH'] = $smokeDb;
putenv('RATEB_DEPLOYMENT');
unset($_ENV['RATEB_DEPLOYMENT']);
HybridRuntime::reset();
Database::disconnect();

assert_true('branch mode', HybridRuntime::isBranchMode());
assert_true('shouldUseSqlite', HybridRuntime::shouldUseSqlite());

$pdo = Database::connection();
assert_true('connection sqlite', Database::isSqlite());
$journal = strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn());
assert_true('WAL enabled', $journal === 'wal', $journal);

$tables = SqliteSchemaBootstrap::countUserTables($pdo);
assert_true('ERP tables present', $tables >= 100, 'tables=' . $tables);
assert_true('companies table', SqliteSchemaBootstrap::tableExists($pdo, 'rateb_companies'));
assert_true('users table', SqliteSchemaBootstrap::tableExists($pdo, 'rateb_users'));
assert_true('pos orders or terminals', SqliteSchemaBootstrap::tableExists($pdo, 'rateb_pos_orders')
    || SqliteSchemaBootstrap::tableExists($pdo, 'rateb_pos_terminals')
    || SqliteSchemaBootstrap::tableExists($pdo, 'rateb_pos_shifts'));

$seed = BranchSeedService::seedMinimalTenant($pdo);
assert_true('seed user', ($seed['user_id'] ?? 0) > 0, json_encode($seed) ?: '');

$user = Auth::attempt(BranchSeedService::DEFAULT_EMAIL, BranchSeedService::DEFAULT_PASSWORD);
assert_true('Auth::attempt email login', is_array($user) && (int) ($user['id'] ?? 0) > 0);
$user2 = Auth::attempt('admin', BranchSeedService::DEFAULT_PASSWORD);
assert_true('Auth::attempt admin username', is_array($user2) && (int) ($user2['id'] ?? 0) > 0);

$version = SqliteSchemaBootstrap::metaValue($pdo, 'schema_version');
assert_true('schema_version Phase B', $version === SqliteSchemaBootstrap::SCHEMA_VERSION_PHASE_B, (string) $version);

// Restore cloud default
Database::disconnect();
putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
putenv('RATEB_ALLOW_RUNTIME_MARKER');
unset($_ENV['RATEB_ALLOW_RUNTIME_MARKER']);
putenv('RATEB_SQLITE_PATH');
unset($_ENV['RATEB_SQLITE_PATH']);
HybridRuntime::reset();
assert_true('cloud default restored', HybridRuntime::isCloudMode() && !HybridRuntime::shouldUseSqlite());

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
