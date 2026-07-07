<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Mappers;

use Rateb\PlatformCatalog\Application\DTO\ProductSeoDto;

final class ProductSeoMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public static function toDto(array $row): ProductSeoDto
    {
        $translations = is_array($row['translations'] ?? null) ? $row['translations'] : [];

        return new ProductSeoDto(
            uuid: (string) ($row['uuid'] ?? ''),
            productUuid: (string) ($row['product_uuid'] ?? ''),
            canonicalUrl: isset($row['canonical_url']) ? (string) $row['canonical_url'] : null,
            translations: array_values($translations)
        );
    }
}
