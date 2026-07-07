<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductVersionReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listByProductUuid(string $productUuid, int $limit = 50): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByProductAndVersion(string $productUuid, int $versionNumber): ?array;
}
