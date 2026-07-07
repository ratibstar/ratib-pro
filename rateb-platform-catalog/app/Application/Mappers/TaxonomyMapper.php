<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Mappers;

use Rateb\PlatformCatalog\Application\DTO\BrandDto;
use Rateb\PlatformCatalog\Application\DTO\CategoryDto;
use Rateb\PlatformCatalog\Application\DTO\SupplierDto;

final class TaxonomyMapper
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<CategoryDto>
     */
    public static function buildCategoryTree(array $rows): array
    {
        /** @var array<int, string> $idToUuid */
        $idToUuid = [];
        foreach ($rows as $row) {
            $idToUuid[(int) $row['id']] = (string) $row['uuid'];
        }

        /** @var array<string, CategoryDto> $nodes */
        $nodes = [];
        /** @var array<string, list<string>> $childrenMap */
        $childrenMap = [];

        foreach ($rows as $row) {
            $uuid = (string) $row['uuid'];
            $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $parentUuid = $parentId !== null ? ($idToUuid[$parentId] ?? null) : null;

            $nodes[$uuid] = new CategoryDto(
                uuid: $uuid,
                parentUuid: $parentUuid,
                slug: (string) $row['slug'],
                depth: (int) $row['depth'],
                path: (string) $row['path'],
                sortOrder: (int) $row['sort_order'],
                imagePath: isset($row['image_path']) ? (string) $row['image_path'] : null,
                status: (string) $row['status'],
                name: (string) ($row['name'] ?? ''),
                description: isset($row['description']) ? (string) $row['description'] : null
            );

            $parentKey = $parentUuid ?? '';
            $childrenMap[$parentKey][] = $uuid;
        }

        $attach = static function (string $uuid) use (&$attach, $nodes, $childrenMap): CategoryDto {
            $node = $nodes[$uuid];
            $childUuids = $childrenMap[$uuid] ?? [];
            $children = array_map(static fn (string $childUuid): CategoryDto => $attach($childUuid), $childUuids);

            return new CategoryDto(
                uuid: $node->uuid,
                parentUuid: $node->parentUuid,
                slug: $node->slug,
                depth: $node->depth,
                path: $node->path,
                sortOrder: $node->sortOrder,
                imagePath: $node->imagePath,
                status: $node->status,
                name: $node->name,
                description: $node->description,
                children: $children
            );
        };

        $roots = $childrenMap[''] ?? [];

        return array_map(static fn (string $uuid): CategoryDto => $attach($uuid), $roots);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function toCategoryDto(array $row, ?string $parentUuid = null): CategoryDto
    {
        return new CategoryDto(
            uuid: (string) $row['uuid'],
            parentUuid: $parentUuid,
            slug: (string) $row['slug'],
            depth: (int) $row['depth'],
            path: (string) $row['path'],
            sortOrder: (int) $row['sort_order'],
            imagePath: isset($row['image_path']) ? (string) $row['image_path'] : null,
            status: (string) $row['status'],
            name: (string) ($row['name'] ?? ''),
            description: isset($row['description']) ? (string) $row['description'] : null
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function toBrandDto(array $row): BrandDto
    {
        return new BrandDto(
            uuid: (string) $row['uuid'],
            slug: (string) $row['slug'],
            logoPath: isset($row['logo_path']) ? (string) $row['logo_path'] : null,
            website: isset($row['website']) ? (string) $row['website'] : null,
            countryCode: isset($row['country_code']) ? (string) $row['country_code'] : null,
            status: (string) $row['status'],
            name: (string) ($row['name'] ?? ''),
            description: isset($row['description']) ? (string) $row['description'] : null
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function toSupplierDto(array $row): SupplierDto
    {
        return new SupplierDto(
            uuid: (string) $row['uuid'],
            code: (string) $row['code'],
            contactEmail: isset($row['contact_email']) ? (string) $row['contact_email'] : null,
            contactPhone: isset($row['contact_phone']) ? (string) $row['contact_phone'] : null,
            countryCode: isset($row['country_code']) ? (string) $row['country_code'] : null,
            status: (string) $row['status'],
            name: (string) ($row['name'] ?? '')
        );
    }
}
