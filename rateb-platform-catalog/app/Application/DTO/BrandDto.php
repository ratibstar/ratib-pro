<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class BrandDto
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $slug,
        public readonly ?string $logoPath,
        public readonly ?string $website,
        public readonly ?string $countryCode,
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
            'slug' => $this->slug,
            'logo_path' => $this->logoPath,
            'website' => $this->website,
            'country_code' => $this->countryCode,
            'status' => $this->status,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
