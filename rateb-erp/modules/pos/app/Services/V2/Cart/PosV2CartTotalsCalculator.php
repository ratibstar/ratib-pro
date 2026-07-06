<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartLineDto;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Discount\CartDiscountSummary;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;
use Rateb\App\Pos\Services\PosCheckoutPricingResolver;

/** Computes cart totals via the same V1 checkout pricing pipeline. */
final class PosV2CartTotalsCalculator
{
    public function __construct(
        private readonly PosCheckoutPricingResolver $pricingResolver = new PosCheckoutPricingResolver(),
    ) {
    }

    /**
     * @param list<PosV2CartLineDto> $lines
     * @param array<int, array<string, mixed>> $v1Lines
     * @param array<string, mixed> $invoiceDiscount
     * @param array<string, mixed> $pricingSession tax_rate, coupon_code, points_redeem, customer
     */
    public function calculate(
        PosV2CartScope $scope,
        array $lines,
        array $v1Lines = [],
        array $invoiceDiscount = [],
        ?CartDiscountSummary $discountSummary = null,
        array $pricingSession = [],
    ): PosV2CartTotalsDto {
        unset($discountSummary);

        if ($v1Lines !== [] && $scope->companyId > 0 && $scope->branchId > 0) {
            $taxRate = (float) ($pricingSession['tax_rate'] ?? 0.15);
            $customer = is_array($pricingSession['customer'] ?? null) ? $pricingSession['customer'] : null;
            $resolved = $this->pricingResolver->resolve(
                $v1Lines,
                $invoiceDiscount,
                [
                    'company_id' => $scope->companyId,
                    'branch_id' => $scope->branchId,
                    'coupon_code' => (string) ($pricingSession['coupon_code'] ?? ''),
                    'points_redeem' => (float) ($pricingSession['points_redeem'] ?? 0),
                ],
                $customer,
                $taxRate,
            );
            $pricing = $resolved['pricing'];

            return new PosV2CartTotalsDto(
                subtotal: $this->toDto(PosV2Money::fromDecimalString($this->money($pricing['subtotal'] ?? 0), $scope->currency)),
                discount: $this->toDto(PosV2Money::fromDecimalString($this->money($pricing['discount_total'] ?? 0), $scope->currency)),
                tax: $this->toDto(PosV2Money::fromDecimalString($this->money($pricing['tax'] ?? 0), $scope->currency)),
                total: $this->toDto(PosV2Money::fromDecimalString($this->money($pricing['total'] ?? 0), $scope->currency)),
            );
        }

        $subtotal = PosV2Money::zero($scope->currency);
        foreach ($lines as $line) {
            $subtotal = $subtotal->add(
                PosV2Money::fromDecimalString($line->lineTotal->amount, $scope->currency),
            );
        }

        $discount = PosV2Money::zero($scope->currency);
        $tax = PosV2Money::zero($scope->currency);
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
