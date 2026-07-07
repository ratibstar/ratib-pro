<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class VariantBarcodeChanged implements DomainEvent
{
    public function __construct(
        private readonly string $variantUuid,
        private readonly string $locale = 'en'
    ) {
    }

    public function eventName(): string
    {
        return 'VariantBarcodeChanged';
    }

    public function payload(): array
    {
        return [
            'variant_uuid' => $this->variantUuid,
            'locale' => $this->locale,
        ];
    }
}
