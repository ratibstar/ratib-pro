<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface IntegrationOutboxWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function insert(string $eventId, string $eventType, string $entityType, string $entityUuid, array $payload, ?int $erpCompanyId = null): void;

    public function markDispatched(string $eventId): void;

    public function markDelivered(string $eventId): void;

    public function markFailed(string $eventId, int $attempts, \DateTimeImmutable $nextAttemptAt): void;

    public function deleteExpiredDelivered(int $retentionDays = 30): int;
}
