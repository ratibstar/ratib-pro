<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductFileUploaded implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly string $fileUuid,
        private readonly string $storageKey,
        private readonly ?string $checksumSha256
    ) {
    }

    public function eventName(): string
    {
        return 'ProductFileUploaded';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'file_uuid' => $this->fileUuid,
            'storage_key' => $this->storageKey,
            'checksum_sha256' => $this->checksumSha256,
        ];
    }
}
