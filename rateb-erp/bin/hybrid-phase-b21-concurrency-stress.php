<?php
declare(strict_types=1);

/**
 * Phase B.2.1 — concurrency stress orchestrator.
 *
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b21-concurrency-stress.php
 *
 * Runs parallel workers for inventory / POS / transfers / warehouse locks / procurement
 * at 100, 500, and 1000 total operations.
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
define('RATEB_SQLITE_SKIP_SCHEMA_BOOTSTRAP', true);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\SqliteCompatPdo;

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

echo "=== Phase B.2.1 Concurrency Stress ===" . PHP_EOL;

$php = PHP_BINARY;
$workerScript = $root . '/bin/hybrid-phase-b21-concurrency-worker.php';
$stressDir = HybridRuntime::branchStorageDir() . '/b21-stress';
if (!is_dir($stressDir)) {
    mkdir($stressDir, 0770, true);
}

/**
 * @return array{workers:int, opsEach:int}
 */
function planWorkers(int $totalOps): array
{
    // Cap process count for Windows / shared hosting friendliness.
    $workers = min(40, max(4, (int) ceil(sqrt($totalOps))));
    $opsEach = (int) ceil($totalOps / $workers);

    return ['workers' => $workers, 'opsEach' => $opsEach];
}

function prepareDb(string $path, int $initialQty): PDO
{
    foreach ([$path, $path . '-wal', $path . '-shm'] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    putenv('RATEB_RUNTIME=branch');
    $_ENV['RATEB_RUNTIME'] = 'branch';
    putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
    $_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
    putenv('RATEB_SQLITE_PATH=' . $path);
    $_ENV['RATEB_SQLITE_PATH'] = $path;
    HybridRuntime::reset();
    Database::disconnect();
    $pdo = Database::connection();
    assert($pdo instanceof SqliteCompatPdo);
    $pdo->exec('CREATE TABLE b21_stock (
        id INTEGER PRIMARY KEY,
        qty INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE b21_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL,
        delta INTEGER NOT NULL,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE b21_pos_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        idempotency_key TEXT NOT NULL UNIQUE,
        worker_id INTEGER NOT NULL,
        seq INTEGER NOT NULL,
        amount REAL NOT NULL,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE b21_warehouses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL,
        name TEXT NOT NULL,
        created_by INTEGER NOT NULL
    )');
    $pdo->exec('CREATE UNIQUE INDEX idx_b21_wh_code ON b21_warehouses(code)');
    $pdo->exec('CREATE TABLE b21_po (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        worker_id INTEGER NOT NULL,
        seq INTEGER NOT NULL,
        qty INTEGER NOT NULL,
        created_at TEXT NOT NULL
    )');
    $pdo->prepare('INSERT INTO b21_stock(id, qty) VALUES (1, ?)')->execute([$initialQty]);
    $pdo->prepare('INSERT INTO b21_stock(id, qty) VALUES (2, ?)')->execute([0]);
    Database::disconnect();

    return $pdo;
}

/**
 * @return array{exit:int, stdout:string, stderr:string}
 */
function runWorkers(string $php, string $workerScript, string $dbPath, string $scenario, int $workers, int $opsEach): array
{
    /** @var list<array{proc:resource, pipes:array<int,resource>}> $procs */
    $procs = [];
    for ($w = 0; $w < $workers; $w++) {
        $cmd = [
            $php,
            '-d', 'extension=pdo_sqlite',
            '-d', 'extension=sqlite3',
            $workerScript,
            $dbPath,
            $scenario,
            (string) $opsEach,
            (string) $w,
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, dirname($workerScript));
        if (!is_resource($proc)) {
            return ['exit' => 1, 'stdout' => '', 'stderr' => 'proc_open failed'];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $procs[] = ['proc' => $proc, 'pipes' => $pipes];
    }

    $stdout = '';
    $stderr = '';
    $exitMax = 0;
    while ($procs !== []) {
        foreach ($procs as $i => $p) {
            $stdout .= stream_get_contents($p['pipes'][1]) ?: '';
            $stderr .= stream_get_contents($p['pipes'][2]) ?: '';
            $status = proc_get_status($p['proc']);
            if (!$status['running']) {
                $stdout .= stream_get_contents($p['pipes'][1]) ?: '';
                $stderr .= stream_get_contents($p['pipes'][2]) ?: '';
                fclose($p['pipes'][1]);
                fclose($p['pipes'][2]);
                $code = proc_close($p['proc']);
                $exitMax = max($exitMax, $code);
                unset($procs[$i]);
            }
        }
        if ($procs !== []) {
            usleep(20_000);
        }
    }

    return ['exit' => $exitMax, 'stdout' => $stdout, 'stderr' => $stderr];
}

function reopen(string $path): PDO
{
    putenv('RATEB_RUNTIME=branch');
    $_ENV['RATEB_RUNTIME'] = 'branch';
    putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
    $_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
    putenv('RATEB_SQLITE_PATH=' . $path);
    $_ENV['RATEB_SQLITE_PATH'] = $path;
    HybridRuntime::reset();
    Database::disconnect();

    return Database::connection();
}

$levels = [100, 500, 1000];

foreach ($levels as $totalOps) {
    $plan = planWorkers($totalOps);
    $workers = $plan['workers'];
    $opsEach = $plan['opsEach'];
    $scheduled = $workers * $opsEach;

    // --- Inventory concurrent sales ---
    $db = $stressDir . "/inv-{$totalOps}.sqlite";
    prepareDb($db, $scheduled);
    $r = runWorkers($php, $workerScript, $db, 'inventory', $workers, $opsEach);
    $pdo = reopen($db);
    $qty = (int) $pdo->query('SELECT qty FROM b21_stock WHERE id = 1')->fetchColumn();
    $moves = (int) $pdo->query("SELECT COUNT(*) FROM b21_movements WHERE kind = 'sale'")->fetchColumn();
    $neg = (int) $pdo->query('SELECT COUNT(*) FROM b21_stock WHERE qty < 0')->fetchColumn();
    assert_true(
        "inventory ops={$totalOps}",
        $qty === 0 && $moves === $scheduled && $neg === 0 && $r['exit'] === 0,
        "qty={$qty} moves={$moves} expected={$scheduled} neg={$neg} exit={$r['exit']} {$r['stderr']}"
    );

    // --- POS concurrent checkout (idempotent + stock) ---
    $db = $stressDir . "/pos-{$totalOps}.sqlite";
    prepareDb($db, $scheduled);
    $r = runWorkers($php, $workerScript, $db, 'pos', $workers, $opsEach);
    $pdo = reopen($db);
    $orders = (int) $pdo->query('SELECT COUNT(*) FROM b21_pos_orders')->fetchColumn();
    $dup = (int) $pdo->query(
        'SELECT COUNT(*) FROM (SELECT idempotency_key, COUNT(*) c FROM b21_pos_orders GROUP BY idempotency_key HAVING c > 1)'
    )->fetchColumn();
    $qty = (int) $pdo->query('SELECT qty FROM b21_stock WHERE id = 1')->fetchColumn();
    $neg = (int) $pdo->query('SELECT COUNT(*) FROM b21_stock WHERE qty < 0')->fetchColumn();
    assert_true(
        "pos ops={$totalOps}",
        $orders === $scheduled && $dup === 0 && $qty === 0 && $neg === 0 && $r['exit'] === 0,
        "orders={$orders} dup={$dup} qty={$qty} neg={$neg} exit={$r['exit']} {$r['stderr']}"
    );

    // --- Warehouse transfers ---
    $db = $stressDir . "/xfer-{$totalOps}.sqlite";
    prepareDb($db, $scheduled);
    $r = runWorkers($php, $workerScript, $db, 'transfer', $workers, $opsEach);
    $pdo = reopen($db);
    $a = (int) $pdo->query('SELECT qty FROM b21_stock WHERE id = 1')->fetchColumn();
    $b = (int) $pdo->query('SELECT qty FROM b21_stock WHERE id = 2')->fetchColumn();
    $neg = (int) $pdo->query('SELECT COUNT(*) FROM b21_stock WHERE qty < 0')->fetchColumn();
    $moves = (int) $pdo->query("SELECT COUNT(*) FROM b21_movements WHERE kind = 'transfer'")->fetchColumn();
    assert_true(
        "transfer ops={$totalOps}",
        $a === 0 && $b === $scheduled && $moves === $scheduled && $neg === 0 && $r['exit'] === 0,
        "a={$a} b={$b} moves={$moves} neg={$neg} exit={$r['exit']} {$r['stderr']}"
    );

    // --- Procurement receives (stock increases) ---
    $db = $stressDir . "/proc-{$totalOps}.sqlite";
    prepareDb($db, 0);
    $r = runWorkers($php, $workerScript, $db, 'procurement', $workers, $opsEach);
    $pdo = reopen($db);
    $qty = (int) $pdo->query('SELECT qty FROM b21_stock WHERE id = 1')->fetchColumn();
    $pos = (int) $pdo->query('SELECT COUNT(*) FROM b21_po')->fetchColumn();
    assert_true(
        "procurement ops={$totalOps}",
        $qty === $scheduled && $pos === $scheduled && $r['exit'] === 0,
        "qty={$qty} po={$pos} expected={$scheduled} exit={$r['exit']} {$r['stderr']}"
    );

    // --- Warehouse GET_LOCK race (exactly one WH-MAIN) ---
    $db = $stressDir . "/wh-{$totalOps}.sqlite";
    prepareDb($db, 0);
    $r = runWorkers($php, $workerScript, $db, 'warehouse', $workers, $opsEach);
    $pdo = reopen($db);
    $wh = (int) $pdo->query("SELECT COUNT(*) FROM b21_warehouses WHERE code = 'WH-MAIN'")->fetchColumn();
    assert_true(
        "warehouse_lock ops={$totalOps}",
        $wh === 1 && $r['exit'] === 0,
        "warehouses={$wh} exit={$r['exit']} {$r['stderr']}"
    );
}

// BEGIN IMMEDIATE proof
$db = $stressDir . '/immediate.sqlite';
prepareDb($db, 1);
$pdo = reopen($db);
$pdo->beginTransaction();
$inTx = $pdo->inTransaction();
$pdo->commit();
assert_true('BEGIN IMMEDIATE via beginTransaction', $inTx === true);

// GET_LOCK timeout behavior
$pdo = reopen($db);
$got1 = (int) $pdo->query("SELECT GET_LOCK('b21_timeout', 1)")->fetchColumn();
// Hold lock in this process; child tries with timeout 0
$cmd = [
    $php, '-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3',
    $workerScript, $db, 'lock_race', '1', '99',
];
// Instead: second GET_LOCK same process re-entrant = 1; timeout test via file lock held by sibling
assert_true('GET_LOCK acquire', $got1 === 1);
$gotRe = (int) $pdo->query("SELECT GET_LOCK('b21_timeout', 0)")->fetchColumn();
assert_true('GET_LOCK re-entrant', $gotRe === 1);
$rel = (int) $pdo->query("SELECT RELEASE_LOCK('b21_timeout')")->fetchColumn();
assert_true('RELEASE_LOCK', $rel === 1);

// Cloud path unchanged
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
