<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartLineDto;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\Services\V2\Discount\DiscountAssembler;
use Rateb\App\Pos\Services\V2\Payment\PaymentAssembler;

/** Assembles CartResponse from normalized V1 lines. */
final class PosV2CartAssembler
{
    public function __construct(
        private readonly PosV2CartLineMapper $lineMapper = new PosV2CartLineMapper(),
        private readonly PosV2CartTotalsCalculator $totalsCalculator = new PosV2CartTotalsCalculator(),
        private readonly DiscountAssembler $discountAssembler = new DiscountAssembler(),
        private readonly PaymentAssembler $paymentAssembler = new PaymentAssembler(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $v1Lines
     * @param array<string, mixed> $invoiceDiscount
     * @param array<int, array<string, mixed>> $sessionPayments
     * @param array<string, mixed> $pricingSession
     */
    public function assemble(
        PosV2CartScope $scope,
        array $v1Lines,
        ?PosV2CustomerSummaryDto $customer = null,
        array $invoiceDiscount = [],
        array $sessionPayments = [],
        array $pricingSession = [],
    ): CartResponse {
        $lines = $this->lineMapper->fromV1Lines($v1Lines, $scope->currency);
        $discounts = $this->discountAssembler->buildSummary($v1Lines, $invoiceDiscount, $scope->currency);
        $totals = $this->totalsCalculator->calculate(
            $scope,
            $lines,
            $v1Lines,
            $invoiceDiscount,
            $discounts,
            $pricingSession,
        );
        $payments = $this->paymentAssembler->buildSummary(
            $sessionPayments,
            $totals->total->amount,
            $scope->currency,
        );

        return new CartResponse(
            lines: $lines,
            totals: $totals,
            itemCount: $this->countItems($lines),
            customer: $customer,
            discounts: $discounts,
            payments: $payments,
        );
    }

    /**
     * @param list<PosV2CartLineDto> $lines
     */
    private function countItems(array $lines): int
    {
        $count = 0;
        foreach ($lines as $line) {
            $count += (int) round((float) $line->qty);
        }

        return $count;
    }
}
