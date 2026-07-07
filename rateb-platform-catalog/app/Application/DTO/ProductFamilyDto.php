<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductFamilyDto
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $code,
        public readonly ?string $brandUuid,
        public readonly string $status,
        public readonly string $name,
        public readonly ?string $description
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'brand_uuid' => $this->brandUuid,
            'status' => $this->status,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
