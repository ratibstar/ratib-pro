<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface SavedFilterReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, ?string $entityType): array;

    public function findByUuid(string $uuid, int $userId): ?array;
}
