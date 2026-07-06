<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Checkout;

use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;

/** Normalizes V2 session payments to V1 checkout payment rows. */
final class CheckoutPaymentNormalizer
{
    /**
     * @param array<int, array<string, mixed>> $payments
     * @return array{payments: list<array<string, mixed>>, change_due: float}
     */
    public function normalizeForV1(array $payments, float $orderTotal): array
    {
        $target = round($orderTotal, 2);
        if ($target <= 0) {
            throw new PosV2PaymentValidationException(
                'ORDER_TOTAL_INVALID',
                'Order total must be greater than zero.',
            );
        }

        $mapped = [];
        $sum = 0.0;
        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $method = strtolower(trim((string) ($payment['method'] ?? 'cash')));
            $reference = trim((string) ($payment['reference_no'] ?? $payment['reference'] ?? ''));
            $mapped[] = [
                'method' => $method,
                'amount' => $amount,
                'reference_no' => $reference,
            ];
            $sum += $amount;
        }

        if ($mapped === []) {
            throw new PosV2PaymentValidationException(
                'PAYMENTS_REQUIRED',
                'At least one payment line is required.',
            );
        }

        if ($sum + 0.02 < $target) {
            throw new PosV2PaymentValidationException(
                'PAYMENT_INSUFFICIENT',
                'Recorded payments do not cover the order total.',
            );
        }

        if (abs($sum - $target) <= 0.02) {
            return [
                'payments' => $this->roundRows($mapped),
                'change_due' => 0.0,
            ];
        }

        $changeDue = round($sum - $target, 2);
        $remaining = $target;
        $fitted = [];
        foreach ($mapped as $row) {
            if ($remaining <= 0.001) {
                break;
            }
            $apply = min((float) $row['amount'], $remaining);
            if ($apply <= 0) {
                continue;
            }
            $fitted[] = [
                'method' => $row['method'],
                'amount' => round($apply, 2),
                'reference_no' => $row['reference_no'],
            ];
            $remaining = round($remaining - $apply, 2);
        }

        return [
            'payments' => $fitted,
            'change_due' => $changeDue,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function roundRows(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'method' => $row['method'],
                'amount' => round((float) $row['amount'], 2),
                'reference_no' => $row['reference_no'] ?? '',
            ],
            $rows,
        );
    }
}
