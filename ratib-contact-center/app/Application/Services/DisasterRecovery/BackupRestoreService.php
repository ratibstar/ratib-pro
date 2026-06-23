<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\DisasterRecovery;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class BackupRestoreService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    public function startBackup(?int $tenantId, string $type, ?int $userId): array
    {
        $dir = $this->backupRoot();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create backup directory');
        }
        $filename = sprintf('rcc_%s_%s_%s.sql', $type, $tenantId ?? 'platform', date('Ymd_His'));
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO rcc_backups (tenant_id, backup_type, storage_path, status) VALUES (:tid, :type, :path, \'running\')'
        )->execute(['tid' => $tenantId, 'type' => $type, 'path' => $path]);
        $backupId = (int) $pdo->lastInsertId();
        $tables = $this->tablesForBackup($tenantId);
        $dump = $this->exportSchemaData($tables, $tenantId);
        file_put_contents($path, $dump);
        $checksum = hash_file('sha256', $path);
        $size = (int) filesize($path);
        $pdo->prepare(
            "UPDATE rcc_backups SET status = 'completed', file_size = :size, checksum_sha256 = :chk, completed_at = NOW(),
             expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = :id"
        )->execute(['size' => $size, 'chk' => $checksum, 'id' => $backupId]);
        $this->verifyBackup($backupId);
        $this->audit->log($tenantId ?? 0, 'backup.completed', $userId, 'backup', $backupId);
        EventBus::instance()->emit([
            'type' => EventType::BACKUP_COMPLETED,
            'tenant_id' => $tenantId ?? 0,
            'payload' => ['backup_id' => $backupId],
        ]);
        return $this->findBackup($backupId) ?? [];
    }

    public function queueRestore(?int $tenantId, int $backupId, ?int $userId, ?int $approverId = null): array
    {
        Database::connection()->prepare(
            'INSERT INTO rcc_restore_jobs (tenant_id, backup_id, status, initiated_by_user_id, approved_by_user_id)
             VALUES (:tid, :bid, \'queued\', :uid, :aid)'
        )->execute(['tid' => $tenantId, 'bid' => $backupId, 'uid' => $userId, 'aid' => $approverId]);
        $id = (int) Database::connection()->lastInsertId();
        $this->audit->log($tenantId ?? 0, 'restore.queued', $userId, 'restore_job', $id);
        EventBus::instance()->emit([
            'type' => EventType::RESTORE_QUEUED,
            'tenant_id' => $tenantId ?? 0,
            'payload' => ['restore_job_id' => $id, 'backup_id' => $backupId],
        ]);
        return ['restore_job_id' => $id, 'status' => 'queued'];
    }

    /** @return list<array<string, mixed>> */
    public function listBackups(?int $tenantId): array
    {
        $sql = 'SELECT * FROM rcc_backups';
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' WHERE tenant_id = :tid OR tenant_id IS NULL';
            $params['tid'] = $tenantId;
        }
        $sql .= ' ORDER BY started_at DESC LIMIT 100';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findBackup(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_backups WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function verifyBackup(int $backupId): void
    {
        $backup = $this->findBackup($backupId);
        if (!$backup || !is_file((string) $backup['storage_path'])) {
            Database::connection()->prepare(
                "INSERT INTO rcc_backup_verifications (backup_id, status, error_message) VALUES (:bid, 'failed', 'File missing')"
            )->execute(['bid' => $backupId]);
            return;
        }
        $lines = count(file((string) $backup['storage_path']));
        $chk = hash_file('sha256', (string) $backup['storage_path']);
        $ok = $chk === ($backup['checksum_sha256'] ?? $chk);
        Database::connection()->prepare(
            'INSERT INTO rcc_backup_verifications (backup_id, status, tables_checked, row_count, verified_at)
             VALUES (:bid, :st, 1, :rows, NOW())'
        )->execute(['bid' => $backupId, 'st' => $ok ? 'passed' : 'failed', 'rows' => $lines]);
    }

    /** @return list<string> */
    private function tablesForBackup(?int $tenantId): array
    {
        $stmt = Database::connection()->query("SHOW TABLES LIKE 'rcc_%'");
        $all = [];
        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $all[] = (string) $row[0];
        }
        return $all;
    }

    /** @param list<string> $tables */
    private function exportSchemaData(array $tables, ?int $tenantId): string
    {
        $pdo = Database::connection();
        $out = "-- RCC backup " . date('c') . "\n";
        foreach ($tables as $table) {
            $out .= "\n-- Table: {$table}\n";
            $hasTenant = $this->tableHasColumn($table, 'tenant_id');
            $sql = "SELECT * FROM `{$table}`";
            if ($tenantId !== null && $hasTenant) {
                $sql .= ' WHERE tenant_id = ' . (int) $tenantId;
            }
            $sql .= ' LIMIT 50000';
            try {
                $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($rows as $row) {
                $cols = array_map(static fn ($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
                $vals = array_map(static function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote((string) $v);
                }, array_values($row));
                $out .= 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
        }
        return $out;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
        );
        $stmt->execute(['t' => $table, 'c' => $column]);
        return $stmt->fetchColumn() !== false;
    }

    private function backupRoot(): string
    {
        $root = getenv('RCC_BACKUP_PATH') ?: (dirname(__DIR__, 4) . '/storage/rcc-backups');
        return $root;
    }
}
