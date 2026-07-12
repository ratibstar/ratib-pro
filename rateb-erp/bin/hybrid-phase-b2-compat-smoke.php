<?php
declare(strict_types=1);

/**
 * Phase B.2 — remaining SQLite compat (FOR UPDATE, GET_LOCK, UPDATE JOIN).
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b2-compat-smoke.php
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
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\WarehouseService;

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

echo "=== Phase B.2 SQLite Compat Smoke ===" . PHP_EOL;

$cases = [
    'SELECT * FROM t WHERE id=1 FOR UPDATE' => 'FOR UPDATE',
    'SELECT * FROM t LOCK IN SHARE MODE' => 'LOCK IN SHARE MODE',
    'REPLACE INTO t (id) VALUES (1)' => 'INSERT OR REPLACE',
    'SHOW INDEX FROM rateb_users' => 'pragma_index_list',
    "UPDATE a s INNER JOIN b w ON w.id=s.bid SET s.x=1 WHERE w.y='z'" => 'WHERE rowid IN',
];
foreach ($cases as $in => $expectOrAbsent) {
    $out = SqlDialectAdapter::toSqlite($in);
    if ($expectOrAbsent === 'FOR UPDATE' || $expectOrAbsent === 'LOCK IN SHARE MODE') {
        assert_true('translate strip: ' . $expectOrAbsent, !str_contains(strtoupper($out), $expectOrAbsent), $out);
    } else {
        assert_true('translate: ' . substr($in, 0, 36), str_contains($out, $expectOrAbsent), $out);
    }
}

// CONVERT(UNHEX) — admin-only: must NOT be rewritten (documented exclusion)
$adminSql = "UPDATE t s INNER JOIN u w ON w.id=s.uid SET s.name = CONVERT(UNHEX('41') USING utf8mb4) WHERE w.x=1";
$adminOut = SqlDialectAdapter::toSqlite($adminSql);
assert_true(
    'CONVERT UNHEX left intact (admin-only)',
    str_contains(strtoupper($adminOut), 'CONVERT') && str_contains(strtoupper($adminOut), 'UNHEX'),
    $adminOut
);

putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
HybridRuntime::reset();
Database::disconnect();
assert_true('cloud shouldUseSqlite false', HybridRuntime::shouldUseSqlite() === false);

$smokeDb = HybridRuntime::branchStorageDir() . '/phase-b2-compat.sqlite';
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

$seed = BranchSeedService::seedMinimalTenant($pdo);
$user = Auth::attempt('admin', BranchSeedService::DEFAULT_PASSWORD);
assert_true('login', is_array($user));
Auth::loginUser($user);
$cid = (int) $seed['company_id'];
TenantContext::setCompanyId($cid);

// FOR UPDATE exec
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT * FROM rateb_inventory WHERE id = :id LIMIT 1 FOR UPDATE');
    $st->execute(['id' => 0]);
    $pdo->commit();
    assert_true('exec FOR_UPDATE', true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    assert_true('exec FOR_UPDATE', false, $e->getMessage());
}

// GET_LOCK / RELEASE_LOCK
try {
    $got = (int) $pdo->query("SELECT GET_LOCK('rateb_b2_test', 2)")->fetchColumn();
    $rel = (int) $pdo->query("SELECT RELEASE_LOCK('rateb_b2_test')")->fetchColumn();
    assert_true('exec GET_LOCK', $got === 1, 'got=' . $got);
    assert_true('exec RELEASE_LOCK', $rel === 1, 'rel=' . $rel);
} catch (Throwable $e) {
    assert_true('exec GET_LOCK', false, $e->getMessage());
    assert_true('exec RELEASE_LOCK', false, $e->getMessage());
}

// WarehouseService uses GET_LOCK unchanged
try {
    $whId = (new WarehouseService())->ensureDefaultWarehouse($cid);
    assert_true('WarehouseService::ensureDefaultWarehouse', $whId > 0, 'id=' . $whId);
} catch (Throwable $e) {
    assert_true('WarehouseService::ensureDefaultWarehouse', false, $e->getMessage());
}

// UPDATE JOIN (runtime-safe, no CONVERT)
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS _b2_uj_a (id INTEGER PRIMARY KEY, bid INTEGER, x INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS _b2_uj_b (id INTEGER PRIMARY KEY, y TEXT)');
    $pdo->exec("DELETE FROM _b2_uj_a; DELETE FROM _b2_uj_b;");
    $pdo->exec("INSERT INTO _b2_uj_a(id,bid,x) VALUES (1,10,0)");
    $pdo->exec("INSERT INTO _b2_uj_b(id,y) VALUES (10,'z')");
    $pdo->exec("UPDATE _b2_uj_a s INNER JOIN _b2_uj_b w ON w.id = s.bid SET s.x = 7 WHERE w.y = 'z'");
    $x = (int) $pdo->query('SELECT x FROM _b2_uj_a WHERE id=1')->fetchColumn();
    assert_true('exec UPDATE_JOIN', $x === 7, 'x=' . $x);
} catch (Throwable $e) {
    assert_true('exec UPDATE_JOIN', false, $e->getMessage());
}

// Transfers FOR UPDATE path shape
try {
    $pdo->beginTransaction();
    $pdo->query('SELECT * FROM rateb_branch_transfers WHERE id = 0 LIMIT 1 FOR UPDATE');
    $pdo->commit();
    assert_true('exec transfer FOR_UPDATE shape', true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    assert_true('exec transfer FOR_UPDATE shape', false, $e->getMessage());
}

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
