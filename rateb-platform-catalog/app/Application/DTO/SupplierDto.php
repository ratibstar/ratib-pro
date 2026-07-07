<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class SupplierDto
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $code,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly ?string $countryCode,
        public readonly string $status,
        public readonly string $name
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'country_code' => $this->countryCode,
            'status' => $this->status,
            'name' => $this->name,
        ];
    }
}
