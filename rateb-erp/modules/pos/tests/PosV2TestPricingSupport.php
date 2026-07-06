<?php

declare(strict_types=1);

use Rateb\App\Pos\Services\PosCheckoutPricingResolver;
use Rateb\App\Pos\Services\PosRewardService;
use Rateb\App\Pos\Services\PosSellPriceService;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAssembler;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartTotalsCalculator;

/** Test helpers for checkout-aligned pricing without a live database. */
final class PosV2TestPricingSupport
{
    public static function passThroughResolver(): PosCheckoutPricingResolver
    {
        return new PosCheckoutPricingResolver(
            sellPrices: self::passThroughSellPrices(),
        );
    }

    public static function cartAssembler(?PosCheckoutPricingResolver $resolver = null): PosV2CartAssembler
    {
        return new PosV2CartAssembler(
            totalsCalculator: new PosV2CartTotalsCalculator(
                pricingResolver: $resolver ?? self::passThroughResolver(),
            ),
        );
    }

    public static function resolverWithSellPrices(
        PosSellPriceService $sellPrices,
        ?PosRewardService $rewards = null,
    ): PosCheckoutPricingResolver {
        return new PosCheckoutPricingResolver(
            sellPrices: $sellPrices,
            rewards: $rewards ?? new PosRewardService(),
        );
    }

    public static function passThroughSellPrices(): PosSellPriceService
    {
        return new class extends PosSellPriceService {
            /** @param array<int, array<string, mixed>> $lines */
            public function applyToLines(array $lines, int $companyId, int $branchId, ?array $customer = null): array
            {
                $out = [];
                foreach ($lines as $line) {
                    if (!is_array($line)) {
                        continue;
                    }
                    $qty = max(0, (float) ($line['quantity'] ?? 0));
                    $unit = max(0, round((float) ($line['unit_price'] ?? 0), 2));
                    $out[] = array_merge($line, [
                        'unit_price' => $unit,
                        'line_total' => round($qty * $unit, 2),
                    ]);
                }

                return $out;
            }
        };
    }

    public static function groupPriceSellPrices(float $groupUnitPrice): PosSellPriceService
    {
        return new class($groupUnitPrice) extends PosSellPriceService {
            public function __construct(private readonly float $groupUnitPrice)
            {
            }

            /** @param array<int, array<string, mixed>> $lines */
            public function applyToLines(array $lines, int $companyId, int $branchId, ?array $customer = null): array
            {
                $out = [];
                foreach ($lines as $line) {
                    if (!is_array($line)) {
                        continue;
                    }
                    $qty = max(0, (float) ($line['quantity'] ?? 0));
                    $unit = $this->groupUnitPrice;
                    $out[] = array_merge($line, [
                        'unit_price' => $unit,
                        'price_source' => 'group',
                        'line_total' => round($qty * $unit, 2),
                    ]);
                }

                return $out;
            }
        };
    }

    public static function couponRewardService(float $discountAmount): PosRewardService
    {
        return new class($discountAmount) extends PosRewardService {
            public function __construct(private readonly float $discountAmount)
            {
            }

            public function buildRewardDiscounts(
                array $invoiceDiscount,
                ?string $couponCode,
                float $pointsRedeem,
                int $companyId,
                int $customerId,
                float $netSubtotal
            ): array {
                unset($pointsRedeem, $companyId, $customerId, $netSubtotal);
                if ($couponCode === null || trim($couponCode) === '') {
                    return [
                        'invoice_discount' => $invoiceDiscount,
                        'coupon_id' => null,
                        'coupon_code' => null,
                        'points_redeemed' => 0.0,
                    ];
                }

                $existing = (float) ($invoiceDiscount['value'] ?? 0);

                return [
                    'invoice_discount' => [
                        'type' => 'amount',
                        'value' => round($existing + $this->discountAmount, 2),
                    ],
                    'coupon_id' => 99,
                    'coupon_code' => strtoupper(trim($couponCode)),
                    'points_redeemed' => 0.0,
                ];
            }
        };
    }

    public static function pointsRewardService(float $pointsRedeem, float $moneyValue): PosRewardService
    {
        return new class($pointsRedeem, $moneyValue) extends PosRewardService {
            public function __construct(
                private readonly float $pointsRedeem,
                private readonly float $moneyValue,
            ) {
            }

            public function buildRewardDiscounts(
                array $invoiceDiscount,
                ?string $couponCode,
                float $pointsRedeem,
                int $companyId,
                int $customerId,
                float $netSubtotal
            ): array {
                unset($couponCode, $companyId, $netSubtotal);
                if ($pointsRedeem <= 0 || $customerId < 1) {
                    return [
                        'invoice_discount' => $invoiceDiscount,
                        'coupon_id' => null,
                        'coupon_code' => null,
                        'points_redeemed' => 0.0,
                    ];
                }

                $existing = (float) ($invoiceDiscount['value'] ?? 0);

                return [
                    'invoice_discount' => [
                        'type' => 'amount',
                        'value' => round($existing + $this->moneyValue, 2),
                    ],
                    'coupon_id' => null,
                    'coupon_code' => null,
                    'points_redeemed' => $this->pointsRedeem,
                ];
            }
        };
    }
}
