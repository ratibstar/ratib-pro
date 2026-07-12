<?php
declare(strict_types=1);

/**
 * Phase B.2 — extended module probes on Branch SQLite.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b2-module-verify.php
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

$db = HybridRuntime::branchStorageDir() . '/phase-b2-modules.sqlite';
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
    $pdo->beginTransaction();
    $pdo->prepare('SELECT * FROM rateb_inventory WHERE id = :id LIMIT 1 FOR UPDATE')->execute(['id' => 0]);
    $pdo->commit();
    $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_warehouses'");
    $ok('Inventory', true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $ok('Inventory', false, $e->getMessage());
}

try {
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
    $pdo->query('SELECT COUNT(*) FROM rateb_employees');
    $ok('HR', true);
} catch (Throwable $e) {
    $ok('HR', false, $e->getMessage());
}

try {
    $pdo->query('SELECT COUNT(*) FROM rateb_purchase_orders');
    $ok('Procurement', true);
} catch (Throwable $e) {
    $ok('Procurement', false, $e->getMessage());
}

try {
    $pdo->exec('INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (' . (int) $seed['user_id'] . ',1)');
    $pdo->prepare('SELECT id FROM rateb_chart_of_accounts WHERE company_id <=> :cid LIMIT 1')->execute(['cid' => $cid]);
    $ok('RBAC', true);
} catch (Throwable $e) {
    $ok('RBAC', false, $e->getMessage());
}

try {
    $auth = Auth::attempt('admin', BranchSeedService::DEFAULT_PASSWORD);
    $ok('Authentication', is_array($auth));
} catch (Throwable $e) {
    $ok('Authentication', false, $e->getMessage());
}

try {
    $wh = (new \Rateb\App\Services\WarehouseService())->ensureDefaultWarehouse($cid);
    $ok('Warehouse', $wh > 0, 'id=' . $wh);
} catch (Throwable $e) {
    $ok('Warehouse', false, $e->getMessage());
}

try {
    $pdo->beginTransaction();
    $pdo->query('SELECT * FROM rateb_branch_transfers WHERE id = 0 LIMIT 1 FOR UPDATE');
    $pdo->query('SELECT * FROM rateb_employees WHERE id = 0 AND company_id = ' . $cid . ' LIMIT 1 FOR UPDATE');
    $pdo->commit();
    $ok('Transfers', true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $ok('Transfers', false, $e->getMessage());
}

try {
    $branches = $pdo->prepare('SELECT COUNT(*) FROM rateb_branches WHERE company_id = :cid');
    $branches->execute(['cid' => $cid]);
    $ok('Branches', (int) $branches->fetchColumn() >= 1);
} catch (Throwable $e) {
    $ok('Branches', false, $e->getMessage());
}

try {
    // Authorization / FIELD ordering path used by BranchAccessService
    $pdo->query(
        "SELECT r.slug FROM rateb_roles r ORDER BY FIELD(r.slug, 'hq_admin', 'hq_manager', 'branch_manager', 'branch_user', 'company-full-access') ASC LIMIT 5"
    );
    $ok('Authorization', true);
} catch (Throwable $e) {
    $ok('Authorization', false, $e->getMessage());
}

try {
    $pdo->exec("UPDATE rateb_hybrid_meta SET value = value WHERE key = '__no_such__'");
    $ok('CRUD', true);
} catch (Throwable $e) {
    $ok('CRUD', false, $e->getMessage());
}

echo "pass={$pass} fail={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
