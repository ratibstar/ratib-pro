<?php
declare(strict_types=1);

/**
 * Phase B.2.1 — single concurrency worker (invoked by stress orchestrator).
 *
 * Usage:
 *   php bin/hybrid-phase-b21-concurrency-worker.php <dbPath> <scenario> <ops> <workerId>
 *
 * Scenarios: inventory | pos | warehouse | transfer | procurement | lock_race
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
define('RATEB_SQLITE_SKIP_SCHEMA_BOOTSTRAP', true);
require_once $root . '/app/Core/Bootstrap.php';

$dbPath = $argv[1] ?? '';
$scenario = $argv[2] ?? '';
$ops = max(1, (int) ($argv[3] ?? 1));
$workerId = (int) ($argv[4] ?? 0);

if ($dbPath === '' || $scenario === '') {
    fwrite(STDERR, "usage: worker <db> <scenario> <ops> <workerId>\n");
    exit(2);
}

putenv('RATEB_RUNTIME=branch');
$_ENV['RATEB_RUNTIME'] = 'branch';
putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
$_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
putenv('RATEB_SQLITE_PATH=' . $dbPath);
$_ENV['RATEB_SQLITE_PATH'] = $dbPath;

\Rateb\App\Core\Bootstrap::initMinimal($root);
\Rateb\App\Core\HybridRuntime::reset();
\Rateb\App\Core\Database::disconnect();

use Rateb\App\Core\Database;
use Rateb\App\Core\SqliteAdvisoryLock;

$pdo = Database::connection();
$ok = 0;
$fail = 0;

$retry = static function (callable $fn, int $attempts = 40) use (&$fail): bool {
    for ($i = 0; $i < $attempts; $i++) {
        try {
            $fn();

            return true;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'busy') !== false || stripos($msg, 'locked') !== false) {
                usleep(5_000 + random_int(0, 15_000));
                continue;
            }
            $fail++;
            fwrite(STDERR, "worker_err: {$msg}\n");

            return false;
        }
    }
    $fail++;
    fwrite(STDERR, "worker_err: busy timeout\n");

    return false;
};

for ($n = 0; $n < $ops; $n++) {
    if ($scenario === 'inventory') {
        $done = $retry(static function () use ($pdo): void {
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('SELECT qty FROM b21_stock WHERE id = 1 FOR UPDATE');
                $st->execute();
                $qty = (int) $st->fetchColumn();
                if ($qty < 1) {
                    $pdo->rollBack();

                    return;
                }
                $pdo->prepare('UPDATE b21_stock SET qty = qty - 1 WHERE id = 1 AND qty >= 1')->execute();
                $pdo->prepare(
                    'INSERT INTO b21_movements(kind, delta, created_at) VALUES(?,?,?)'
                )->execute(['sale', -1, date('c')]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        });
        $ok += $done ? 1 : 0;
        continue;
    }

    if ($scenario === 'pos') {
        $key = 'pos-' . $workerId . '-' . $n;
        $done = $retry(static function () use ($pdo, $key, $workerId, $n): void {
            $pdo->beginTransaction();
            try {
                $chk = $pdo->prepare(
                    'SELECT id FROM b21_pos_orders WHERE idempotency_key = :k LIMIT 1 FOR UPDATE'
                );
                $chk->execute(['k' => $key]);
                if ($chk->fetch()) {
                    $pdo->commit();

                    return;
                }
                $pdo->prepare(
                    'INSERT INTO b21_pos_orders(idempotency_key, worker_id, seq, amount, created_at)
                     VALUES(?,?,?,?,?)'
                )->execute([$key, $workerId, $n, 10.0, date('c')]);
                $st = $pdo->prepare('SELECT qty FROM b21_stock WHERE id = 1 FOR UPDATE');
                $st->execute();
                $qty = (int) $st->fetchColumn();
                if ($qty < 1) {
                    $pdo->rollBack();

                    return;
                }
                $pdo->prepare('UPDATE b21_stock SET qty = qty - 1 WHERE id = 1 AND qty >= 1')->execute();
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        });
        $ok += $done ? 1 : 0;
        continue;
    }

    if ($scenario === 'transfer') {
        $done = $retry(static function () use ($pdo): void {
            $pdo->beginTransaction();
            try {
                $a = $pdo->query('SELECT qty FROM b21_stock WHERE id = 1 FOR UPDATE')->fetchColumn();
                $b = $pdo->query('SELECT qty FROM b21_stock WHERE id = 2 FOR UPDATE')->fetchColumn();
                if ((int) $a < 1) {
                    $pdo->rollBack();

                    return;
                }
                $pdo->exec('UPDATE b21_stock SET qty = qty - 1 WHERE id = 1 AND qty >= 1');
                $pdo->exec('UPDATE b21_stock SET qty = qty + 1 WHERE id = 2');
                $pdo->prepare(
                    'INSERT INTO b21_movements(kind, delta, created_at) VALUES(?,?,?)'
                )->execute(['transfer', -1, date('c')]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        });
        $ok += $done ? 1 : 0;
        continue;
    }

    if ($scenario === 'procurement') {
        $done = $retry(static function () use ($pdo, $workerId, $n): void {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'INSERT INTO b21_po(worker_id, seq, qty, created_at) VALUES(?,?,?,?)'
                )->execute([$workerId, $n, 1, date('c')]);
                $pdo->prepare('SELECT qty FROM b21_stock WHERE id = 1 FOR UPDATE')->execute();
                $pdo->exec('UPDATE b21_stock SET qty = qty + 1 WHERE id = 1');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        });
        $ok += $done ? 1 : 0;
        continue;
    }

    if ($scenario === 'warehouse' || $scenario === 'lock_race') {
        $lock = 'rateb_wh_main_stress';
        $done = $retry(static function () use ($pdo, $lock, $workerId): void {
            $got = (int) $pdo->query('SELECT GET_LOCK(' . $pdo->quote($lock) . ', 8)')->fetchColumn();
            if ($got !== 1) {
                throw new RuntimeException('GET_LOCK timeout');
            }
            try {
                $cnt = (int) $pdo->query('SELECT COUNT(*) FROM b21_warehouses WHERE code = \'WH-MAIN\'')->fetchColumn();
                if ($cnt === 0) {
                    $pdo->prepare(
                        'INSERT INTO b21_warehouses(code, name, created_by) VALUES(?,?,?)'
                    )->execute(['WH-MAIN', 'Main', $workerId]);
                }
            } finally {
                $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lock) . ')');
            }
        });
        $ok += $done ? 1 : 0;
        continue;
    }

    fwrite(STDERR, "unknown scenario {$scenario}\n");
    exit(2);
}

// Ensure locks released on orderly exit
SqliteAdvisoryLock::releaseAll();

echo json_encode(['worker' => $workerId, 'ok' => $ok, 'fail' => $fail, 'scenario' => $scenario], JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($fail > 0 ? 1 : 0);
