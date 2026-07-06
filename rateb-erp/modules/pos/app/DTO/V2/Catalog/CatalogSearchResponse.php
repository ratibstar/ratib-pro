<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Catalog;

/** Paginated catalog search response. */
final readonly class CatalogSearchResponse
{
    /**
     * @param list<PosV2CatalogProductDto> $products
     */
    public function __construct(
        public array $products,
        public PosV2PaginationDto $pagination,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'products' => array_map(
                static fn (PosV2CatalogProductDto $product): array => $product->toArray(),
                $this->products,
            ),
            'pagination' => $this->pagination->toArray(),
        ];
    }
}
