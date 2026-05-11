<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Hosting;

use Ratib\InfrastructureMarketplace\Domain\Contracts\HostingProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

final class PlaceholderHostingAdapter implements HostingProviderInterface
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

    public function getCapabilityMatrix(): array
    {
        return [
            'panels' => [],
            'provisioning_modes' => [],
        ];
    }
}
