<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;

final class QueueWorkerService
{
    public function processPending(int $limit = 50): int
    {
        $db = Database::connection();
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
            $db->prepare('UPDATE rateb_notification_queue SET status = :st, sent_at = NOW() WHERE id = :id')
                ->execute(['st' => $ok ? 'sent' : 'failed', 'id' => (int) $row['id']]);
            $processed++;
        }
        return $processed;
    }
}
