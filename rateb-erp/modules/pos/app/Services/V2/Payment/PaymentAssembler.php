<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Payment;

use Rateb\App\Pos\Domain\V2\Payment\PosV2PaymentMethod;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto;
use Rateb\App\Pos\DTO\V2\Payment\PosV2PaymentLineDto;

/** Maps session payment rows to payment DTOs (T12). */
final class PaymentAssembler
{
    public function __construct(
        private readonly PaymentCalculator $calculator = new PaymentCalculator(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $rawPayments
     */
    public function buildSummary(array $rawPayments, string $totalDue, string $currency): PaymentSummaryDto
    {
        return $this->calculator->summarize($rawPayments, $totalDue, $currency);
    }

    /** @param array<string, mixed> $row */
    public function fromSessionRow(array $row, string $currency): ?PosV2PaymentLineDto
    {
        $id = trim((string) ($row['id'] ?? ''));
        $amount = (float) ($row['amount'] ?? 0);
        if ($id === '' || $amount <= 0) {
            return null;
        }

        return new PosV2PaymentLineDto(
            id: $id,
            method: PosV2PaymentMethod::fromString(strtolower(trim((string) ($row['method'] ?? 'cash'))))
                ?? PosV2PaymentMethod::Cash,
            amount: new \Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto(
                number_format($amount, 2, '.', ''),
                strtoupper($currency),
            ),
        );
    }
}
