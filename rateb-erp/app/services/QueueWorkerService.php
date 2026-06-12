<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

final class QueueWorkerService
{
    private const MAX_ATTEMPTS = 3;

    public function processPending(int $limit = 50): int
    {
        $db = Database::connection();
        $this->requeueRetriableFailures();

        $stmt = $db->prepare(
            'SELECT * FROM rateb_notification_queue WHERE status = :st ORDER BY id ASC LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute(['st' => 'pending']);
        $rows = $stmt->fetchAll();
        $mail = new MailService();
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
                Logger::info('SMS queue item skipped (no gateway configured)', ['id' => $row['id'] ?? 0]);
                $ok = false;
            }
            $attempts = (int) ($row['attempt_count'] ?? 0) + 1;
            $db->prepare('UPDATE rateb_notification_queue SET status = :st, sent_at = NOW(), attempt_count = :ac WHERE id = :id')
                ->execute([
                    'st' => $ok ? 'sent' : ($attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending'),
                    'ac' => $attempts,
                    'id' => (int) $row['id'],
                ]);
            $processed++;
        }
        return $processed;
    }

    private function requeueRetriableFailures(): void
    {
        $db = Database::connection();
        $hasAttempts = $this->queueHasAttemptColumn();
        if (!$hasAttempts) {
            return;
        }
        $db->exec(
            'UPDATE rateb_notification_queue SET status = \'pending\'
             WHERE status = \'failed\' AND attempt_count > 0 AND attempt_count < ' . self::MAX_ATTEMPTS
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
