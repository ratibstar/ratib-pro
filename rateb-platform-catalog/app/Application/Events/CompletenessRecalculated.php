<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class CompletenessRecalculated implements DomainEvent
{
    /**
     * @param list<array<string, mixed>> $scores
     */
    public function __construct(
        private readonly string $productUuid,
        private readonly array $scores
    ) {
    }

    public function eventName(): string
    {
        return 'CompletenessRecalculated';
    }

    public function payload(): array
    {
        return [
            'product_uuid' => $this->productUuid,
            'scores' => $this->scores,
        ];
    }
}
