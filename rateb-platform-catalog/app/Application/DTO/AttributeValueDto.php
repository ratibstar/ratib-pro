<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class AttributeValueDto
{
    public function __construct(
        public readonly string $uuid,
        public readonly int $sortOrder,
        public readonly string $status,
        public readonly string $value
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'sort_order' => $this->sortOrder,
            'status' => $this->status,
            'value' => $this->value,
        ];
    }
}
