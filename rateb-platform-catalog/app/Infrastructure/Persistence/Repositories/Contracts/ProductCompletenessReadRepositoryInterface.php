<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductCompletenessReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listByProductUuid(string $productUuid): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByProductAndLocale(string $productUuid, string $locale): ?array;
}
