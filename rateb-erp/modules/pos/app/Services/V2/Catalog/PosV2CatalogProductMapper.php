<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Catalog;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogProductDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

/** Maps V1 inventory bridge payloads to catalog DTOs. */
final class PosV2CatalogProductMapper
{
    public function fromV1Product(array $row, string $currency): PosV2CatalogProductDto
    {
        $availability = is_array($row['availability'] ?? null) ? $row['availability'] : [];
        $unitPrice = (float) ($row['unit_price'] ?? 0);
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            $sku = trim((string) ($row['item_code'] ?? ''));
        }

        return new PosV2CatalogProductDto(
            id: (int) ($row['id'] ?? 0),
            sku: $sku,
            name: (string) ($row['item_name'] ?? ''),
            price: new PosV2MoneyDto(
                amount: number_format(max(0, $unitPrice), 2, '.', ''),
                currency: $currency,
            ),
            imageUrl: $this->optionalString($row['image_url'] ?? null),
            inStock: (bool) ($availability['can_add'] ?? ($availability['available'] ?? 0) > 0),
            requiresWeight: $this->requiresWeight((string) ($row['unit'] ?? '')),
        );
    }

    private function requiresWeight(string $unit): bool
    {
        $unit = strtolower(trim($unit));

        return in_array($unit, ['kg', 'g', 'gram', 'grams', 'kilogram', 'lb', 'lbs'], true);
    }

    private function optionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
