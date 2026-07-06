<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/** Shared sell-price + reward + VAT pipeline for cart preview and checkout. */
final class PosCheckoutPricingResolver
{
    public function __construct(
        private PosPricingService $pricing = new PosPricingService(),
        private PosSellPriceService $sellPrices = new PosSellPriceService(),
        private PosRewardService $rewards = new PosRewardService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $cartLines
     * @param array<string, mixed> $invoiceDiscount
     * @param array<string, mixed> $scope company_id, branch_id, coupon_code?, points_redeem?
     * @return array{pricing: array<string, mixed>, lines: array<int, array<string, mixed>>, reward_bundle: array<string, mixed>}
     */
    public function resolve(
        array $cartLines,
        array $invoiceDiscount,
        array $scope,
        ?array $customer = null,
        float $taxRate = 0.15,
    ): array {
        $companyId = (int) ($scope['company_id'] ?? 0);
        $branchId = (int) ($scope['branch_id'] ?? 0);

        $lines = $this->sellPrices->applyToLines($cartLines, $companyId, $branchId, $customer);
        $prePricing = $this->pricing->calculate($lines, $invoiceDiscount, $taxRate);
        $customerId = !empty($customer['id']) ? (int) $customer['id'] : 0;
        $couponCode = trim((string) ($scope['coupon_code'] ?? ''));
        $rewardBundle = $this->rewards->buildRewardDiscounts(
            $invoiceDiscount,
            $couponCode !== '' ? $couponCode : null,
            (float) ($scope['points_redeem'] ?? 0),
            $companyId,
            $customerId,
            (float) ($prePricing['net_subtotal'] ?? $prePricing['subtotal'] ?? 0)
        );
        $invoiceDiscount = $rewardBundle['invoice_discount'];
        $pricing = $this->pricing->calculate($lines, $invoiceDiscount, $taxRate);

        return [
            'pricing' => $pricing,
            'lines' => $lines,
            'reward_bundle' => $rewardBundle,
        ];
    }
}
