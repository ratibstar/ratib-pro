<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductBarcodeDto
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $barcode,
        public readonly string $barcodeType,
        public readonly bool $isPrimary,
        public readonly array $extra = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'uuid' => $this->uuid,
            'barcode' => $this->barcode,
            'barcode_type' => $this->barcodeType,
            'is_primary' => $this->isPrimary,
        ], $this->extra);
    }
}
