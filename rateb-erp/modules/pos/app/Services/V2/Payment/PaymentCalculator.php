<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Payment;

use Rateb\App\Pos\Domain\V2\Payment\PosV2PaymentMethod;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto;
use Rateb\App\Pos\DTO\V2\Payment\PosV2PaymentLineDto;

/** Cash payment totals using BCMath-safe money objects (T12). */
final class PaymentCalculator
{
    private const MAX_CASH_AMOUNT = '999999.99';

    /**
     * @param array<int, array<string, mixed>> $rawPayments
     */
    public function summarize(array $rawPayments, string $totalDue, string $currency): PaymentSummaryDto
    {
        $currency = strtoupper($currency);
        $due = PosV2Money::fromDecimalString($this->normalize($totalDue), $currency);
        $paid = PosV2Money::zero($currency);
        $lines = [];

        foreach ($rawPayments as $row) {
            if (!is_array($row)) {
                continue;
            }
            $amount = (float) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $method = PosV2PaymentMethod::fromString(strtolower(trim((string) ($row['method'] ?? 'cash'))))
                ?? PosV2PaymentMethod::Cash;
            $money = PosV2Money::fromDecimalString(number_format($amount, 2, '.', ''), $currency);
            $paid = $paid->add($money);
            $lines[] = new PosV2PaymentLineDto(
                id: (string) ($row['id'] ?? ''),
                method: $method,
                amount: $this->toDto($money),
            );
        }

        $remaining = $due;
        $change = PosV2Money::zero($currency);

        if ($paid->isGreaterThan($due) || $this->equals($paid, $due)) {
            if ($paid->isGreaterThan($due)) {
                $change = $paid->subtract($due);
            }
            $remaining = PosV2Money::zero($currency);
        } else {
            $remaining = $due->subtract($paid);
        }

        return new PaymentSummaryDto(
            payments: $lines,
            totalDue: $this->toDto($due),
            paid: $this->toDto($paid),
            remaining: $this->toDto($remaining),
            changeDue: $this->toDto($change),
        );
    }

    public function assertAmountWithinLimit(string $amount): void
    {
        $normalized = $this->normalize($amount);
        if (function_exists('bccomp')) {
            if (bccomp($normalized, self::MAX_CASH_AMOUNT, 2) === 1) {
                throw new \InvalidArgumentException('Cash amount exceeds allowed limit.');
            }

            return;
        }

        if ((float) $normalized > (float) self::MAX_CASH_AMOUNT) {
            throw new \InvalidArgumentException('Cash amount exceeds allowed limit.');
        }
    }

    private function equals(PosV2Money $left, PosV2Money $right): bool
    {
        if (function_exists('bccomp')) {
            return bccomp($left->amount, $right->amount, 2) === 0;
        }

        return abs((float) $left->amount - (float) $right->amount) < 0.001;
    }

    private function normalize(string $amount): string
    {
        if (function_exists('bcadd')) {
            return bcadd(trim($amount), '0', 2);
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private function toDto(PosV2Money $money): PosV2MoneyDto
    {
        return new PosV2MoneyDto($money->amount, $money->currency);
    }
}
