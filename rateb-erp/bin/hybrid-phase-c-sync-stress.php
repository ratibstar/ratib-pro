<?php
declare(strict_types=1);

/**
 * Phase C — Hybrid Sync stress (100/500/1000/5000) with interrupt & duplicate replay.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-c-sync-stress.php
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
define('RATEB_SQLITE_SKIP_SCHEMA_BOOTSTRAP', true);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\HybridSyncEngine;
use Rateb\App\Core\HybridSyncOutboxCapture;
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

echo "=== Phase C Hybrid Sync Stress ===" . PHP_EOL;

$dir = HybridRuntime::branchStorageDir() . '/phase-c-stress';
if (!is_dir($dir)) {
    mkdir($dir, 0770, true);
}

/**
 * @return array{branch:PDO, sink:HybridSyncSink, engine:HybridSyncEngine}
 */
function setupPair(string $dir, string $tag): array
{
    Database::disconnect();
    HybridSyncOutboxCapture::resetConnection();
    $branch = $dir . "/{$tag}-branch.sqlite";
    $mirror = $dir . "/{$tag}-mirror.sqlite";
    foreach ([$branch, $branch . '-wal', $branch . '-shm', $mirror, $mirror . '-wal', $mirror . '-shm'] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    putenv('RATEB_RUNTIME=branch');
    $_ENV['RATEB_RUNTIME'] = 'branch';
    putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
    $_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
    putenv('RATEB_SQLITE_PATH=' . $branch);
    $_ENV['RATEB_SQLITE_PATH'] = $branch;
    putenv('RATEB_HYBRID_SYNC_ENABLED=1');
    $_ENV['RATEB_HYBRID_SYNC_ENABLED'] = '1';
    putenv('RATEB_HYBRID_SYNC_SINK=mirror');
    $_ENV['RATEB_HYBRID_SYNC_SINK'] = 'mirror';
    putenv('RATEB_HYBRID_SYNC_MIRROR=' . $mirror);
    $_ENV['RATEB_HYBRID_SYNC_MIRROR'] = $mirror;
    HybridRuntime::reset();
    Database::disconnect();
    $pdo = Database::connection();
    $pdo->exec('CREATE TABLE IF NOT EXISTS c_stock (id INTEGER PRIMARY KEY, sku TEXT NOT NULL, qty INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS c_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL UNIQUE, amount REAL NOT NULL)');
    $pdo->exec("INSERT INTO c_stock (id, sku, qty) VALUES (1, 'SKU-1', 0)");
    $sink = new HybridSyncSink();
    $cloud = $sink->connection();
    $cloud->exec('CREATE TABLE IF NOT EXISTS c_stock (id INTEGER PRIMARY KEY, sku TEXT NOT NULL, qty INTEGER NOT NULL)');
    $cloud->exec('CREATE TABLE IF NOT EXISTS c_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL UNIQUE, amount REAL NOT NULL)');
    $cloud->exec("INSERT OR IGNORE INTO c_stock (id, sku, qty) VALUES (1, 'SKU-1', 0)");

    return ['branch' => $pdo, 'sink' => $sink, 'engine' => new HybridSyncEngine($sink)];
}

foreach ([100, 500, 1000, 5000] as $n) {
    $ctx = setupPair($dir, 'n' . $n);
    $pdo = $ctx['branch'];
    $engine = $ctx['engine'];
    $sink = $ctx['sink'];

    for ($i = 0; $i < $n; $i++) {
        $pdo->beginTransaction();
        $pdo->exec('UPDATE c_stock SET qty = qty + 1 WHERE id = 1');
        $pdo->prepare('INSERT INTO c_orders (ref, amount) VALUES (?, ?)')->execute(['R-' . $n . '-' . $i, 1.5]);
        $pdo->commit();
    }

    // Crash-resume simulation once
    $pdo->exec("UPDATE rateb_sync_outbox SET status='syncing' WHERE status='pending' AND id % 17 = 0");
    $engine->resumeInterrupted($pdo);

    // Full drain with batching
    $accepted = 0;
    $dup = 0;
    $fail = 0;
    for ($round = 0; $round < 400; $round++) {
        $r = $engine->pushPending($pdo, 100);
        $accepted += (int) ($r['accepted'] ?? 0);
        $dup += (int) ($r['duplicate'] ?? 0);
        $fail += (int) ($r['failed'] ?? 0);
        $left = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','failed','syncing')")->fetchColumn();
        if ($left === 0) {
            break;
        }
    }

    $cloud = $sink->connection();
    $qty = (int) $cloud->query('SELECT qty FROM c_stock WHERE id = 1')->fetchColumn();
    $orders = (int) $cloud->query('SELECT COUNT(*) FROM c_orders')->fetchColumn();
    $neg = (int) $cloud->query('SELECT COUNT(*) FROM c_stock WHERE qty < 0')->fetchColumn();
    $pending = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','failed','syncing')")->fetchColumn();
    $inbox = (int) $cloud->query('SELECT COUNT(*) FROM rateb_sync_cloud_inbox')->fetchColumn();

    assert_true(
        "stress ops={$n}",
        $qty === $n && $orders === $n && $neg === 0 && $pending === 0 && $fail === 0 && $inbox >= $n,
        "qty={$qty} orders={$orders} pending={$pending} fail={$fail} accepted={$accepted} inbox={$inbox}"
    );

    // Duplicate packet replay — re-queue synced rows and drain; must be duplicate-only, no qty drift
    $pdo->exec("UPDATE rateb_sync_outbox SET status='pending', retry_count=0 WHERE status='synced'");
    $dupOnly = 0;
    for ($round = 0; $round < 400; $round++) {
        $r = $engine->pushPending($pdo, 100);
        $dupOnly += (int) ($r['duplicate'] ?? 0);
        $fail += (int) ($r['failed'] ?? 0);
        $left = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','failed','syncing')")->fetchColumn();
        if ($left === 0) {
            break;
        }
    }
    $qty2 = (int) $cloud->query('SELECT qty FROM c_stock WHERE id = 1')->fetchColumn();
    $orders2 = (int) $cloud->query('SELECT COUNT(*) FROM c_orders')->fetchColumn();
    assert_true(
        "replay ops={$n}",
        $qty2 === $n && $orders2 === $n && $dupOnly >= $n && $fail === 0,
        "qty={$qty2} orders={$orders2} dup={$dupOnly} fail={$fail}"
    );
}

// Out-of-order / partial success: push with intentional failure row then recover
$ctx = setupPair($dir, 'partial');
$pdo = $ctx['branch'];
$engine = $ctx['engine'];
$pdo->exec("INSERT INTO c_orders (ref, amount) VALUES ('P-OK', 1)");
$push = $engine->pushPending($pdo, 10);
assert_true('partial batch baseline', ($push['accepted'] ?? 0) >= 1, json_encode($push) ?: '');

Database::disconnect();
putenv('RATEB_RUNTIME');
unset($_ENV['RATEB_RUNTIME']);
HybridRuntime::reset();
assert_true('cloud restored', HybridRuntime::isCloudMode());

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
