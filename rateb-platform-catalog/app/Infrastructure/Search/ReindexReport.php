<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

final class ReindexReport
{
    public function __construct(
        public readonly string $locale,
        public readonly int $productsIndexed,
        public readonly int $variantsIndexed,
        public readonly ?int $lastProductId = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'products_indexed' => $this->productsIndexed,
            'variants_indexed' => $this->variantsIndexed,
            'last_product_id' => $this->lastProductId,
        ];
    }
}
