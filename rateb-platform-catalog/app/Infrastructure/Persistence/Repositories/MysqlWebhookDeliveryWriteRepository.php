<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookDeliveryWriteRepositoryInterface;

final class MysqlWebhookDeliveryWriteRepository extends BaseRepository implements WebhookDeliveryWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'webhook_deliveries';
    }

    public function create(int $subscriptionId, string $eventId, array $requestBody): string
    {
        $uuid = $this->newUuid();
        $this->writePdo->prepare(
            'INSERT INTO webhook_deliveries
             (uuid, subscription_id, event_id, request_body, status)
             VALUES (:uuid, :subscription_id, :event_id, :request_body, :status)'
        )->execute([
            'uuid' => $uuid,
            'subscription_id' => $subscriptionId,
            'event_id' => $eventId,
            'request_body' => json_encode($requestBody, JSON_UNESCAPED_UNICODE) ?: '{}',
            'status' => 'pending',
        ]);

        return $uuid;
    }

    public function markDelivered(string $deliveryUuid, int $responseStatus, ?string $responseBody): void
    {
        $this->writePdo->prepare(
            'UPDATE webhook_deliveries
             SET status = :status,
                 response_status = :response_status,
                 response_body = :response_body,
                 delivered_at = CURRENT_TIMESTAMP(6),
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid'
        )->execute([
            'uuid' => $deliveryUuid,
            'status' => 'delivered',
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
        ]);
    }

    public function markFailed(string $deliveryUuid, int $responseStatus, ?string $responseBody, int $attempts): void
    {
        $this->writePdo->prepare(
            'UPDATE webhook_deliveries
             SET status = :status,
                 response_status = :response_status,
                 response_body = :response_body,
                 attempts = :attempts,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid'
        )->execute([
            'uuid' => $deliveryUuid,
            'status' => 'failed',
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'attempts' => $attempts,
        ]);
    }
}
