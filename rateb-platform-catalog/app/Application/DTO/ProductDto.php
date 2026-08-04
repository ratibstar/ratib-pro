<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductDto
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $sku,
        public readonly ?string $brandUuid,
        public readonly string $categoryUuid,
        public readonly ?string $familyUuid,
        public readonly string $unitUuid,
        public readonly bool $isBundle,
        public readonly ?string $primaryBarcode,
        public readonly ?string $weightKg,
        public readonly ?string $lengthCm,
        public readonly ?string $widthCm,
        public readonly ?string $heightCm,
        public readonly ?int $manufacturerId,
        public readonly ?int $countryId,
        public readonly ?int $warrantyMonths,
        public readonly ?string $taxClass,
        public readonly string $status,
        public readonly int $versionNumber,
        public readonly int $lockVersion,
        public readonly ?string $publishAt,
        public readonly ?string $archiveAt,
        public readonly ?string $publishedAt,
        public readonly ?int $approvedBy,
        public readonly ?string $approvedAt,
        public readonly string $searchWeight,
        public readonly string $boostScore,
        public readonly string $name,
        public readonly ?string $shortDescription,
        public readonly ?string $description,
        public readonly ?string $categoryName = null
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'brand_uuid' => $this->brandUuid,
            'category_uuid' => $this->categoryUuid,
            'category_name' => $this->categoryName,
            'family_uuid' => $this->familyUuid,
            'unit_uuid' => $this->unitUuid,
            'is_bundle' => $this->isBundle,
            'primary_barcode' => $this->primaryBarcode,
            'weight_kg' => $this->weightKg,
            'length_cm' => $this->lengthCm,
            'width_cm' => $this->widthCm,
            'height_cm' => $this->heightCm,
            'manufacturer_id' => $this->manufacturerId,
            'country_id' => $this->countryId,
            'warranty_months' => $this->warrantyMonths,
            'tax_class' => $this->taxClass,
            'status' => $this->status,
            'version_number' => $this->versionNumber,
            'lock_version' => $this->lockVersion,
            'publish_at' => $this->publishAt,
            'archive_at' => $this->archiveAt,
            'published_at' => $this->publishedAt,
            'approved_by' => $this->approvedBy,
            'approved_at' => $this->approvedAt,
            'search_weight' => $this->searchWeight,
            'boost_score' => $this->boostScore,
            'name' => $this->name,
            'short_description' => $this->shortDescription,
            'description' => $this->description,
        ];
    }
}
