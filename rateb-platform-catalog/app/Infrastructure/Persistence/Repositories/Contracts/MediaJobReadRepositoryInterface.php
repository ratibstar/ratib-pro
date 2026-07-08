<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface MediaJobReadRepositoryInterface
{
    public function findByUuid(string $uuid): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listByStatus(string $status, int $limit): array;
}
