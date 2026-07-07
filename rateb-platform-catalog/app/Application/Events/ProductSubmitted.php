<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductSubmitted implements DomainEvent
{
    public function __construct(
        private readonly string $productUuid,
        private readonly string $fromStatus,
        private readonly string $locale = 'en'
    ) {
    }

    public function eventName(): string
    {
        return 'ProductSubmittedForReview';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'from_status' => $this->fromStatus,
            'locale' => $this->locale,
        ];
    }
}
