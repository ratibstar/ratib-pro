<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Catalog;

/** POS catalog product card/detail. */
final readonly class PosV2CatalogProductDto
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public PosV2MoneyDto $price,
        public ?string $imageUrl,
        public bool $inStock,
        public bool $requiresWeight,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price->toArray(),
            'image_url' => $this->imageUrl,
            'in_stock' => $this->inStock,
            'requires_weight' => $this->requiresWeight,
        ];
    }
}
