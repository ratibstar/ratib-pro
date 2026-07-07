<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductVideoAdded implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly string $videoUuid,
        private readonly string $videoType
    ) {
    }

    public function eventName(): string
    {
        return 'ProductVideoAdded';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'video_uuid' => $this->videoUuid,
            'video_type' => $this->videoType,
        ];
    }
}
