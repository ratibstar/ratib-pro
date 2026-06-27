<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

final class DeploymentReadinessService
{
    private const MIGRATION_023 = '023_automation_hardening.sql';

    /** Max decompressed bytes scanned for SQL dump signatures (MariaDB preamble-safe). */
    private const BACKUP_VERIFY_SCAN_BYTES = 262144;

    /** @return array{applied:bool,schema_ok:bool,missing:array<int,string>} */
    public function verifyMigration023(): array
    {
        $missing = [];
        $db = Database::connection();

        $applied = false;
        try {
            $row = $db->query(
                "SELECT id FROM rateb_migrations WHERE filename = '" . self::MIGRATION_023 . "' LIMIT 1"
            )->fetch();
            $applied = $row !== false;
        } catch (\Throwable $e) {
            $missing[] = 'rateb_migrations table';
        }

        $required = [
            'rateb_users.failed_attempts' => "SELECT failed_attempts FROM rateb_users LIMIT 0",
            'rateb_remember_tokens' => 'SELECT id FROM rateb_remember_tokens LIMIT 0',
            'rateb_cron_health' => 'SELECT id FROM rateb_cron_health LIMIT 0',
            'rateb_warehouse_transfers' => 'SELECT id FROM rateb_warehouse_transfers LIMIT 0',
            'rateb_notification_queue.next_retry_at' => 'SELECT next_retry_at FROM rateb_notification_queue LIMIT 0',
        ];

        foreach ($required as $label => $sql) {
            try {
                $db->query($sql);
            } catch (\Throwable $e) {
                $missing[] = $label;
            }
        }

        return [
            'applied' => $applied,
            'schema_ok' => $missing === [],
            'missing' => $missing,
        ];
    }

    /** @return array{valid:bool,error:?string,size:int,path:string} */
    public function verifyBackupFile(string $path): array
    {
        if (!is_file($path)) {
            return ['valid' => false, 'error' => 'file_not_found', 'size' => 0, 'path' => $path];
        }
        $size = (int) filesize($path);
        if ($size < 100) {
            return ['valid' => false, 'error' => 'file_too_small', 'size' => $size, 'path' => $path];
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return ['valid' => false, 'error' => 'unreadable', 'size' => $size, 'path' => $path];
        }
        $magic = fread($fh, 2);
        fclose($fh);
        if ($magic !== "\x1f\x8b") {
            return ['valid' => false, 'error' => 'not_gzip', 'size' => $size, 'path' => $path];
        }
        $sample = $this->readGzipSample($path, self::BACKUP_VERIFY_SCAN_BYTES);
        if ($sample === '') {
            return ['valid' => false, 'error' => 'gzip_empty', 'size' => $size, 'path' => $path];
        }
        if (!$this->isSqlDumpSample($sample)) {
            return ['valid' => false, 'error' => 'not_sql_dump', 'size' => $size, 'path' => $path];
        }
        return ['valid' => true, 'error' => null, 'size' => $size, 'path' => $path];
    }

    private function readGzipSample(string $path, int $maxBytes): string
    {
        $gz = @gzopen($path, 'rb');
        if ($gz === false) {
            return '';
        }
        $buffer = '';
        while (!gzeof($gz) && strlen($buffer) < $maxBytes) {
            $read = gzread($gz, min(65536, $maxBytes - strlen($buffer)));
            if ($read === false || $read === '') {
                break;
            }
            $buffer .= $read;
        }
        gzclose($gz);

        return $buffer;
    }

    private function isSqlDumpSample(string $sample): bool
    {
        if (strlen($sample) < 10) {
            return false;
        }

        $hasDumpHeader = preg_match(
            '/(?:MariaDB dump|MySQL dump|mysqldump)/i',
            $sample
        ) === 1 || stripos($sample, '-- Host:') !== false;

        $hasCreate = stripos($sample, 'CREATE TABLE') !== false
            || stripos($sample, 'CREATE DATABASE') !== false;

        $hasInsert = preg_match('/\bINSERT\s+INTO\b/i', $sample) === 1;

        if (!$hasDumpHeader && !$hasCreate) {
            return false;
        }

        if (!$hasCreate) {
            return false;
        }

        // Schema-only dumps are valid; INSERT lines optional when tables exist in preamble-only edge cases.
        return $hasCreate && ($hasDumpHeader || $hasInsert || stripos($sample, 'DROP TABLE') !== false);
    }

    /** @return array<int, string> */
    public function cronWarnings(array $cronRows, array $backupInfo): array
    {
        $warnings = [];
        $byName = [];
        foreach ($cronRows as $row) {
            $byName[(string) ($row['job_name'] ?? '')] = $row;
        }

        if (!isset($byName['erp-cron'])) {
            $warnings[] = __('cron_warning_missing_erp_cron');
        } elseif (($byName['erp-cron']['status'] ?? '') === 'late') {
            $warnings[] = __('cron_warning_late_erp_cron');
        }

        if (!isset($byName['erp-backup'])) {
            $warnings[] = __('cron_warning_missing_erp_backup');
        } elseif (($byName['erp-backup']['status'] ?? '') === 'late') {
            $warnings[] = __('cron_warning_late_erp_backup');
        }

        if (($backupInfo['count'] ?? 0) < 1) {
            $warnings[] = __('cron_warning_no_backups');
        } elseif (($backupInfo['verify_ok'] ?? false) === false) {
            $warnings[] = __('cron_warning_backup_invalid');
        }

        return $warnings;
    }
}
