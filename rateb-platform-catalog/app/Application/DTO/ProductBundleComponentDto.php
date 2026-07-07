<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductBundleComponentDto
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $componentProductUuid,
        public readonly ?string $componentVariantUuid,
        public readonly string $quantity,
        public readonly int $sortOrder,
        public readonly bool $isOptional,
        public readonly ?string $componentName = null,
        public readonly ?string $componentSku = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'component_product_uuid' => $this->componentProductUuid,
            'component_variant_uuid' => $this->componentVariantUuid,
            'quantity' => $this->quantity,
            'sort_order' => $this->sortOrder,
            'is_optional' => $this->isOptional,
            'component_name' => $this->componentName,
            'component_sku' => $this->componentSku,
        ];
    }
}
