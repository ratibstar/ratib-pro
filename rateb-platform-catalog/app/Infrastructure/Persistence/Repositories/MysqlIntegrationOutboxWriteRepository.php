<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IntegrationOutboxWriteRepositoryInterface;

final class MysqlIntegrationOutboxWriteRepository extends BaseRepository implements IntegrationOutboxWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'integration_outbox';
    }

    public function insert(
        string $eventId,
        string $eventType,
        string $entityType,
        string $entityUuid,
        array $payload,
        ?int $erpCompanyId = null
    ): void {
        $this->writePdo->prepare(
            'INSERT INTO integration_outbox
             (event_id, event_type, entity_type, entity_uuid, erp_company_id, payload, status, next_attempt_at)
             VALUES (:event_id, :event_type, :entity_type, :entity_uuid, :erp_company_id, :payload, :status, CURRENT_TIMESTAMP(6))'
        )->execute([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
            'erp_company_id' => $erpCompanyId,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
            'status' => 'pending',
        ]);
    }

    public function markDispatched(string $eventId): void
    {
        $this->writePdo->prepare(
            'UPDATE integration_outbox
             SET status = :status, updated_at = CURRENT_TIMESTAMP(6)
             WHERE event_id = :event_id'
        )->execute([
            'event_id' => $eventId,
            'status' => 'dispatched',
        ]);
    }

    public function markDelivered(string $eventId): void
    {
        $this->writePdo->prepare(
            'UPDATE integration_outbox
             SET status = :status, updated_at = CURRENT_TIMESTAMP(6)
             WHERE event_id = :event_id'
        )->execute([
            'event_id' => $eventId,
            'status' => 'delivered',
        ]);
    }

    public function markFailed(string $eventId, int $attempts, \DateTimeImmutable $nextAttemptAt): void
    {
        $this->writePdo->prepare(
            'UPDATE integration_outbox
             SET status = :status, attempts = :attempts, next_attempt_at = :next_attempt_at,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE event_id = :event_id'
        )->execute([
            'event_id' => $eventId,
            'status' => 'failed',
            'attempts' => $attempts,
            'next_attempt_at' => $nextAttemptAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function deleteExpiredDelivered(int $retentionDays = 30): int
    {
        $retentionDays = max(1, $retentionDays);
        $stmt = $this->writePdo->prepare(
            'DELETE FROM integration_outbox
             WHERE status = :status
               AND updated_at < DATE_SUB(CURRENT_TIMESTAMP(6), INTERVAL ' . $retentionDays . ' DAY)'
        );
        $stmt->execute(['status' => 'delivered']);

        return $stmt->rowCount();
    }
}
