<?php
declare(strict_types=1);

/**
 * Phase B.1 — module execution probes on Branch SQLite.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b1-module-verify.php
 */

$root = dirname(__DIR__);
chdir($root);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\BranchSeedService;
use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\TenantContext;

$db = HybridRuntime::branchStorageDir() . '/phase-b1-modules.sqlite';
foreach ([$db, $db . '-wal', $db . '-shm'] as $f) {
    if (is_file($f)) {
        @unlink($f);
    }
}
putenv('RATEB_RUNTIME=branch');
$_ENV['RATEB_RUNTIME'] = 'branch';
putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
$_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
putenv('RATEB_SQLITE_PATH=' . $db);
$_ENV['RATEB_SQLITE_PATH'] = $db;
HybridRuntime::reset();
Database::disconnect();

$pdo = Database::connection();
$seed = BranchSeedService::seedMinimalTenant($pdo);
$user = Auth::attempt('admin', BranchSeedService::DEFAULT_PASSWORD);
Auth::loginUser($user);
$cid = (int) $seed['company_id'];
TenantContext::setCompanyId($cid);

$fail = 0;
$pass = 0;
$ok = static function (string $n, bool $v, string $d = '') use (&$fail, &$pass): void {
    echo ($v ? 'PASS' : 'FAIL') . " | {$n}" . ($d !== '' ? " | {$d}" : '') . PHP_EOL;
    $v ? $pass++ : $fail++;
};

try {
    (new \Rateb\App\Services\DashboardService())->adminMetrics();
    $ok('Dashboard', true);
} catch (Throwable $e) {
    $ok('Dashboard', false, $e->getMessage());
}

try {
    $built = (new \Rateb\App\Services\AccountingDashboardService())->build($cid);
    $ok('Accounting', is_array($built) && isset($built['metrics']));
} catch (Throwable $e) {
    $ok('Accounting', false, $e->getMessage());
}

try {
    $pdo->query("SELECT DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_warehouses'");
    $pdo->query('SELECT COUNT(*) FROM rateb_inventory');
    $ok('Inventory', true);
} catch (Throwable $e) {
    $ok('Inventory', false, $e->getMessage());
}

try {
    $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders'");
    $n = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'rateb_pos%'")->fetchColumn();
    $ok('POS', $n > 0, 'pos_tables=' . $n);
} catch (Throwable $e) {
    $ok('POS', false, $e->getMessage());
}

try {
    $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) c FROM rateb_companies GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
    $ok('Reports', true);
} catch (Throwable $e) {
    $ok('Reports', false, $e->getMessage());
}

try {
    $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_employees'");
    $pdo->query('SELECT COUNT(*) FROM rateb_employees');
    $ok('HR', true);
} catch (Throwable $e) {
    $ok('HR', false, $e->getMessage());
}

try {
    $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders'");
    $pdo->query('SELECT COUNT(*) FROM rateb_purchase_orders');
    $ok('Procurement', true);
} catch (Throwable $e) {
    $ok('Procurement', false, $e->getMessage());
}

try {
    $pdo->exec('INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (' . (int) $seed['user_id'] . ',1)');
    $pdo->exec("DELETE r1 FROM rateb_roles r1 INNER JOIN rateb_roles r2 ON r1.slug=r2.slug WHERE r1.id > r2.id AND 1=0");
    $pdo->prepare('SELECT id FROM rateb_chart_of_accounts WHERE company_id <=> :cid LIMIT 1')->execute(['cid' => $cid]);
    $ok('RBAC_SQL', true);
} catch (Throwable $e) {
    $ok('RBAC_SQL', false, $e->getMessage());
}

echo "pass={$pass} fail={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
