<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductVariantDto
{
    /**
     * @param list<array<string, mixed>> $barcodes
     * @param list<array<string, mixed>> $optionValues
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $sku,
        public readonly ?string $primaryBarcode,
        public readonly int $sortOrder,
        public readonly ?string $weightKg,
        public readonly ?string $lengthCm,
        public readonly ?string $widthCm,
        public readonly ?string $heightCm,
        public readonly string $status,
        public readonly bool $isDefault,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly array $barcodes = [],
        public readonly array $optionValues = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'primary_barcode' => $this->primaryBarcode,
            'sort_order' => $this->sortOrder,
            'weight_kg' => $this->weightKg,
            'length_cm' => $this->lengthCm,
            'width_cm' => $this->widthCm,
            'height_cm' => $this->heightCm,
            'status' => $this->status,
            'is_default' => $this->isDefault,
            'name' => $this->name,
            'description' => $this->description,
            'barcodes' => $this->barcodes,
            'option_values' => $this->optionValues,
        ];
    }
}
