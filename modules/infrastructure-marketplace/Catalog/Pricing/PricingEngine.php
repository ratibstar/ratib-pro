<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Catalog\Pricing;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Reseller\AgencyResellerPolicy;

final class PricingEngine
{
    private AgencyResellerPolicy $resellerPolicy;

    public function __construct(AgencyResellerPolicy $resellerPolicy) {
        $this->resellerPolicy = $resellerPolicy;
    }


    /**
     * @param array<string, mixed> $sku
     * @return array<string, mixed>
     */
    public function resolve(array $sku, TenantContext $tenant, string $currency = 'USD'): array
    {
        $base = (float) ($sku['base_price'] ?? 0);
        $skuCode = (string) ($sku['sku'] ?? '');
        $markup = $this->resellerPolicy->appliesWhiteLabelMarkup($skuCode, $tenant) ?? 0.0;
        $final = $base + ($base * ($markup / 100.0));

        return [
            'currency' => strtoupper($currency),
            'base_price' => round($base, 4),
            'markup_percent' => round($markup, 2),
            'final_price' => round($final, 4),
            'billing_cycle' => (string) ($sku['billing_cycle'] ?? 'monthly'),
            'recurring' => (bool) ($sku['is_recurring'] ?? true),
        ];
    }
}

