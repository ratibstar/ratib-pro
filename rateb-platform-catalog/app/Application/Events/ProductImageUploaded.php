<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductImageUploaded implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly string $imageUuid,
        private readonly string $storageKey,
        private readonly string $checksumSha256
    ) {
    }

    public function eventName(): string
    {
        return 'ProductImageUploaded';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'image_uuid' => $this->imageUuid,
            'storage_key' => $this->storageKey,
            'checksum_sha256' => $this->checksumSha256,
        ];
    }
}
