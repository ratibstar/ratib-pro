<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domain\Contracts;

use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

interface RegistrarProviderInterface
{
    /**
     * @return array<string, mixed> opaque handles safe for persistence (FQDN + registry object id, etc.).
     */
    public function registerDomain(TenantContext $tenant, ProvisioningPayload $payload): array;

    public function renewDomain(TenantContext $tenant, string $externalReference, int $years): array;

    public function getCapabilityMatrix(): array;
}
