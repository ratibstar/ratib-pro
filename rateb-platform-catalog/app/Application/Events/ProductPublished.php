<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductPublished implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly int $versionNumber,
        private readonly string $locale = 'en'
    ) {
    }

    public function eventName(): string
    {
        return 'ProductPublished';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'version_number' => $this->versionNumber,
            'locale' => $this->locale,
        ];
    }
}
