<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Domain\Contracts;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

/**
 * Abstraction over panel/WHM-capable backends (implementations stay in Adapters/Hosting).
 */
interface HostingProviderInterface
{
    /**
     * @return array<string, mixed> Provider-specific identifiers (no secrets in return values).
     */
    public function createAccount(TenantContext $tenant, ProvisioningPayload $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function suspendAccount(TenantContext $tenant, string $externalReference): array;

    public function getCapabilityMatrix(): array;
}
