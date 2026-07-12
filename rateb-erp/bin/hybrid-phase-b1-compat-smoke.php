<?php
declare(strict_types=1);

/**
 * Phase B.1 — SQLite compatibility layer smoke + module probes.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b1-compat-smoke.php
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
use Rateb\App\Core\SqlDialectAdapter;
use Rateb\App\Core\SqliteCompatPdo;
use Rateb\App\Services\DashboardService;

$failed = 0;
$passed = 0;
function assert_true(string $l, bool $ok, string $d = ''): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "[PASS] {$l}" . ($d !== '' ? " — {$d}" : '') . PHP_EOL;
        return;
    }
    $failed++;
    echo "[FAIL] {$l}" . ($d !== '' ? " — {$d}" : '') . PHP_EOL;
}

echo "=== Phase B.1 SQLite Compat Smoke ===" . PHP_EOL;

// --- Unit: translator ---
$cases = [
    'SHOW TABLES' => "SELECT name AS",
    "SHOW COLUMNS FROM `rateb_users` LIKE 'email'" => 'pragma_table_info',
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t" => 'sqlite_master',
    "SELECT DATABASE()" => 'branch_sqlite',
    "SELECT DATE_FORMAT(created_at, '%Y-%m') FROM t" => 'strftime',
    "SELECT DATE_ADD(CURDATE(), INTERVAL 30 DAY)" => "datetime(",
    "SELECT DATE_SUB(NOW(), INTERVAL 1 HOUR)" => "datetime(",
    "SELECT CURDATE()" => "date('now')",
    'INSERT IGNORE INTO t (a) VALUES (1)' => 'INSERT OR IGNORE',
    'INSERT INTO t (id,a) VALUES (1,2) ON DUPLICATE KEY UPDATE a=VALUES(a)' => 'ON CONFLICT',
    'DELETE r1 FROM rateb_roles r1 INNER JOIN rateb_roles r2 ON r1.slug=r2.slug WHERE r1.id>r2.id' => 'rowid IN',
];
foreach ($cases as $in => $expect) {
    $out = SqlDialectAdapter::toSqlite($in);
    assert_true('translate: ' . substr($in, 0, 40), str_contains($out, $expect), $out);
}

// MySQL path must not wrap
putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
HybridRuntime::reset();
Database::disconnect();
assert_true('cloud shouldUseSqlite false', HybridRuntime::shouldUseSqlite() === false);

// --- Branch runtime ---
$smokeDb = HybridRuntime::branchStorageDir() . '/phase-b1-compat.sqlite';
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
HybridRuntime::reset();
Database::disconnect();

$pdo = Database::connection();
assert_true('SqliteCompatPdo', $pdo instanceof SqliteCompatPdo);
assert_true('isSqlite', Database::isSqlite());

$seed = BranchSeedService::seedMinimalTenant($pdo);
$user = Auth::attempt('admin', BranchSeedService::DEFAULT_PASSWORD);
assert_true('login', is_array($user));

// Former blockers — must execute
$probes = [
    'ON_DUPLICATE' => "INSERT INTO rateb_hybrid_meta(key,value,updated_at) VALUES('b1','1',datetime('now')) ON DUPLICATE KEY UPDATE value=VALUES(value)",
    'DATE_FORMAT' => "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total FROM rateb_companies GROUP BY DATE_FORMAT(created_at, '%Y-%m')",
    'DATE_ADD' => "SELECT DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
    'DATE_SUB' => "SELECT DATE_SUB(NOW(), INTERVAL 1 DAY)",
    'CURDATE' => 'SELECT CURDATE()',
    'INFORMATION_SCHEMA' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders'",
    'DATABASE' => 'SELECT DATABASE()',
    'SHOW_TABLES' => 'SHOW TABLES',
    'SHOW_COLUMNS' => "SHOW COLUMNS FROM rateb_users LIKE 'email'",
    'INSERT_IGNORE' => 'INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (' . (int) $seed['user_id'] . ',1)',
];
foreach ($probes as $id => $sql) {
    try {
        $pdo->query($sql);
        assert_true('exec ' . $id, true);
    } catch (Throwable $e) {
        assert_true('exec ' . $id, false, $e->getMessage());
    }
}

// DELETE JOIN
try {
    $pdo->exec(
        'DELETE r1 FROM rateb_roles r1
         INNER JOIN rateb_roles r2 ON r1.slug = r2.slug AND r1.company_id = r2.company_id
         WHERE r1.id > r2.id AND r1.slug = \'__no_such_dup_role__\''
    );
    assert_true('exec DELETE_JOIN', true);
} catch (Throwable $e) {
    assert_true('exec DELETE_JOIN', false, $e->getMessage());
}

// Dashboard service (MySQL DATE_FORMAT SQL unchanged in service)
try {
    Auth::loginUser($user);
    $dash = new DashboardService();
    $metrics = $dash->adminMetrics();
    assert_true('DashboardService::adminMetrics', is_array($metrics), json_encode(array_keys($metrics)) ?: '');
} catch (Throwable $e) {
    assert_true('DashboardService::adminMetrics', false, $e->getMessage());
}

// Module schema queries via MySQL idioms
$modules = [
    'POS' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders'",
    'Inventory' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory'",
    'Accounting' => "SELECT COUNT(*) FROM rateb_accounting_currencies",
    'HR' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_employees'",
    'Procurement' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders'",
    'Reports' => "SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) c FROM rateb_companies GROUP BY DATE_FORMAT(created_at, '%Y-%m')",
];
foreach ($modules as $name => $sql) {
    try {
        $n = $pdo->query($sql)->fetch();
        assert_true('module ' . $name, $n !== false);
    } catch (Throwable $e) {
        assert_true('module ' . $name, false, $e->getMessage());
    }
}

// MYSQL_ATTR ignore
assert_true('ignore MYSQL_ATTR_INIT_COMMAND', $pdo->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, 'SET NAMES utf8mb4') === true);

// Restore cloud
Database::disconnect();
putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
putenv('RATEB_ALLOW_RUNTIME_MARKER');
unset($_ENV['RATEB_ALLOW_RUNTIME_MARKER']);
putenv('RATEB_SQLITE_PATH');
unset($_ENV['RATEB_SQLITE_PATH']);
HybridRuntime::reset();
assert_true('cloud restored', HybridRuntime::isCloudMode() && !HybridRuntime::shouldUseSqlite());

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
