<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Domain\Contracts;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

interface RegistrarProviderInterface
{
    /**
     * @return array<string, mixed> opaque handles safe for persistence (FQDN + registry object id, etc.).
     */
    public function registerDomain(TenantContext $tenant, ProvisioningPayload $payload): array;

    public function renewDomain(TenantContext $tenant, string $externalReference, int $years): array;

    public function getCapabilityMatrix(): array;
}
