<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Tests\Support;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotGraphReadRepositoryInterface;

final class EmptyProductSnapshotGraphReadRepository implements ProductSnapshotGraphReadRepositoryInterface
{
    public function buildForProduct(string $productUuid): array
    {
        unset($productUuid);

        return [
            'variants' => [],
            'product_barcodes' => [],
            'bundle_components' => [],
            'images' => [],
            'files' => [],
            'videos' => [],
        ];
    }
}
