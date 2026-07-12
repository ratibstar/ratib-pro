<?php
declare(strict_types=1);

/**
 * Phase A Hybrid Core Seam — smoke verification (CLI).
 *
 * Usage (enable SQLite for this process if needed):
 *   php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-a-smoke.php
 *
 * Exit 0 = PASS. Does not touch Controllers/Services/Models/Routes/Views.
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

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

echo "=== Phase A Hybrid Core Seam Smoke ===" . PHP_EOL;

// --- 1) Default cloud mode (MySQL path selected; no SQLite forced) ---
HybridRuntime::reset();
Database::disconnect();
putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
HybridRuntime::reset();

assert_true('default mode is cloud', HybridRuntime::mode() === HybridRuntime::MODE_CLOUD);
assert_true('default shouldUseSqlite is false', HybridRuntime::shouldUseSqlite() === false);
assert_true('default driver is mysql', HybridRuntime::driver() === HybridRuntime::DRIVER_MYSQL);

// --- 2) Branch mode detection ---
HybridRuntime::reset();
Database::disconnect();
putenv('RATEB_RUNTIME=branch');
$_ENV['RATEB_RUNTIME'] = 'branch';
HybridRuntime::reset();

assert_true('RATEB_RUNTIME=branch → branch mode', HybridRuntime::isBranchMode());

$sqliteOk = HybridRuntime::sqliteExtensionAvailable();
assert_true(
    'pdo_sqlite available for branch verification',
    $sqliteOk,
    $sqliteOk ? 'extension loaded' : 'run with: php -d extension=pdo_sqlite -d extension=sqlite3'
);

if (!$sqliteOk) {
    echo PHP_EOL . "Aborting SQLite open tests — enable pdo_sqlite." . PHP_EOL;
    exit(1);
}

assert_true('branch mode shouldUseSqlite', HybridRuntime::shouldUseSqlite());

$smokeDb = HybridRuntime::branchStorageDir() . '/phase-a-smoke.sqlite';
if (is_file($smokeDb)) {
    @unlink($smokeDb);
}
if (is_file($smokeDb . '-wal')) {
    @unlink($smokeDb . '-wal');
}
if (is_file($smokeDb . '-shm')) {
    @unlink($smokeDb . '-shm');
}

putenv('RATEB_SQLITE_PATH=' . $smokeDb);
$_ENV['RATEB_SQLITE_PATH'] = $smokeDb;
HybridRuntime::reset();
Database::disconnect();

$pdo = Database::connection();
assert_true('Database::connection() returns PDO', $pdo instanceof PDO);
assert_true('active driver is sqlite', Database::isSqlite());
assert_true('activeDriver() === sqlite', Database::activeDriver() === 'sqlite');

$journal = (string) $pdo->query('PRAGMA journal_mode')->fetchColumn();
assert_true('WAL mode enabled', strtolower($journal) === 'wal', 'journal_mode=' . $journal);

$tables = SqliteSchemaBootstrap::ensureMinimal($pdo);
assert_true('minimal schema created', in_array('rateb_sync_outbox', $tables, true));

assert_true(
    'tableHasColumn hybrid_meta.key',
    Database::tableHasColumn('rateb_hybrid_meta', 'key')
);
assert_true(
    'tableHasColumn sync_outbox.uuid',
    Database::tableHasColumn('rateb_sync_outbox', 'uuid')
);
assert_true(
    'missing column returns false',
    Database::tableHasColumn('rateb_sync_outbox', 'not_a_real_column') === false
);

$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    random_int(0, 0xffff),
    random_int(0, 0xffff),
    random_int(0, 0xffff),
    random_int(0, 0x0fff) | 0x4000,
    random_int(0, 0x3fff) | 0x8000,
    random_int(0, 0xffff),
    random_int(0, 0xffff),
    random_int(0, 0xffff)
);
$idem = 'phase-a-' . $uuid;
$ins = $pdo->prepare(
    'INSERT INTO rateb_sync_outbox
     (uuid, entity_table, entity_pk, operation, payload_json, version, idempotency_key, occurred_at, status)
     VALUES (:u, :t, :pk, :op, :p, 1, :idem, :occ, :st)'
);
$ins->execute([
    'u' => $uuid,
    't' => 'rateb_hybrid_meta',
    'pk' => 'hybrid_phase',
    'op' => 'UPDATE',
    'p' => '{"value":"A"}',
    'idem' => $idem,
    'occ' => gmdate('c'),
    'st' => 'pending',
]);

$row = $pdo->query(
    "SELECT uuid, status FROM rateb_sync_outbox WHERE idempotency_key = " . $pdo->quote($idem)
)->fetch(PDO::FETCH_ASSOC);
assert_true('outbox row persisted', is_array($row) && ($row['uuid'] ?? '') === $uuid);

// Idempotency: duplicate key must fail
$dupFailed = false;
try {
    $ins->execute([
        'u' => $uuid . '-2',
        't' => 'rateb_hybrid_meta',
        'pk' => 'hybrid_phase',
        'op' => 'UPDATE',
        'p' => '{}',
        'idem' => $idem,
        'occ' => gmdate('c'),
        'st' => 'pending',
    ]);
} catch (Throwable $e) {
    $dupFailed = true;
}
assert_true('idempotency_key unique enforced', $dupFailed);

// --- 3) Restore cloud default after disconnect ---
putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
putenv('RATEB_SQLITE_PATH');
unset($_ENV['RATEB_SQLITE_PATH']);
Database::disconnect();
HybridRuntime::reset();
assert_true('after reset mode is cloud again', HybridRuntime::isCloudMode());
assert_true('after reset shouldUseSqlite false', HybridRuntime::shouldUseSqlite() === false);

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
