<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

final class QueueWorkerService
{
    private const MAX_ATTEMPTS = 5;

    public function processPending(int $limit = 50): int
    {
        $db = Database::connection();
        $this->requeueRetriableFailures();

        // Throttle (Admin → Settings → Mail). Shared cPanel SMTP suspends accounts
        // long before the queue's theoretical throughput, so cap batch and rate.
        $batch = BulkCampaignService::readSettingInt('mail_queue_batch_size', max(1, min(200, $limit)), 1, 500);
        $delayMs = BulkCampaignService::readSettingInt('mail_queue_delay_ms', 300, 0, 10000);
        $hourlyLimit = BulkCampaignService::readSettingInt('mail_queue_hourly_limit', 400, 0, 100000);
        $emailBudget = $hourlyLimit > 0 ? max(0, $hourlyLimit - $this->emailsSentLastHour()) : PHP_INT_MAX;

        $stmt = $db->prepare(
            'SELECT * FROM rateb_notification_queue
             WHERE status = :st AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY id ASC LIMIT ' . $batch
        );
        $stmt->execute(['st' => 'pending']);
        $rows = $stmt->fetchAll();
        $mail = new MailService();
        $sms = new SmsGatewayService();
        $hasErrorCode = $this->queueHasColumn('error_code');
        $processed = 0;
        $emailsThisRun = 0;
        foreach ($rows as $row) {
            $ok = false;
            $errorCode = null;
            $channel = (string) ($row['channel'] ?? 'email');
            if ($channel === 'email') {
                if ($emailsThisRun >= $emailBudget) {
                    continue;
                }
                if ($delayMs > 0 && $emailsThisRun > 0) {
                    usleep($delayMs * 1000);
                }
                $ok = $mail->send(
                    (string) ($row['recipient'] ?? ''),
                    (string) ($row['subject'] ?? 'RTAB ERP'),
                    (string) ($row['body'] ?? ''),
                    null,
                    false,
                    null,
                    null,
                    null,
                    isset($row['unsubscribe_url']) ? (string) $row['unsubscribe_url'] : null
                );
                $errorCode = $ok ? null : $mail->lastErrorCode();
                $emailsThisRun++;
            } elseif ($channel === 'sms') {
                $ok = $sms->send((string) ($row['recipient'] ?? ''), (string) ($row['body'] ?? ''));
            }
            $attempts = (int) ($row['attempt_count'] ?? 0) + 1;
            if ($hasErrorCode) {
                $db->prepare('UPDATE rateb_notification_queue SET error_code = :ec WHERE id = :id')
                    ->execute(['ec' => $errorCode, 'id' => (int) $row['id']]);
            }
            if ($ok) {
                $db->prepare('UPDATE rateb_notification_queue SET status = :st, sent_at = NOW(), attempt_count = :ac WHERE id = :id')
                    ->execute(['st' => 'sent', 'ac' => $attempts, 'id' => (int) $row['id']]);
            } elseif ($errorCode === 'smtp_rcpt' || $attempts >= self::MAX_ATTEMPTS) {
                $db->prepare(
                    'UPDATE rateb_notification_queue SET status = :st, attempt_count = :ac, dead_letter_at = NOW() WHERE id = :id'
                )->execute(['st' => 'failed', 'ac' => $attempts, 'id' => (int) $row['id']]);
            } else {
                $backoff = min(3600, (int) (60 * (2 ** ($attempts - 1))));
                $next = date('Y-m-d H:i:s', time() + $backoff);
                $db->prepare(
                    'UPDATE rateb_notification_queue SET status = :st, attempt_count = :ac, next_retry_at = :next WHERE id = :id'
                )->execute(['st' => 'pending', 'ac' => $attempts, 'next' => $next, 'id' => (int) $row['id']]);
            }
            $processed++;
        }
        return $processed;
    }

    public function retryFailed(int $limit = 20): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_notification_queue SET status = \'pending\', next_retry_at = NOW(), dead_letter_at = NULL
             WHERE status = \'failed\' AND dead_letter_at IS NOT NULL LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function requeueRetriableFailures(): void
    {
        if (!$this->queueHasAttemptColumn()) {
            return;
        }
        Database::connection()->exec(
            'UPDATE rateb_notification_queue SET status = \'pending\', next_retry_at = NOW()
             WHERE status = \'failed\' AND dead_letter_at IS NULL AND attempt_count > 0 AND attempt_count < ' . self::MAX_ATTEMPTS
        );
    }

    private function queueHasAttemptColumn(): bool
    {
        return $this->queueHasColumn('attempt_count');
    }

    private function queueHasColumn(string $column): bool
    {
        static $cache = [];
        if (isset($cache[$column])) {
            return $cache[$column];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'rateb_notification_queue' AND column_name = :col"
        );
        $stmt->execute(['col' => $column]);
        $row = $stmt->fetch();
        $cache[$column] = $row && (int) ($row['c'] ?? 0) > 0;

        return $cache[$column];
    }

    /** Rolling send rate used by the hourly throttle. */
    private function emailsSentLastHour(): int
    {
        try {
            $stmt = Database::connection()->query(
                "SELECT COUNT(*) AS c FROM rateb_notification_queue
                 WHERE channel = 'email' AND status = 'sent' AND sent_at >= (NOW() - INTERVAL 1 HOUR)"
            );
            $row = $stmt !== false ? $stmt->fetch() : false;

            return $row ? (int) ($row['c'] ?? 0) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
