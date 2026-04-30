<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\BaseModel;
use PDO;

final class WebhookRepository extends BaseModel
{
    /** @return list<array<string, mixed>> */
    public function listActiveByEvent(string $eventName): array
    {
        $st = $this->db->prepare(
            "SELECT id, target_url, secret, timeout_seconds
             FROM webhooks
             WHERE is_active = 1
               AND event_name = :event_name"
        );
        $st->execute([':event_name' => $eventName]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $payload */
    public function enqueueDelivery(int $webhookId, string $eventName, array $payload): int
    {
        $st = $this->db->prepare(
            "INSERT INTO webhook_deliveries
             (webhook_id, event_name, payload_json, status, attempts, next_retry_at, created_at, updated_at)
             VALUES
             (:webhook_id, :event_name, :payload_json, 'pending', 0, NOW(), NOW(), NOW())"
        );
        $st->execute([
            ':webhook_id' => $webhookId,
            ':event_name' => $eventName,
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function takePendingDeliveries(int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT d.id, d.webhook_id, d.event_name, d.payload_json, d.attempts, w.target_url, w.secret, w.timeout_seconds
                FROM webhook_deliveries d
                INNER JOIN webhooks w ON w.id = d.webhook_id
                WHERE d.status IN ('pending', 'retry')
                  AND d.next_retry_at <= NOW()
                  AND w.is_active = 1
                ORDER BY d.id ASC
                LIMIT {$limit}";
        $st = $this->db->query($sql);
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    }

    public function markDelivered(int $deliveryId, int $httpStatus, string $responseBody): void
    {
        $st = $this->db->prepare(
            "UPDATE webhook_deliveries
             SET status = 'delivered',
                 attempts = attempts + 1,
                 http_status = :http_status,
                 response_body = :response_body,
                 delivered_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $st->execute([
            ':id' => $deliveryId,
            ':http_status' => $httpStatus,
            ':response_body' => mb_substr($responseBody, 0, 2000),
        ]);
    }

    public function markRetry(int $deliveryId, int $httpStatus, string $responseBody, int $attempts): void
    {
        $delaySeconds = min(3600, (int) pow(2, max(1, $attempts)) * 30);
        $st = $this->db->prepare(
            "UPDATE webhook_deliveries
             SET status = 'retry',
                 attempts = attempts + 1,
                 http_status = :http_status,
                 response_body = :response_body,
                 next_retry_at = DATE_ADD(NOW(), INTERVAL :delay_sec SECOND),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $st->bindValue(':id', $deliveryId, PDO::PARAM_INT);
        $st->bindValue(':http_status', $httpStatus, PDO::PARAM_INT);
        $st->bindValue(':response_body', mb_substr($responseBody, 0, 2000), PDO::PARAM_STR);
        $st->bindValue(':delay_sec', $delaySeconds, PDO::PARAM_INT);
        $st->execute();
    }

    public function markFailed(int $deliveryId, int $httpStatus, string $responseBody): void
    {
        $st = $this->db->prepare(
            "UPDATE webhook_deliveries
             SET status = 'failed',
                 attempts = attempts + 1,
                 http_status = :http_status,
                 response_body = :response_body,
                 updated_at = NOW()
             WHERE id = :id"
        );
        $st->execute([
            ':id' => $deliveryId,
            ':http_status' => $httpStatus,
            ':response_body' => mb_substr($responseBody, 0, 2000),
        ]);
    }
}
