<?php
declare(strict_types=1);

/**
 * Phase C.1 — Always-On Hybrid Sync Service smoke.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-c1-service-smoke.php
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

echo "=== Phase C.1 Hybrid Sync Service Smoke ===" . PHP_EOL;

$dir = HybridRuntime::branchStorageDir();
$branchDb = $dir . '/phase-c1-service.sqlite';
$mirrorDb = $dir . '/phase-c1-service-mirror.sqlite';
foreach ([$branchDb, $branchDb . '-wal', $branchDb . '-shm', $mirrorDb, $mirrorDb . '-wal', $mirrorDb . '-shm'] as $f) {
    if (is_file($f)) {
        @unlink($f);
    }
}
@unlink($dir . '/hybrid-sync.daemon.lock');
@unlink($dir . '/hybrid-sync.stop');

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
putenv('RATEB_HYBRID_SYNC_PULL_ENTITIES=');
$_ENV['RATEB_HYBRID_SYNC_PULL_ENTITIES'] = '';
HybridRuntime::reset();
Database::disconnect();
HybridSyncOutboxCapture::resetConnection();

$pdo = Database::connection();
$pdo->exec('CREATE TABLE IF NOT EXISTS c1_items (id INTEGER PRIMARY KEY, name TEXT NOT NULL, qty INTEGER NOT NULL)');
$pdo->exec("INSERT INTO c1_items (id, name, qty) VALUES (1, 'X', 5)");
$pdo->exec("UPDATE c1_items SET qty = 4 WHERE id = 1");

$sink = new HybridSyncSink();
$cloud = $sink->connection();
$cloud->exec('CREATE TABLE IF NOT EXISTS c1_items (id INTEGER PRIMARY KEY, name TEXT NOT NULL, qty INTEGER NOT NULL)');

$pending = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status = 'pending'")->fetchColumn();
assert_true('outbox has work', $pending >= 1, 'pending=' . $pending);

$daemon = new HybridSyncDaemon(new HybridSyncEngine($sink));
$code = $daemon->run([
    'max_cycles' => 20,
    'fast' => true,
    'stop_when_idle' => true,
    'pull_entities' => [],
]);
assert_true('daemon exit 0', $code === 0, 'code=' . $code);

$left = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','syncing','failed')")->fetchColumn();
assert_true('outbox drained by daemon', $left === 0, 'left=' . $left);

$log = $dir . '/logs/hybrid-sync.jsonl';
assert_true('structured log exists', is_file($log));
$logBody = (string) file_get_contents($log);
assert_true('log startup', str_contains($logBody, '"event":"startup"'));
assert_true('log push or success', str_contains($logBody, '"event":"push"') || str_contains($logBody, '"event":"success"'));
assert_true('log shutdown', str_contains($logBody, '"event":"shutdown"'));

// Single-instance: hold lock, second run must refuse
$lockPath = $dir . '/hybrid-sync.daemon.lock';
$fh = fopen($lockPath, 'c+');
assert_true('lock open', $fh !== false);
flock($fh, LOCK_EX | LOCK_NB);
$daemon2 = new HybridSyncDaemon(new HybridSyncEngine());
$code2 = $daemon2->run(['max_cycles' => 1, 'fast' => true, 'pull_entities' => []]);
assert_true('second instance refused', $code2 === 3, 'code=' . $code2);
flock($fh, LOCK_UN);
fclose($fh);

// Crash resume: leave rows in syncing, daemon must reset + drain
$pdo->exec("UPDATE c1_items SET qty = 3 WHERE id = 1");
$id = (int) $pdo->query("SELECT id FROM rateb_sync_outbox WHERE status='pending' ORDER BY id DESC LIMIT 1")->fetchColumn();
assert_true('pending row for crash sim', $id > 0, 'id=' . $id);
$pdo->prepare("UPDATE rateb_sync_outbox SET status='syncing' WHERE id=:id")->execute(['id' => $id]);
$daemon3 = new HybridSyncDaemon(new HybridSyncEngine($sink));
$code3 = $daemon3->run(['max_cycles' => 20, 'fast' => true, 'stop_when_idle' => true, 'pull_entities' => []]);
assert_true('resume daemon exit 0', $code3 === 0);
$syncingLeft = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status = 'syncing'")->fetchColumn();
assert_true('no stuck syncing after resume', $syncingLeft === 0, 'syncing=' . $syncingLeft);
$workLeft = (int) $pdo->query("SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','failed')")->fetchColumn();
assert_true('work drained after resume', $workLeft === 0, 'left=' . $workLeft);

$src = (string) file_get_contents($root . '/app/Core/HybridSyncDaemon.php');
assert_true('daemon reuses engine push', str_contains($src, 'pushPending'));
assert_true('daemon reuses engine pull', str_contains($src, 'pullEntity'));
assert_true('daemon reuses resume', str_contains($src, 'resumeInterrupted'));
assert_true('daemon has no INSERT business SQL', !preg_match('/INSERT\s+INTO\s+(?!rateb_)/i', $src));

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
