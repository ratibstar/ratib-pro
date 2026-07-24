<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/**
 * Narrow port for sync commit → checkout. Production impl: PosCheckoutService.
 */
interface PosCheckoutCompletePort
{
    /**
     * @param array<int, array<string, mixed>> $cartLines
     * @param array<int, array<string, mixed>> $payments
     * @param array<string, mixed> $invoiceDiscount
     * @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    public function complete(
        array $cartLines,
        array $payments,
        array $invoiceDiscount,
        array $scope,
        ?array $customer = null,
        float $taxRate = 0.15,
        bool $giftReceipt = false
    ): array;
}
