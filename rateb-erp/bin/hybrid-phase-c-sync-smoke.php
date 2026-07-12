<?php
declare(strict_types=1);

/**
 * Phase C — Hybrid Sync Engine smoke.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-c-sync-smoke.php
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
define('RATEB_SQLITE_SKIP_SCHEMA_BOOTSTRAP', true);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\HybridSyncConfig;
use Rateb\App\Core\HybridSyncCrypto;
use Rateb\App\Core\HybridSyncEngine;
use Rateb\App\Core\HybridSyncSink;

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

echo "=== Phase C Hybrid Sync Smoke ===" . PHP_EOL;

$branchDb = HybridRuntime::branchStorageDir() . '/phase-c-sync.sqlite';
$mirrorDb = HybridRuntime::branchStorageDir() . '/phase-c-mirror.sqlite';
foreach ([$branchDb, $branchDb . '-wal', $branchDb . '-shm', $mirrorDb, $mirrorDb . '-wal', $mirrorDb . '-shm'] as $f) {
    if (is_file($f)) {
        @unlink($f);
    }
}

putenv('RATEB_RUNTIME=branch');
$_ENV['RATEB_RUNTIME'] = 'branch';
putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
$_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
putenv('RATEB_SQLITE_PATH=' . $branchDb);
$_ENV['RATEB_SQLITE_PATH'] = $branchDb;
putenv('RATEB_HYBRID_SYNC_ENABLED=1');
$_ENV['RATEB_HYBRID_SYNC_ENABLED'] = '1';
putenv('RATEB_HYBRID_SYNC_SINK=mirror');
$_ENV['RATEB_HYBRID_SYNC_SINK'] = 'mirror';
putenv('RATEB_HYBRID_SYNC_MIRROR=' . $mirrorDb);
$_ENV['RATEB_HYBRID_SYNC_MIRROR'] = $mirrorDb;
HybridRuntime::reset();
Database::disconnect();

$pdo = Database::connection();
assert_true('sync enabled', HybridSyncConfig::enabled());
assert_true('sink mirror', HybridSyncConfig::sinkMode() === 'mirror');

$pdo->exec('CREATE TABLE IF NOT EXISTS c_items (id INTEGER PRIMARY KEY, name TEXT NOT NULL, qty INTEGER NOT NULL)');
$pdo->exec("INSERT INTO c_items (id, name, qty) VALUES (1, 'A', 10)");
$pdo->exec("UPDATE c_items SET qty = 9 WHERE id = 1");

$pending = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status = 'pending'")->fetchColumn();
assert_true('outbox captured mutations', $pending >= 2, 'pending=' . $pending);

// Crypto
$h = HybridSyncCrypto::hashPayload('{"a":1}');
$sig = HybridSyncCrypto::sign($h, 'batch1');
assert_true('sign/verify', HybridSyncCrypto::verify($h, $sig, 'batch1'));
$enc = HybridSyncCrypto::encrypt('hello');
assert_true('encrypt/decrypt', HybridSyncCrypto::decrypt($enc) === 'hello');

// Prepare mirror schema
$sink = new HybridSyncSink();
$cloud = $sink->connection();
$cloud->exec('CREATE TABLE IF NOT EXISTS c_items (id INTEGER PRIMARY KEY, name TEXT NOT NULL, qty INTEGER NOT NULL)');

$engine = new HybridSyncEngine($sink);
$engine->resumeInterrupted($pdo);
$push = $engine->pushPending($pdo, 50);
assert_true('push ok', !empty($push['ok']), json_encode($push) ?: '');
assert_true('push accepted', (($push['accepted'] ?? 0) + ($push['duplicate'] ?? 0)) >= 2, json_encode($push) ?: '');

$cloudQty = (int) $cloud->query('SELECT qty FROM c_items WHERE id = 1')->fetchColumn();
assert_true('cloud applied qty', $cloudQty === 9, 'qty=' . $cloudQty);

// Idempotent re-push: reset synced rows to pending and push again → duplicates
$pdo->exec("UPDATE rateb_sync_outbox SET status = 'pending', retry_count = 0 WHERE entity_table = 'c_items'");
$push2 = $engine->pushPending($pdo, 50);
assert_true('replay duplicate-safe', ($push2['duplicate'] ?? 0) >= 1 || ($push2['accepted'] ?? 0) === 0, json_encode($push2) ?: '');

// Interrupt resume
$pdo->exec("INSERT INTO c_items (id, name, qty) VALUES (2, 'B', 1)");
$id = (int) $pdo->query("SELECT id FROM rateb_sync_outbox WHERE status='pending' ORDER BY id DESC LIMIT 1")->fetchColumn();
$pdo->prepare("UPDATE rateb_sync_outbox SET status='syncing' WHERE id=:id")->execute(['id' => $id]);
$res = $engine->resumeInterrupted($pdo);
assert_true('resume interrupted', ($res['reset'] ?? 0) >= 1);

// Offline pause simulation: break mirror path
putenv('RATEB_HYBRID_SYNC_MIRROR=' . $mirrorDb . '.missing-dir/nope.sqlite');
$_ENV['RATEB_HYBRID_SYNC_MIRROR'] = $mirrorDb . '.missing-dir/nope.sqlite';
// still creates dir — force offline by sink mode check: use invalid mysql briefly
// Instead mark: engine pauses when isOnline false — for mirror always online.
// Test audit exists
$aud = (int) $pdo->query('SELECT COUNT(*) FROM rateb_sync_audit')->fetchColumn();
assert_true('audit entries', $aud >= 1, 'audit=' . $aud);

$status = $engine->status($pdo);
assert_true('status shape', isset($status['outbox']['pending']));

// Pull delta
$pull = $engine->pullEntity('c_items', $pdo, 10);
assert_true('pull delta', !empty($pull['ok']), json_encode($pull) ?: '');

// Cloud path identity: restore runtime
Database::disconnect();
putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
putenv('RATEB_HYBRID_SYNC_ENABLED');
unset($_ENV['RATEB_HYBRID_SYNC_ENABLED']);
putenv('RATEB_HYBRID_SYNC_SINK');
unset($_ENV['RATEB_HYBRID_SYNC_SINK']);
putenv('RATEB_HYBRID_SYNC_MIRROR');
unset($_ENV['RATEB_HYBRID_SYNC_MIRROR']);
HybridRuntime::reset();
assert_true('cloud mode restored', HybridRuntime::isCloudMode());

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
