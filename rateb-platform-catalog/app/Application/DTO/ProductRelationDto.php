<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductRelationDto
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $relatedProductUuid,
        public readonly string $relationType,
        public readonly int $sortOrder,
        public readonly bool $isBidirectional,
        public readonly ?string $relatedProductName = null,
        public readonly ?string $relatedProductSku = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'related_product_uuid' => $this->relatedProductUuid,
            'relation_type' => $this->relationType,
            'sort_order' => $this->sortOrder,
            'is_bidirectional' => $this->isBidirectional,
            'related_product_name' => $this->relatedProductName,
            'related_product_sku' => $this->relatedProductSku,
        ];
    }
}
