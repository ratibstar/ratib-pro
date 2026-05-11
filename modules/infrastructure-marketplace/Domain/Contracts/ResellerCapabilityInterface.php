<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domain\Contracts;

use Ratib\InfrastructureMarketplace\Domain\TenantContext;

/**
 * Agency / reseller white-label hooks (pricing overlays, delegated ownership).
 */
interface ResellerCapabilityInterface
{
    public function resellerChainForTenant(TenantContext $tenant): array;

    public function appliesWhiteLabelMarkup(string $sku, TenantContext $tenant): ?float;
}
