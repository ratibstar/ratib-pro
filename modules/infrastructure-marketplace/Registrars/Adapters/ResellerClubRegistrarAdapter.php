<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Registrars\Adapters;

use RATEB\InfrastructureMarketplace\Domain\Contracts\RegistrarProviderInterface;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

final class ResellerClubRegistrarAdapter implements RegistrarProviderInterface
{
    public function registerDomain(TenantContext $tenant, ProvisioningPayload $payload): array
    {
        unset($tenant, $payload);
        return ['provider' => 'resellerclub', 'state' => 'skeleton_only'];
    }

    public function renewDomain(TenantContext $tenant, string $externalReference, int $years): array
    {
        unset($tenant, $externalReference, $years);
        return ['provider' => 'resellerclub', 'state' => 'skeleton_only'];
    }

    public function getCapabilityMatrix(): array
    {
        return ['provider' => 'resellerclub', 'ready' => false];
    }
}

