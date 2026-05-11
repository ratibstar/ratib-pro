<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Registrars;

use Ratib\InfrastructureMarketplace\Domain\Contracts\RegistrarProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

final class PlaceholderRegistrarAdapter implements RegistrarProviderInterface
{
    public function registerDomain(TenantContext $tenant, ProvisioningPayload $payload): array
    {
        unset($tenant, $payload);

        return ['provider' => 'placeholder', 'state' => 'not_configured'];
    }

    public function renewDomain(TenantContext $tenant, string $externalReference, int $years): array
    {
        unset($tenant, $years);

        return ['provider' => 'placeholder', 'reference' => $externalReference, 'state' => 'not_configured'];
    }

    public function getCapabilityMatrix(): array
    {
        return ['tlds' => [], 'premium_support' => false];
    }
}
