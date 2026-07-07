<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface ProductImageReadRepositoryInterface extends ReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listByProductUuid(string $productUuid, LocaleContext $locale): array;

    public function findByUuidAndVariant(string $imageUuid, string $variant): ?array;

    /**
     * @param list<int> $imageIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listTranslationsGrouped(array $imageIds): array;
}
