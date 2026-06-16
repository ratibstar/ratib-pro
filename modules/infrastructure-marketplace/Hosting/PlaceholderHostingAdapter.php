<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Hosting;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Hosting\Contracts\HostingProvisioningInterface;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

final class PlaceholderHostingAdapter implements HostingProvisioningInterface
{
    public function createAccount(TenantContext $tenant, ProvisioningPayload $payload): array
    {
        unset($tenant, $payload);

        return ['provider' => 'placeholder', 'state' => 'not_configured'];
    }

    public function suspendAccount(TenantContext $tenant, string $externalReference): array
    {
        unset($tenant);

        return ['provider' => 'placeholder', 'reference' => $externalReference, 'state' => 'not_configured'];
    }

    public function listPackages(TenantContext $tenant): array
    {
        unset($tenant);
        return [];
    }

    public function unsuspendAccount(TenantContext $tenant, string $externalReference): array
    {
        unset($tenant);
        return ['provider' => 'placeholder', 'reference' => $externalReference, 'state' => 'not_configured'];
    }

    public function terminateAccount(TenantContext $tenant, string $externalReference): array
    {
        unset($tenant);
        return ['provider' => 'placeholder', 'reference' => $externalReference, 'state' => 'not_configured'];
    }

    public function usageMetrics(TenantContext $tenant, string $externalReference): array
    {
        unset($tenant);
        return ['account' => $externalReference, 'bandwidth_mb' => 0.0, 'quota_mb' => 0.0, 'disk_used_mb' => 0.0];
    }

    public function getCapabilityMatrix(): array
    {
        return [
            'panels' => [],
            'provisioning_modes' => [],
        ];
    }
}
