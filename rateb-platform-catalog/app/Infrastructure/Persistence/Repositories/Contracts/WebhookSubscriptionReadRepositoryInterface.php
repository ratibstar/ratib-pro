<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface WebhookSubscriptionReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findActiveForEvent(string $eventType, ?int $erpCompanyId): array;

    public function findByUuid(string $uuid): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $limit, int $offset): array;
}
