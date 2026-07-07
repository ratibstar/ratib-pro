<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Mappers;

use Rateb\PlatformCatalog\Application\DTO\AttributeDto;
use Rateb\PlatformCatalog\Application\DTO\AttributeValueDto;
use Rateb\PlatformCatalog\Application\DTO\ProductFamilyDto;

final class FamilyAttributeMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public static function toProductFamilyDto(array $row): ProductFamilyDto
    {
        return new ProductFamilyDto(
            uuid: (string) $row['uuid'],
            code: (string) $row['code'],
            brandUuid: isset($row['brand_uuid']) && $row['brand_uuid'] !== null ? (string) $row['brand_uuid'] : null,
            status: (string) $row['status'],
            name: (string) ($row['name'] ?? ''),
            description: isset($row['description']) ? (string) $row['description'] : null
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $valueRows
     */
    public static function toAttributeDto(array $row, array $valueRows = []): AttributeDto
    {
        $values = array_map(
            static fn (array $valueRow): AttributeValueDto => new AttributeValueDto(
                uuid: (string) $valueRow['uuid'],
                sortOrder: (int) $valueRow['sort_order'],
                status: (string) $valueRow['status'],
                value: (string) ($valueRow['value'] ?? '')
            ),
            $valueRows
        );

        return new AttributeDto(
            uuid: (string) $row['uuid'],
            code: (string) $row['code'],
            inputType: (string) $row['input_type'],
            isVariantDefining: (bool) (int) ($row['is_variant_defining'] ?? 0),
            isFilterable: (bool) (int) ($row['is_filterable'] ?? 0),
            isVisible: (bool) (int) ($row['is_visible'] ?? 1),
            sortOrder: (int) $row['sort_order'],
            status: (string) $row['status'],
            name: (string) ($row['name'] ?? ''),
            values: $values
        );
    }
}
