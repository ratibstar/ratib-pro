<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Catalog;

/** Single catalog product response. */
final readonly class CatalogProductResponse
{
    public function __construct(
        public PosV2CatalogProductDto $product,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->product->toArray();
    }
}
