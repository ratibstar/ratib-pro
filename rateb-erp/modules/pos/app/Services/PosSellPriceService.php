<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Services\Bridge\PosPricingBridgeService;

/**
 * POS sell price resolution — manual override, group, branch, default.
 * Promotion slot reserved for future engine (via PosPricingBridgeService).
 */
class PosSellPriceService
{
    public function __construct(
        private PosPricingBridgeService $bridge = new PosPricingBridgeService(),
    ) {
    }

    /**
     * @param array<string, mixed> $line
     * @param array<string, mixed>|null $customer
     * @return array{unit_price: float, price_source: string, promotion_id: ?int}
     */
    public function resolveLine(
        array $line,
        int $companyId,
        int $branchId,
        ?array $customer = null
    ): array {
        $productId = (int) ($line['product_id'] ?? 0);
        if ($productId < 1 || $companyId < 1) {
            return ['unit_price' => 0.0, 'price_source' => 'default', 'promotion_id' => null];
        }

        $manualOverride = !empty($line['price_override']) || (($line['price_source'] ?? '') === 'manual');
        $manualPrice = (float) ($line['unit_price'] ?? 0);
        if ($manualOverride && $manualPrice > 0) {
            return [
                'unit_price' => round($manualPrice, 2),
                'price_source' => 'manual',
                'promotion_id' => null,
            ];
        }

        $promo = $this->bridge->resolveActivePromotion($productId, $companyId, [
            'branch_id' => $branchId,
            'customer_id' => (int) ($customer['id'] ?? 0),
            'quantity' => (float) ($line['quantity'] ?? 0),
        ]);
        $base = $this->bridge->inventoryPriceBase($productId, $companyId);
        $listPrice = (float) ($base['price'] ?? 0);
        if (is_array($promo) && (float) ($promo['price'] ?? 0) > 0) {
            return [
                'unit_price' => round((float) $promo['price'], 2),
                'price_source' => 'promotion',
                'promotion_id' => isset($promo['promotion_id']) ? (int) $promo['promotion_id'] : null,
            ];
        }

        $groupId = (int) ($customer['price_group_id'] ?? 0);
        if ($groupId > 0) {
            $groupPrice = $this->bridge->groupPrice($productId, $groupId, $companyId);
            if ($groupPrice !== null) {
                return [
                    'unit_price' => $groupPrice,
                    'price_source' => 'group',
                    'promotion_id' => null,
                ];
            }
        }

        if ($branchId > 0) {
            $branchPrice = $this->bridge->branchPrice($productId, $branchId, $companyId);
            if ($branchPrice !== null) {
                return [
                    'unit_price' => $branchPrice,
                    'price_source' => 'branch',
                    'promotion_id' => null,
                ];
            }
        }

        $base = $this->bridge->inventoryPriceBase($productId, $companyId);
        return [
            'unit_price' => (float) $base['price'],
            'price_source' => ($base['sell_price'] ?? null) !== null && (float) ($base['sell_price'] ?? 0) > 0
                ? 'default'
                : 'cost_fallback',
            'promotion_id' => null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    public function applyToLines(
        array $lines,
        int $companyId,
        int $branchId,
        ?array $customer = null
    ): array {
        $out = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $resolved = $this->resolveLine($line, $companyId, $branchId, $customer);
            $qty = max(0, (float) ($line['quantity'] ?? 0));
            $unit = (float) $resolved['unit_price'];
            $productId = (int) ($line['product_id'] ?? 0);
            $base = $productId > 0 ? $this->bridge->inventoryPriceBase($productId, $companyId) : ['price' => $unit];
            $listUnit = (float) ($base['price'] ?? $unit);
            $promoDisc = ($resolved['price_source'] === 'promotion' && $listUnit > $unit)
                ? round(($listUnit - $unit) * $qty, 2)
                : 0.0;
            $out[] = array_merge($line, [
                'list_unit_price' => $listUnit,
                'unit_price' => $unit,
                'price_source' => $resolved['price_source'],
                'promotion_id' => $resolved['promotion_id'],
                'promotion_discount' => $promoDisc,
                'line_total' => round($qty * $unit, 2),
            ]);
        }
        return $out;
    }
}
