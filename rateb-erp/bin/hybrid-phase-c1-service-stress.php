<?php
declare(strict_types=1);

/**
 * Phase C.1 — Always-On service stress (volume + lock + interrupt via daemon).
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-c1-service-stress.php
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
define('RATEB_SQLITE_SKIP_SCHEMA_BOOTSTRAP', true);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\HybridSyncDaemon;
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

echo "=== Phase C.1 Hybrid Sync Service Stress ===" . PHP_EOL;

$dir = HybridRuntime::branchStorageDir() . '/phase-c1-stress';
if (!is_dir($dir)) {
    mkdir($dir, 0770, true);
}
@unlink(HybridRuntime::branchStorageDir() . '/hybrid-sync.daemon.lock');
@unlink(HybridRuntime::branchStorageDir() . '/hybrid-sync.stop');

/**
 * @return array{pdo:PDO, engine:HybridSyncEngine}
 */
function c1_setup(string $dir, string $tag): array
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
    $pdo->exec('CREATE TABLE IF NOT EXISTS c1_stock (id INTEGER PRIMARY KEY, sku TEXT NOT NULL, qty INTEGER NOT NULL)');
    $pdo->exec("INSERT INTO c1_stock (id, sku, qty) VALUES (1, 'SKU-C1', 0)");
    $sink = new HybridSyncSink();
    $cloud = $sink->connection();
    $cloud->exec('CREATE TABLE IF NOT EXISTS c1_stock (id INTEGER PRIMARY KEY, sku TEXT NOT NULL, qty INTEGER NOT NULL)');
    $cloud->exec("INSERT OR IGNORE INTO c1_stock (id, sku, qty) VALUES (1, 'SKU-C1', 0)");

    return ['pdo' => $pdo, 'engine' => new HybridSyncEngine($sink)];
}

foreach ([100, 500, 1000] as $n) {
    $pair = c1_setup($dir, "n{$n}");
    $pdo = $pair['pdo'];
    for ($i = 0; $i < $n; $i++) {
        $pdo->exec('UPDATE c1_stock SET qty = qty + 1 WHERE id = 1');
    }
    $pending = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status='pending'")->fetchColumn();
    assert_true("seed {$n} pending", $pending >= $n, "pending={$pending}");

    // Simulate crash mid-batch
    $pdo->exec("UPDATE rateb_sync_outbox SET status='syncing' WHERE status='pending' AND id % 19 = 0");

    $daemon = new HybridSyncDaemon($pair['engine']);
    $code = $daemon->run([
        'max_cycles' => (int) ceil($n / 40) + 20,
        'fast' => true,
        'stop_when_idle' => true,
        'pull_entities' => [],
    ]);
    assert_true("daemon {$n} exit", $code === 0, "code={$code}");

    $left = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','failed','syncing')")->fetchColumn();
    assert_true("daemon {$n} drained", $left === 0, "left={$left}");

    // Duplicate replay must be idempotent
    $pdo->exec("UPDATE rateb_sync_outbox SET status='pending', retry_count=0 WHERE status='synced'");
    $daemonR = new HybridSyncDaemon(new HybridSyncEngine());
    $codeR = $daemonR->run([
        'max_cycles' => (int) ceil($n / 40) + 20,
        'fast' => true,
        'stop_when_idle' => true,
        'pull_entities' => [],
    ]);
    assert_true("replay {$n} exit", $codeR === 0);
    $leftR = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','failed','syncing')")->fetchColumn();
    assert_true("replay {$n} drained", $leftR === 0, "left={$leftR}");
}

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
