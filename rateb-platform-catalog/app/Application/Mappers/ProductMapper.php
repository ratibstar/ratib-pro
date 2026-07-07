<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Mappers;

use Rateb\PlatformCatalog\Application\DTO\ProductDto;

final class ProductMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public static function toProductDto(array $row): ProductDto
    {
        return new ProductDto(
            uuid: (string) $row['uuid'],
            sku: (string) $row['sku'],
            brandUuid: isset($row['brand_uuid']) && $row['brand_uuid'] !== null ? (string) $row['brand_uuid'] : null,
            categoryUuid: (string) $row['category_uuid'],
            familyUuid: isset($row['family_uuid']) && $row['family_uuid'] !== null ? (string) $row['family_uuid'] : null,
            unitUuid: (string) $row['unit_uuid'],
            isBundle: (bool) (int) ($row['is_bundle'] ?? 0),
            primaryBarcode: isset($row['primary_barcode']) ? (string) $row['primary_barcode'] : null,
            weightKg: isset($row['weight_kg']) ? (string) $row['weight_kg'] : null,
            lengthCm: isset($row['length_cm']) ? (string) $row['length_cm'] : null,
            widthCm: isset($row['width_cm']) ? (string) $row['width_cm'] : null,
            heightCm: isset($row['height_cm']) ? (string) $row['height_cm'] : null,
            manufacturerId: isset($row['manufacturer_id']) ? (int) $row['manufacturer_id'] : null,
            countryId: isset($row['country_id']) ? (int) $row['country_id'] : null,
            warrantyMonths: isset($row['warranty_months']) ? (int) $row['warranty_months'] : null,
            taxClass: isset($row['tax_class']) ? (string) $row['tax_class'] : null,
            status: (string) $row['status'],
            versionNumber: (int) $row['version_number'],
            lockVersion: (int) $row['lock_version'],
            publishAt: isset($row['publish_at']) ? (string) $row['publish_at'] : null,
            archiveAt: isset($row['archive_at']) ? (string) $row['archive_at'] : null,
            publishedAt: isset($row['published_at']) ? (string) $row['published_at'] : null,
            approvedBy: isset($row['approved_by']) ? (int) $row['approved_by'] : null,
            approvedAt: isset($row['approved_at']) ? (string) $row['approved_at'] : null,
            searchWeight: (string) $row['search_weight'],
            boostScore: (string) $row['boost_score'],
            name: (string) ($row['name'] ?? ''),
            shortDescription: isset($row['short_description']) ? (string) $row['short_description'] : null,
            description: isset($row['description']) ? (string) $row['description'] : null
        );
    }
}
