<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductSnapshotGraphReadRepositoryInterface
{
    /**
     * @return array{
     *   variants: list<array<string, mixed>>,
     *   product_barcodes: list<array<string, mixed>>,
     *   bundle_components: list<array<string, mixed>>,
     *   images: list<array<string, mixed>>,
     *   files: list<array<string, mixed>>,
     *   videos: list<array<string, mixed>>
     * }
     */
    public function buildForProduct(string $productUuid): array;
}
