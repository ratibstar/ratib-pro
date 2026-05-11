<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Reseller;

use Ratib\InfrastructureMarketplace\Domain\Contracts\ResellerCapabilityInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;

final class AgencyResellerPolicy implements ResellerCapabilityInterface
{
    /**
     * @param array<string, mixed> $tenantRules
     */
    public function __construct(
        private readonly array $tenantRules = []
    ) {}

    public function resellerChainForTenant(TenantContext $tenant): array
    {
        $tenantId = (string) ($tenant->tenantId() ?? 'global');
        $chain = $this->tenantRules[$tenantId]['reseller_chain'] ?? [];
        return is_array($chain) ? $chain : [];
    }

    public function appliesWhiteLabelMarkup(string $sku, TenantContext $tenant): ?float
    {
        $tenantId = (string) ($tenant->tenantId() ?? 'global');
        $markups = $this->tenantRules[$tenantId]['sku_markups'] ?? [];
        if (!is_array($markups) || !array_key_exists($sku, $markups)) {
            return null;
        }
        return (float) $markups[$sku];
    }

    /**
     * @return array<string, mixed>
     */
    public function quotaEnvelope(TenantContext $tenant): array
    {
        $tenantId = (string) ($tenant->tenantId() ?? 'global');
        $q = $this->tenantRules[$tenantId]['infra_quotas'] ?? [];
        return is_array($q) ? $q : [];
    }
}

