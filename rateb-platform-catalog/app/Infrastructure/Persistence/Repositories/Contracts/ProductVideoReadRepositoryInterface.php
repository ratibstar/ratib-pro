<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface ProductVideoReadRepositoryInterface extends ReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listByProductUuid(string $productUuid, LocaleContext $locale): array;

    /**
     * @param list<int> $videoIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listTranslationsGrouped(array $videoIds): array;
}
