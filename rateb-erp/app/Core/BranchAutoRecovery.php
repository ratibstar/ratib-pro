<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D — Automatic recovery (power loss, crash, locks, interrupted sync/tx).
 */
final class BranchAutoRecovery
{
    /**
     * @return array{ok:bool,actions:list<array{id:string,ok:bool,detail:string}>}
     */
    public function recover(): array
    {
        BranchAppliancePaths::ensureLayout();
        $actions = [];

        // Corrupted / stale daemon lock (process gone)
        $lock = BranchAppliancePaths::root() . '/hybrid-sync.daemon.lock';
        if (is_file($lock)) {
            $fh = @fopen($lock, 'c+');
            if ($fh !== false) {
                if (@flock($fh, LOCK_EX | LOCK_NB)) {
                    // Nobody holds it — clear stale lock
                    ftruncate($fh, 0);
                    flock($fh, LOCK_UN);
                    fclose($fh);
                    @unlink($lock);
                    $actions[] = ['id' => 'corrupted_lock', 'ok' => true, 'detail' => 'stale_lock_cleared'];
                } else {
                    fclose($fh);
                    $actions[] = ['id' => 'corrupted_lock', 'ok' => true, 'detail' => 'lock_held_by_live_process'];
                }
            }
        } else {
            $actions[] = ['id' => 'corrupted_lock', 'ok' => true, 'detail' => 'no_lock'];
        }

        // Clear stop remnant after crash
        $stop = BranchAppliancePaths::root() . '/hybrid-sync.stop';
        if (is_file($stop)) {
            @unlink($stop);
            $actions[] = ['id' => 'service_restart', 'ok' => true, 'detail' => 'stop_file_cleared'];
        } else {
            $actions[] = ['id' => 'service_restart', 'ok' => true, 'detail' => 'ready'];
        }

        if (!HybridRuntime::shouldUseSqlite() || !is_file(HybridRuntime::sqlitePath())) {
            $actions[] = ['id' => 'sqlite_recovery', 'ok' => false, 'detail' => 'not_branch_sqlite'];

            return ['ok' => false, 'actions' => $actions];
        }

        try {
            $pdo = Database::connection();
            // Interrupted transaction / WAL recovery
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
                $actions[] = ['id' => 'interrupted_transaction', 'ok' => true, 'detail' => 'rolled_back'];
            } else {
                $actions[] = ['id' => 'interrupted_transaction', 'ok' => true, 'detail' => 'none'];
            }
            $pdo->exec('PRAGMA wal_checkpoint(PASSIVE)');
            $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $actions[] = [
                'id' => 'sqlite_recovery',
                'ok' => strtoupper($integrity) === 'OK',
                'detail' => $integrity,
            ];

            // Interrupted sync
            $resume = (new HybridSyncEngine())->resumeInterrupted($pdo);
            $actions[] = [
                'id' => 'interrupted_sync',
                'ok' => true,
                'detail' => 'reset=' . (int) ($resume['reset'] ?? 0),
            ];

            // Power loss / crash markers
            SqliteSchemaBootstrap::upsertMeta($pdo, 'last_recovery_at', gmdate('c'));
            $actions[] = ['id' => 'power_loss_crash', 'ok' => true, 'detail' => 'recovery_meta_written'];
        } catch (\Throwable $e) {
            $actions[] = ['id' => 'sqlite_recovery', 'ok' => false, 'detail' => $e->getMessage()];
        }

        $ok = true;
        foreach ($actions as $a) {
            if (!$a['ok']) {
                $ok = false;
            }
        }
        @file_put_contents(
            BranchAppliancePaths::root() . '/recovery/last-recovery.json',
            json_encode(['ok' => $ok, 'actions' => $actions, 'ts' => gmdate('c')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return ['ok' => $ok, 'actions' => $actions];
    }
}
