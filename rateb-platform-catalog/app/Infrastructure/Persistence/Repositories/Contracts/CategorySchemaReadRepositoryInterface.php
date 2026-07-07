<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface CategorySchemaReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listRequiredForCategory(int $categoryId): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listResolvedSchemaForCategory(int $categoryId): array;

    public function findCategoryIdByUuid(string $categoryUuid): ?int;
}
