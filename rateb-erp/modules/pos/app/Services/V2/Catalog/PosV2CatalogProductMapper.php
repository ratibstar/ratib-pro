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

        $name = (string) ($row['item_name'] ?? '');
        // Guest-menu / retail seed safety net: never show ?? for RC-*/GM-* when PHP seed has Arabic.
        if ($this->looksLikeMojibake($name) && $sku !== '' && (str_starts_with($sku, 'RC-') || str_starts_with($sku, 'GM-'))) {
            if (class_exists(\Rateb\App\GuestMenu\Services\PlatformRetailCatalogSeedData::class)) {
                $seedName = \Rateb\App\GuestMenu\Services\PlatformRetailCatalogSeedData::nameBySku()[$sku] ?? '';
                if ($seedName !== '' && !$this->looksLikeMojibake($seedName)) {
                    $name = $seedName;
                }
            }
        }

        return new PosV2CatalogProductDto(
            id: (int) ($row['id'] ?? 0),
            sku: $sku,
            name: $name,
            price: new PosV2MoneyDto(
                amount: number_format(max(0, $unitPrice), 2, '.', ''),
                currency: $currency,
            ),
            imageUrl: $this->optionalString($row['image_url'] ?? null),
            inStock: (bool) ($availability['can_add'] ?? ($availability['available'] ?? 0) > 0),
            requiresWeight: $this->requiresWeight((string) ($row['unit'] ?? '')),
        );
    }

    private function looksLikeMojibake(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return true;
        }
        if (str_contains($name, '??') || str_contains($name, "\u{FFFD}")) {
            return true;
        }
        if (preg_match('/^\?+$/u', $name) === 1) {
            return true;
        }
        $q = substr_count($name, '?');

        return $q >= 2 && $q >= (int) floor(mb_strlen($name, 'UTF-8') * 0.5);
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
