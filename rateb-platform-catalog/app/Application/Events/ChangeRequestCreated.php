<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ChangeRequestCreated implements DomainEvent
{
    public function __construct(
        private readonly string $changeRequestUuid,
        private readonly string $productUuid
    ) {
    }

    public function eventName(): string
    {
        return 'ChangeRequestSubmitted';
    }

    public function payload(): array
    {
        return [
            'change_request_uuid' => $this->changeRequestUuid,
            'product_uuid' => $this->productUuid,
        ];
    }
}
