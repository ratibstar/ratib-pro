<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class VariantCreated implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly string $variantUuid,
        private readonly string $locale = 'en'
    ) {
    }

    public function eventName(): string
    {
        return 'VariantCreated';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'variant_uuid' => $this->variantUuid,
            'locale' => $this->locale,
        ];
    }
}
