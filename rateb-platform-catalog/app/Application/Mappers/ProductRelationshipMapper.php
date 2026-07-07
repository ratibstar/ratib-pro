<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Mappers;

use Rateb\PlatformCatalog\Application\DTO\ProductAttributeDto;
use Rateb\PlatformCatalog\Application\DTO\ProductBarcodeDto;
use Rateb\PlatformCatalog\Application\DTO\ProductBundleComponentDto;
use Rateb\PlatformCatalog\Application\DTO\ProductRelationDto;
use Rateb\PlatformCatalog\Application\DTO\ProductVariantDto;

final class ProductRelationshipMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public static function toBarcodeDto(array $row): ProductBarcodeDto
    {
        return new ProductBarcodeDto(
            uuid: (string) $row['uuid'],
            barcode: (string) $row['barcode'],
            barcodeType: (string) $row['barcode_type'],
            isPrimary: (bool) (int) ($row['is_primary'] ?? 0)
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $barcodes
     * @param list<array<string, mixed>> $optionValues
     */
    public static function toVariantDto(array $row, array $barcodes = [], array $optionValues = []): ProductVariantDto
    {
        return new ProductVariantDto(
            uuid: (string) $row['uuid'],
            sku: (string) $row['sku'],
            primaryBarcode: isset($row['primary_barcode']) ? (string) $row['primary_barcode'] : null,
            sortOrder: (int) ($row['sort_order'] ?? 0),
            weightKg: isset($row['weight_kg']) ? (string) $row['weight_kg'] : null,
            lengthCm: isset($row['length_cm']) ? (string) $row['length_cm'] : null,
            widthCm: isset($row['width_cm']) ? (string) $row['width_cm'] : null,
            heightCm: isset($row['height_cm']) ? (string) $row['height_cm'] : null,
            status: (string) $row['status'],
            isDefault: (bool) (int) ($row['is_default'] ?? 0),
            name: isset($row['name']) ? (string) $row['name'] : null,
            description: isset($row['description']) ? (string) $row['description'] : null,
            barcodes: $barcodes,
            optionValues: $optionValues
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $translations
     */
    public static function toAttributeDto(array $row, array $translations = []): ProductAttributeDto
    {
        return new ProductAttributeDto(
            uuid: (string) $row['uuid'],
            attributeUuid: (string) $row['attribute_uuid'],
            attributeCode: (string) $row['attribute_code'],
            attributeValueUuid: isset($row['attribute_value_uuid']) ? (string) $row['attribute_value_uuid'] : null,
            valueText: isset($row['value_text']) ? (string) $row['value_text'] : null,
            valueNumber: isset($row['value_number']) ? (string) $row['value_number'] : null,
            valueBoolean: isset($row['value_boolean']) ? (bool) (int) $row['value_boolean'] : null,
            displayValue: isset($row['display_value']) ? (string) $row['display_value'] : null,
            translations: $translations
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function toBundleComponentDto(array $row): ProductBundleComponentDto
    {
        return new ProductBundleComponentDto(
            uuid: (string) $row['uuid'],
            componentProductUuid: (string) $row['component_product_uuid'],
            componentVariantUuid: isset($row['component_variant_uuid']) ? (string) $row['component_variant_uuid'] : null,
            quantity: (string) $row['quantity'],
            sortOrder: (int) ($row['sort_order'] ?? 0),
            isOptional: (bool) (int) ($row['is_optional'] ?? 0),
            componentName: isset($row['component_name']) ? (string) $row['component_name'] : null,
            componentSku: isset($row['component_sku']) ? (string) $row['component_sku'] : null
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function toRelationDto(array $row): ProductRelationDto
    {
        return new ProductRelationDto(
            uuid: (string) $row['uuid'],
            relatedProductUuid: (string) $row['related_product_uuid'],
            relationType: (string) $row['relation_type'],
            sortOrder: (int) ($row['sort_order'] ?? 0),
            isBidirectional: (bool) (int) ($row['is_bidirectional'] ?? 0),
            relatedProductName: isset($row['related_product_name']) ? (string) $row['related_product_name'] : null,
            relatedProductSku: isset($row['related_product_sku']) ? (string) $row['related_product_sku'] : null
        );
    }
}
