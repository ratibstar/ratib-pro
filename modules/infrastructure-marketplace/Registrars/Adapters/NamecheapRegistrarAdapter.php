<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Registrars\Adapters;

use Ratib\InfrastructureMarketplace\Domain\Contracts\RegistrarProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

final class NamecheapRegistrarAdapter implements RegistrarProviderInterface
{
    public function registerDomain(TenantContext $tenant, ProvisioningPayload $payload): array
    {
        unset($tenant, $payload);
        return ['provider' => 'namecheap', 'state' => 'skeleton_only'];
    }

    public function renewDomain(TenantContext $tenant, string $externalReference, int $years): array
    {
        unset($tenant, $externalReference, $years);
        return ['provider' => 'namecheap', 'state' => 'skeleton_only'];
    }

    public function getCapabilityMatrix(): array
    {
        return ['provider' => 'namecheap', 'ready' => false];
    }
}

