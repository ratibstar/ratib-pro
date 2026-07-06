<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\DTO\V2\Cart\PosV2CartLineDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

/** Maps V1 session cart lines to V2 DTOs. */
final class PosV2CartLineMapper
{
    /**
     * @param array<int, array<string, mixed>> $lines
     * @return list<PosV2CartLineDto>
     */
    public function fromV1Lines(array $lines, string $currency): array
    {
        $mapped = [];

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $lineId = trim((string) ($line['id'] ?? ''));
            $productId = (int) ($line['product_id'] ?? 0);
            $qty = (string) ($line['quantity'] ?? '0');
            if ($lineId === '' || $productId < 1 || (float) $qty <= 0) {
                continue;
            }

            $unitAmount = $this->formatMoney((float) ($line['unit_price'] ?? 0));
            $lineAmount = $this->formatMoney((float) ($line['line_total'] ?? 0));

            $mapped[] = new PosV2CartLineDto(
                lineId: $lineId,
                productId: $productId,
                name: trim((string) ($line['item_name'] ?? '')),
                qty: $this->formatQty($qty),
                unitPrice: new PosV2MoneyDto($unitAmount, $currency),
                lineTotal: new PosV2MoneyDto($lineAmount, $currency),
            );
        }

        return $mapped;
    }

    private function formatMoney(float $amount): string
    {
        return number_format(max(0, $amount), 2, '.', '');
    }

    private function formatQty(string $qty): string
    {
        $value = (float) $qty;
        if (abs($value - round($value)) < 0.0001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
