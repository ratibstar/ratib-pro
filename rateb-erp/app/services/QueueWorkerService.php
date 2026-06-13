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

        $stmt = $db->prepare(
            'SELECT * FROM rateb_notification_queue
             WHERE status = :st AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY id ASC LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute(['st' => 'pending']);
        $rows = $stmt->fetchAll();
        $mail = new MailService();
        $sms = new SmsGatewayService();
        $processed = 0;
        foreach ($rows as $row) {
            $ok = false;
            $channel = (string) ($row['channel'] ?? 'email');
            if ($channel === 'email') {
                $ok = $mail->send(
                    (string) ($row['recipient'] ?? ''),
                    (string) ($row['subject'] ?? 'RTAB ERP'),
                    (string) ($row['body'] ?? ''),
                    null,
                    false
                );
            } elseif ($channel === 'sms') {
                $ok = $sms->send((string) ($row['recipient'] ?? ''), (string) ($row['body'] ?? ''));
            }
            $attempts = (int) ($row['attempt_count'] ?? 0) + 1;
            if ($ok) {
                $db->prepare('UPDATE rateb_notification_queue SET status = :st, sent_at = NOW(), attempt_count = :ac WHERE id = :id')
                    ->execute(['st' => 'sent', 'ac' => $attempts, 'id' => (int) $row['id']]);
            } elseif ($attempts >= self::MAX_ATTEMPTS) {
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
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $db = Database::connection();
        $stmt = $db->query(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'rateb_notification_queue' AND column_name = 'attempt_count'"
        );
        $row = $stmt !== false ? $stmt->fetch() : false;
        $cached = $row && (int) ($row['c'] ?? 0) > 0;
        return $cached;
    }
}
