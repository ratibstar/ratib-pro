<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class VariantDeleted implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly string $variantUuid
    ) {
    }

    public function eventName(): string
    {
        return 'VariantDeleted';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'variant_uuid' => $this->variantUuid,
        ];
    }
}
