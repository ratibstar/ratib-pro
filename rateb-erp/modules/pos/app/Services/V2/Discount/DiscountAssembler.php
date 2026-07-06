<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Discount;

use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;
use Rateb\App\Pos\DTO\V2\Discount\CartDiscountSummary;
use Rateb\App\Pos\DTO\V2\Discount\PosV2CartDiscountDto;
use Rateb\App\Pos\DTO\V2\Discount\PosV2LineDiscountDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

/** Builds discount DTOs from V1 session state (T11). */
final class DiscountAssembler
{
    public function __construct(
        private readonly DiscountCalculator $calculator = new DiscountCalculator(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $invoiceDiscount
     */
    public function buildSummary(array $lines, array $invoiceDiscount, string $currency): CartDiscountSummary
    {
        $lineDiscountTotal = $this->calculator->lineDiscountTotal($lines, $currency);
        $cartDiscountTotal = $this->calculator->cartDiscountAmount($lines, $invoiceDiscount, $currency);
        $totalDiscount = $lineDiscountTotal->add($cartDiscountTotal);

        $cartDiscount = $this->buildCartDiscountDto($invoiceDiscount, $cartDiscountTotal);

        return new CartDiscountSummary(
            lineDiscountTotal: $this->toDto($lineDiscountTotal),
            cartDiscountTotal: $this->toDto($cartDiscountTotal),
            totalDiscount: $this->toDto($totalDiscount),
            cartDiscount: $cartDiscount,
        );
    }

    /**
     * @param array<string, mixed> $line
     */
    public function buildLineDiscountDto(array $line, string $currency): ?PosV2LineDiscountDto
    {
        $amount = $this->calculator->lineDiscountAmount($line, $currency);
        if ((float) $amount->amount <= 0) {
            return null;
        }

        $fixed = (float) ($line['discount_amount'] ?? 0);
        if ($fixed > 0) {
            return new PosV2LineDiscountDto(
                type: PosV2DiscountType::Fixed,
                value: number_format($fixed, 2, '.', ''),
                amount: $this->toDto($amount),
            );
        }

        $percent = (float) ($line['discount_percent'] ?? 0);
        if ($percent > 0) {
            return new PosV2LineDiscountDto(
                type: PosV2DiscountType::Percent,
                value: rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.'),
                amount: $this->toDto($amount),
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $invoiceDiscount
     */
    private function buildCartDiscountDto(array $invoiceDiscount, PosV2Money $amount): ?PosV2CartDiscountDto
    {
        if ((float) $amount->amount <= 0) {
            return null;
        }

        $type = PosV2DiscountType::fromString((string) ($invoiceDiscount['type'] ?? 'fixed'));
        $value = (float) ($invoiceDiscount['value'] ?? 0);
        if ($type === null || $value <= 0) {
            return null;
        }

        return new PosV2CartDiscountDto(
            type: $type,
            value: $type === PosV2DiscountType::Percent
                ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.')
                : number_format($value, 2, '.', ''),
            amount: $this->toDto($amount),
        );
    }

    private function toDto(PosV2Money $money): PosV2MoneyDto
    {
        return new PosV2MoneyDto($money->amount, $money->currency);
    }
}
