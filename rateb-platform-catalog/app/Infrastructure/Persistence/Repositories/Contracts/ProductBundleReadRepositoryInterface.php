<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface ProductBundleReadRepositoryInterface extends ReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listComponents(string $bundleProductUuid, LocaleContext $locale): array;

    /**
     * @param list<int> $componentProductIds
     */
    public function wouldIntroduceCycle(int $bundleProductId, array $componentProductIds): bool;
}
