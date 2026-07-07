<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductApproved implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly string $locale = 'en'
    ) {
    }

    public function eventName(): string
    {
        return 'ProductApproved';
    }

    public function payload(): array
    {
        return ['product_uuid' => $this->productUuid, 'locale' => $this->locale];
    }
}
