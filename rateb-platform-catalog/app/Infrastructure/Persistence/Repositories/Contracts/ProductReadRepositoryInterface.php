<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\DTO\ProductListFilter;

interface ProductReadRepositoryInterface extends ReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listFiltered(
        LocaleContext $locale,
        ProductListFilter $filter,
        int $limit = 100,
        int $offset = 0
    ): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array;

    public function findLockVersion(string $uuid): ?int;

    /**
     * @return array<string, mixed>|null
     */
    public function findWorkflowMeta(string $uuid): ?array;
}
