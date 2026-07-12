<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D — SQLite backup / verify / restore / rotation (Core only).
 */
final class BranchBackupService
{
    public const KEEP = 10;

    /**
     * @return array{ok:bool,path:string,meta:array<string,mixed>,verified:bool}
     */
    public function backup(?string $label = null): array
    {
        BranchAppliancePaths::ensureLayout();
        Database::disconnect();
        $src = HybridRuntime::sqlitePath();
        if (!is_file($src)) {
            return ['ok' => false, 'path' => '', 'meta' => [], 'verified' => false];
        }

        // Checkpoint WAL into main DB for consistent cold backup
        try {
            putenv('RATEB_RUNTIME=branch');
            $_ENV['RATEB_RUNTIME'] = 'branch';
            HybridRuntime::reset();
            $pdo = Database::connection();
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            Database::disconnect();
        } catch (\Throwable $e) {
            Database::disconnect();
        }

        $stamp = gmdate('Ymd-His');
        $label = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $label) ?: 'auto';
        $dest = BranchAppliancePaths::backupsDir() . "/rateb-{$stamp}-{$label}.sqlite";
        if (!@copy($src, $dest)) {
            return ['ok' => false, 'path' => $dest, 'meta' => [], 'verified' => false];
        }
        @copy($src . '-wal', $dest . '-wal');
        @copy($src . '-shm', $dest . '-shm');

        $hash = hash_file('sha256', $dest) ?: '';
        $meta = [
            'created_at' => gmdate('c'),
            'source' => $src,
            'path' => $dest,
            'sha256' => $hash,
            'bytes' => filesize($dest) ?: 0,
            'label' => $label,
            'version' => BranchAppliancePaths::readVersion(),
            'point_in_time' => gmdate('c'),
        ];
        $metaPath = BranchAppliancePaths::backupsDir() . '/meta/' . basename($dest) . '.json';
        file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $verified = $this->verifyBackup($dest);
        $meta['verified'] = $verified;
        file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->rotate(self::KEEP);

        return ['ok' => $verified, 'path' => $dest, 'meta' => $meta, 'verified' => $verified];
    }

    public function verifyBackup(string $path): bool
    {
        if (!is_file($path) || filesize($path) < 100) {
            return false;
        }
        try {
            $pdo = new \PDO('sqlite:' . $path, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $tables = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();

            return strtoupper($integrity) === 'OK' && $tables > 10;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Restore from backup path into branch SQLite (destructive).
     *
     * @return array{ok:bool,detail:string}
     */
    public function restore(string $backupPath): array
    {
        if (!$this->verifyBackup($backupPath)) {
            return ['ok' => false, 'detail' => 'backup_verify_failed'];
        }
        // Pre-restore safety copy
        $safety = $this->backup('pre-restore');
        Database::disconnect();
        HybridSyncOutboxCapture::resetConnection();
        $target = HybridRuntime::sqlitePath();
        @unlink($target . '-wal');
        @unlink($target . '-shm');
        if (!@copy($backupPath, $target)) {
            return ['ok' => false, 'detail' => 'copy_failed'];
        }
        HybridRuntime::reset();
        $pdo = Database::connection();
        $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        $ok = strtoupper($integrity) === 'OK';

        return [
            'ok' => $ok,
            'detail' => $ok ? 'restored integrity=OK safety=' . ($safety['path'] ?? '') : 'integrity_failed',
        ];
    }

    public function rotate(int $keep = self::KEEP): int
    {
        $files = glob(BranchAppliancePaths::backupsDir() . '/rateb-*.sqlite') ?: [];
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $removed = 0;
        foreach (array_slice($files, max(0, $keep)) as $old) {
            @unlink($old);
            @unlink($old . '-wal');
            @unlink($old . '-shm');
            @unlink(BranchAppliancePaths::backupsDir() . '/meta/' . basename($old) . '.json');
            $removed++;
        }

        return $removed;
    }

    /** @return list<array<string,mixed>> */
    public function listBackups(): array
    {
        $out = [];
        foreach (glob(BranchAppliancePaths::backupsDir() . '/meta/*.json') ?: [] as $m) {
            $j = json_decode((string) file_get_contents($m), true);
            if (is_array($j)) {
                $out[] = $j;
            }
        }
        usort($out, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return $out;
    }
}
