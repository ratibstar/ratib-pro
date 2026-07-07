<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ChangeRequestApplied implements DomainEvent
{
    public function __construct(
        private readonly string $changeRequestUuid,
        private readonly string $productUuid,
        private readonly int $versionNumber
    ) {
    }

    public function eventName(): string
    {
        return 'ChangeRequestApplied';
    }

    public function payload(): array
    {
        return [
            'change_request_uuid' => $this->changeRequestUuid,
            'product_uuid' => $this->productUuid,
            'version_number' => $this->versionNumber,
        ];
    }
}
