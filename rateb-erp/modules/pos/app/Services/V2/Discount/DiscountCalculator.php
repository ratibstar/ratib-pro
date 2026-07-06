<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Discount;

use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;

/** Computes line and cart discount amounts (V1-compatible rules, no tax, T11). */
final class DiscountCalculator
{
    /**
     * @param array<string, mixed> $line
     */
    public function lineGross(array $line, string $currency): PosV2Money
    {
        $qty = max(0, (float) ($line['quantity'] ?? 0));
        $unit = max(0, round((float) ($line['unit_price'] ?? 0), 2));

        return PosV2Money::fromDecimalString(
            number_format(round($qty * $unit, 2), 2, '.', ''),
            $currency,
        );
    }

    /**
     * @param array<string, mixed> $line
     */
    public function lineDiscountAmount(array $line, string $currency): PosV2Money
    {
        $grossValue = (float) $this->lineGross($line, $currency)->amount;

        $fixed = (float) ($line['discount_amount'] ?? 0);
        if ($fixed > 0) {
            return PosV2Money::fromDecimalString(
                number_format(min($grossValue, round($fixed, 2)), 2, '.', ''),
                $currency,
            );
        }

        $percent = (float) ($line['discount_percent'] ?? 0);
        if ($percent > 0) {
            $amount = min($grossValue, round($grossValue * ($percent / 100), 2));

            return PosV2Money::fromDecimalString(number_format($amount, 2, '.', ''), $currency);
        }

        return PosV2Money::zero($currency);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $invoiceDiscount
     */
    public function cartDiscountAmount(array $lines, array $invoiceDiscount, string $currency): PosV2Money
    {
        $netValue = (float) $this->netSubtotalAfterLineDiscounts($lines, $currency)->amount;
        if ($netValue <= 0) {
            return PosV2Money::zero($currency);
        }

        $type = PosV2DiscountType::fromString((string) ($invoiceDiscount['type'] ?? 'fixed'));
        $value = (float) ($invoiceDiscount['value'] ?? 0);
        if ($value <= 0 || $type === null) {
            return PosV2Money::zero($currency);
        }

        if ($type === PosV2DiscountType::Percent) {
            $amount = min($netValue, round($netValue * ($value / 100), 2));
        } else {
            $amount = min($netValue, round($value, 2));
        }

        return PosV2Money::fromDecimalString(number_format($amount, 2, '.', ''), $currency);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    public function lineDiscountTotal(array $lines, string $currency): PosV2Money
    {
        $total = PosV2Money::zero($currency);
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $total = $total->add($this->lineDiscountAmount($line, $currency));
        }

        return $total;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    public function grossSubtotal(array $lines, string $currency): PosV2Money
    {
        $subtotal = PosV2Money::zero($currency);
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $qty = max(0, (float) ($line['quantity'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $subtotal = $subtotal->add($this->lineGross($line, $currency));
        }

        return $subtotal;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    public function netSubtotalAfterLineDiscounts(array $lines, string $currency): PosV2Money
    {
        return $this->grossSubtotal($lines, $currency)->subtract($this->lineDiscountTotal($lines, $currency));
    }
}
