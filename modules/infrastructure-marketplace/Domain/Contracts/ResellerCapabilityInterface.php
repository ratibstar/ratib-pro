<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Domain\Contracts;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;

/**
 * Agency / reseller white-label hooks (pricing overlays, delegated ownership).
 */
interface ResellerCapabilityInterface
{
    public function resellerChainForTenant(TenantContext $tenant): array;

    public function appliesWhiteLabelMarkup(string $sku, TenantContext $tenant): ?float;
}
