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
        $db = Database::connection();
        $stmt = $db->query(
            "UPDATE rateb_cron_health SET status = 'late'
             WHERE next_expected_at IS NOT NULL AND next_expected_at < NOW() AND status = 'ok'"
        );
        return $stmt !== false ? $stmt->rowCount() : 0;
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $db = Database::connection();
        $cron = $db->query('SELECT * FROM rateb_cron_health ORDER BY job_name')->fetchAll() ?: [];
        $queuePending = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE status = 'pending'")->fetch()['c'] ?? 0);
        $queueFailed = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE status = 'failed'")->fetch()['c'] ?? 0);
        $queueDead = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE dead_letter_at IS NOT NULL")->fetch()['c'] ?? 0);
        $backups = glob((defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/storage/backups/*.sql.gz') ?: [];
        $latestBackup = '';
        if ($backups !== []) {
            usort($backups, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $latestBackup = basename($backups[0]);
        }
        $pendingWorkflows = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_approval_instances WHERE status = 'pending'")->fetch()['c'] ?? 0);
        $overdueWorkflows = (int) ($db->query(
            "SELECT COUNT(*) AS c FROM rateb_approval_instances WHERE status = 'pending' AND due_at IS NOT NULL AND due_at < NOW()"
        )->fetch()['c'] ?? 0);
        $failedLogins24h = (int) ($db->query(
            "SELECT COUNT(*) AS c FROM rateb_login_activity WHERE success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetch()['c'] ?? 0);
        return [
            'cron' => $cron,
            'queue' => ['pending' => $queuePending, 'failed' => $queueFailed, 'dead_letter' => $queueDead],
            'backup' => ['latest' => $latestBackup, 'count' => count($backups)],
            'workflow' => ['pending' => $pendingWorkflows, 'overdue' => $overdueWorkflows],
            'email_health' => $queueFailed < 10 ? 'ok' : 'degraded',
            'sms_health' => AutomationSettings::getString('sms_provider', 'log') !== 'log' ? 'configured' : 'log_only',
            'failed_logins_24h' => $failedLogins24h,
        ];
    }
}
