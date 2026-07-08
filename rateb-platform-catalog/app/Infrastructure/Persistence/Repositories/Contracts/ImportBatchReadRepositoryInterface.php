<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ImportBatchReadRepositoryInterface
{
    public function findByUuid(string $uuid): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listRows(string $batchUuid, ?string $status, int $limit, int $offset): array;

    public function countRows(string $batchUuid, ?string $status): int;

    public function findSourceIdByCode(string $code): ?int;
}
