<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class VersionCreated implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly int $versionNumber,
        private readonly string $changeType
    ) {
    }

    public function eventName(): string
    {
        return 'VersionCreated';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'version_number' => $this->versionNumber,
            'change_type' => $this->changeType,
        ];
    }
}
