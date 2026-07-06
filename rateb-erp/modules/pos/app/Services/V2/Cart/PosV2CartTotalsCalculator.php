<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\DTO\V2\Cart\PosV2CartLineDto;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Discount\CartDiscountSummary;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;
use Rateb\App\Pos\Services\V2\Discount\DiscountCalculator;

/** Computes cart totals including discounts (tax placeholder, T11). */
final class PosV2CartTotalsCalculator
{
    public function __construct(
        private readonly DiscountCalculator $discountCalculator = new DiscountCalculator(),
    ) {
    }

    /**
     * @param list<PosV2CartLineDto> $lines
     * @param array<int, array<string, mixed>> $v1Lines
     * @param array<string, mixed> $invoiceDiscount
     */
    public function calculate(
        array $lines,
        string $currency,
        array $v1Lines = [],
        array $invoiceDiscount = [],
        ?CartDiscountSummary $discountSummary = null,
    ): PosV2CartTotalsDto {
        if ($discountSummary !== null) {
            $gross = $this->discountCalculator->grossSubtotal($v1Lines, $currency);
            $totalDiscount = PosV2Money::fromDecimalString(
                $discountSummary->totalDiscount->amount,
                $currency,
            );
            $tax = PosV2Money::zero($currency);
            $total = $gross->subtract($totalDiscount);

            return new PosV2CartTotalsDto(
                subtotal: $this->toDto($gross),
                discount: $this->toDto($totalDiscount),
                tax: $this->toDto($tax),
                total: $this->toDto($total),
            );
        }

        $subtotal = PosV2Money::zero($currency);
        foreach ($lines as $line) {
            $subtotal = $subtotal->add(
                PosV2Money::fromDecimalString($line->lineTotal->amount, $currency),
            );
        }

        $discount = PosV2Money::zero($currency);
        $tax = PosV2Money::zero($currency);
        $total = $subtotal->subtract($discount);

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
