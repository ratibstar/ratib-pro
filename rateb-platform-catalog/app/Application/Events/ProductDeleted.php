<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductDeleted implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid
    ) {
    }

    public function eventName(): string
    {
        return 'ProductDeleted';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
        ];
    }
}
