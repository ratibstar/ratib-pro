<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D — Enterprise updater (version detect, safe update, rollback, verify).
 * Does not modify Controllers/Services/Models — operates on VERSION + schema verify + sync restart.
 */
final class BranchUpdater
{
    public function currentVersion(): string
    {
        return BranchAppliancePaths::readVersion();
    }

    /**
     * Safe update: pre-backup → write target version → schema verify → restart sync → post-verify.
     *
     * @return array{ok:bool,from:string,to:string,backup?:string,steps:list<array{id:string,ok:bool,detail:string}>}
     */
    public function safeUpdate(string $targetVersion): array
    {
        BranchAppliancePaths::ensureLayout();
        $from = $this->currentVersion();
        $steps = [];
        $targetVersion = trim($targetVersion);
        if ($targetVersion === '') {
            return ['ok' => false, 'from' => $from, 'to' => '', 'steps' => [['id' => 'version', 'ok' => false, 'detail' => 'empty']]];
        }

        $backup = (new BranchBackupService())->backup('pre-update');
        $steps[] = ['id' => 'pre_update_backup', 'ok' => $backup['ok'], 'detail' => $backup['path']];
        if (!$backup['ok']) {
            return ['ok' => false, 'from' => $from, 'to' => $targetVersion, 'steps' => $steps];
        }

        // Persist rollback pointer
        $rb = [
            'from' => $from,
            'to' => $targetVersion,
            'backup' => $backup['path'],
            'created_at' => gmdate('c'),
        ];
        file_put_contents(
            BranchAppliancePaths::root() . '/updates/rollback/last.json',
            json_encode($rb, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        file_put_contents(BranchAppliancePaths::versionFile(), $targetVersion . "\n");
        $steps[] = ['id' => 'version_write', 'ok' => $this->currentVersion() === $targetVersion, 'detail' => $targetVersion];

        $schemaOk = false;
        $schemaDetail = '';
        if (HybridRuntime::shouldUseSqlite() && is_file(HybridRuntime::sqlitePath())) {
            try {
                $pdo = Database::connection();
                $schema = SqliteSchemaBootstrap::ensureErpSchema($pdo);
                $tables = SqliteSchemaBootstrap::countUserTables($pdo);
                $schemaOk = $tables >= 100;
                $schemaDetail = 'tables=' . $tables . ' v=' . ($schema['version'] ?? '');
                SqliteSchemaBootstrap::upsertMeta($pdo, 'appliance_version', $targetVersion);
            } catch (\Throwable $e) {
                $schemaDetail = $e->getMessage();
            }
        }
        $steps[] = ['id' => 'schema_verification', 'ok' => $schemaOk, 'detail' => $schemaDetail];

        $restart = $this->restartSyncService();
        $steps[] = ['id' => 'restart_sync_service', 'ok' => $restart['ok'], 'detail' => $restart['detail']];

        $post = (new BranchDiagnostics())->run();
        $steps[] = ['id' => 'post_update_verification', 'ok' => ($post['health'] ?? '') !== 'red', 'detail' => 'health=' . ($post['health'] ?? '')];

        $ok = true;
        foreach ($steps as $s) {
            if (!$s['ok']) {
                $ok = false;
                break;
            }
        }

        return [
            'ok' => $ok,
            'from' => $from,
            'to' => $targetVersion,
            'backup' => $backup['path'],
            'steps' => $steps,
        ];
    }

    /**
     * @return array{ok:bool,detail:string,restored_version?:string}
     */
    public function rollback(): array
    {
        $path = BranchAppliancePaths::root() . '/updates/rollback/last.json';
        if (!is_file($path)) {
            return ['ok' => false, 'detail' => 'no_rollback_pointer'];
        }
        $rb = json_decode((string) file_get_contents($path), true);
        if (!is_array($rb) || empty($rb['backup']) || !is_file((string) $rb['backup'])) {
            return ['ok' => false, 'detail' => 'invalid_rollback_pointer'];
        }
        $restore = (new BranchBackupService())->restore((string) $rb['backup']);
        if (!$restore['ok']) {
            return ['ok' => false, 'detail' => 'restore_failed:' . $restore['detail']];
        }
        $from = (string) ($rb['from'] ?? 'unknown');
        file_put_contents(BranchAppliancePaths::versionFile(), $from . "\n");
        $this->restartSyncService();

        return ['ok' => true, 'detail' => 'rolled_back', 'restored_version' => $from];
    }

    /** @return array{ok:bool,detail:string} */
    public function restartSyncService(): array
    {
        BranchAppliancePaths::ensureLayout();
        $stop = BranchAppliancePaths::root() . '/hybrid-sync.stop';
        file_put_contents($stop, gmdate('c') . PHP_EOL);
        // systemd/WinSW Restart=always will relaunch; for CLI we only signal stop.
        return ['ok' => true, 'detail' => 'stop_signal_written'];
    }
}
