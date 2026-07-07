<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class BarcodeChanged implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly string $locale = 'en'
    ) {
    }

    public function eventName(): string
    {
        return 'BarcodeChanged';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'locale' => $this->locale,
        ];
    }
}
