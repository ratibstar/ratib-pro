<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class AssetTypeDto
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $code,
        public readonly string $category,
        public readonly bool $isSystem,
        public readonly string $status,
        public readonly string $name
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'category' => $this->category,
            'is_system' => $this->isSystem,
            'status' => $this->status,
            'name' => $this->name,
        ];
    }
}
