<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\Services\PosPricingService;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartLineDto;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Discount\CartDiscountSummary;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;

/** Computes cart totals via the same V1 pricing pipeline used at checkout. */
final class PosV2CartTotalsCalculator
{
    private const DEFAULT_TAX_RATE = 0.15;

    public function __construct(
        private readonly PosPricingService $pricing = new PosPricingService(),
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
        unset($discountSummary);

        if ($v1Lines !== []) {
            $pricing = $this->pricing->calculate($v1Lines, $invoiceDiscount, self::DEFAULT_TAX_RATE);

            return new PosV2CartTotalsDto(
                subtotal: $this->toDto(PosV2Money::fromDecimalString($this->money($pricing['subtotal'] ?? 0), $currency)),
                discount: $this->toDto(PosV2Money::fromDecimalString($this->money($pricing['discount_total'] ?? 0), $currency)),
                tax: $this->toDto(PosV2Money::fromDecimalString($this->money($pricing['tax'] ?? 0), $currency)),
                total: $this->toDto(PosV2Money::fromDecimalString($this->money($pricing['total'] ?? 0), $currency)),
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

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
