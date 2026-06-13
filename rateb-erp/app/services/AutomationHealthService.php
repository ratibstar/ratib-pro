<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

final class AutomationHealthService
{
    public function recordCronRun(string $jobName, array $stats, int $expectedIntervalMinutes = 15): void
    {
        $db = Database::connection();
        $next = date('Y-m-d H:i:s', time() + ($expectedIntervalMinutes * 60));
        $db->prepare(
            'INSERT INTO rateb_cron_health (job_name, last_run_at, next_expected_at, status, stats_json)
             VALUES (:job, NOW(), :next, \'ok\', :stats)
             ON DUPLICATE KEY UPDATE last_run_at = NOW(), next_expected_at = :next2, status = \'ok\', stats_json = :stats2'
        )->execute([
            'job' => $jobName,
            'next' => $next,
            'stats' => json_encode($stats, JSON_UNESCAPED_UNICODE),
            'next2' => $next,
            'stats2' => json_encode($stats, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function checkLateJobs(): int
    {
        try {
            $db = Database::connection();
            $stmt = $db->query(
                "UPDATE rateb_cron_health SET status = 'late'
                 WHERE next_expected_at IS NOT NULL AND next_expected_at < NOW() AND status = 'ok'"
            );
            return $stmt !== false ? $stmt->rowCount() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $db = Database::connection();
        $readiness = new DeploymentReadinessService();
        $migration023 = $readiness->verifyMigration023();

        $cron = [];
        try {
            $cron = $db->query('SELECT * FROM rateb_cron_health ORDER BY job_name')->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $cron = [];
        }

        $this->checkLateJobs();

        $queuePending = 0;
        $queueFailed = 0;
        $queueDead = 0;
        try {
            $queuePending = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE status = 'pending'")->fetch()['c'] ?? 0);
            $queueFailed = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE status = 'failed'")->fetch()['c'] ?? 0);
            $queueDead = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE dead_letter_at IS NOT NULL")->fetch()['c'] ?? 0);
        } catch (\Throwable $e) {
        }

        $backupDir = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/storage/backups';
        $backups = is_dir($backupDir) ? (glob($backupDir . '/*.sql.gz') ?: []) : [];
        $latestBackup = '';
        $latestPath = '';
        $backupVerify = ['valid' => false, 'error' => 'no_backup', 'size' => 0, 'path' => ''];
        if ($backups !== []) {
            usort($backups, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $latestPath = $backups[0];
            $latestBackup = basename($latestPath);
            $backupVerify = $readiness->verifyBackupFile($latestPath);
        }

        $backupInfo = [
            'latest' => $latestBackup,
            'latest_path' => $latestPath,
            'count' => count($backups),
            'verify_ok' => $backupVerify['valid'],
            'verify_error' => $backupVerify['error'],
            'verify_size' => $backupVerify['size'],
        ];

        $pendingWorkflows = 0;
        $overdueWorkflows = 0;
        try {
            $pendingWorkflows = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_approval_instances WHERE status = 'pending'")->fetch()['c'] ?? 0);
            $overdueWorkflows = (int) ($db->query(
                "SELECT COUNT(*) AS c FROM rateb_approval_instances WHERE status = 'pending' AND due_at IS NOT NULL AND due_at < NOW()"
            )->fetch()['c'] ?? 0);
        } catch (\Throwable $e) {
        }

        $failedLogins24h = 0;
        try {
            $failedLogins24h = (int) ($db->query(
                "SELECT COUNT(*) AS c FROM rateb_login_activity WHERE success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )->fetch()['c'] ?? 0);
        } catch (\Throwable $e) {
        }

        $cronWarnings = $readiness->cronWarnings($cron, $backupInfo);
        if (!$migration023['schema_ok']) {
            $cronWarnings[] = __('migration_023_missing');
        } elseif (!$migration023['applied']) {
            $cronWarnings[] = __('migration_023_schema_only');
        }

        return [
            'cron' => $cron,
            'cron_warnings' => $cronWarnings,
            'migration_023' => $migration023,
            'queue' => ['pending' => $queuePending, 'failed' => $queueFailed, 'dead_letter' => $queueDead],
            'backup' => $backupInfo,
            'workflow' => ['pending' => $pendingWorkflows, 'overdue' => $overdueWorkflows],
            'email_health' => $queueFailed < 10 ? 'ok' : 'degraded',
            'sms_health' => AutomationSettings::getString('sms_provider', 'log') !== 'log' ? 'configured' : 'log_only',
            'failed_logins_24h' => $failedLogins24h,
        ];
    }
}
