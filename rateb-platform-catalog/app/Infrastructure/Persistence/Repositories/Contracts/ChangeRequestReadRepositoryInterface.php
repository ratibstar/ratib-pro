<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ChangeRequestReadRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByUuid(string $uuid): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $status = null, int $limit = 100, int $offset = 0): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listItems(int $changeRequestId): array;
}
