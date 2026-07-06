<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartLineDto;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

/** Computes cart totals using money value objects (T09 placeholders for tax/discount). */
final class PosV2CartTotalsCalculator
{
    /**
     * @param list<PosV2CartLineDto> $lines
     */
    public function calculate(array $lines, string $currency): PosV2CartTotalsDto
    {
        $subtotal = PosV2Money::zero($currency);

        foreach ($lines as $line) {
            $subtotal = $subtotal->add(
                PosV2Money::fromDecimalString($line->lineTotal->amount, $currency),
            );
        }

        $discount = PosV2Money::zero($currency);
        $tax = PosV2Money::zero($currency);
        $total = $subtotal->add($discount)->add($tax);

        return new PosV2CartTotalsDto(
            subtotal: $this->toDto($subtotal),
            discount: $this->toDto($discount),
            tax: $this->toDto($tax),
            total: $this->toDto($total),
        );
    }

    private function toDto(PosV2Money $money): PosV2MoneyDto
    {
        return new PosV2MoneyDto($money->amount, $money->currency);
    }
}
