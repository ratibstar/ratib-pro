<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\SSL;

use RATEB\InfrastructureMarketplace\Domain\Contracts\SslProviderInterface;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;

final class PlaceholderSslAdapter implements SslProviderInterface
{
    public function provisionCertificate(TenantContext $tenant, string $fqdn, array $options = []): array
    {
        unset($tenant, $options);

        return ['provider' => 'placeholder', 'fqdn' => $fqdn, 'state' => 'not_configured'];
    }

    public function revokeCertificate(TenantContext $tenant, string $externalReference): array
    {
        unset($tenant);

        return ['provider' => 'placeholder', 'reference' => $externalReference, 'state' => 'not_configured'];
    }

    public function getCapabilityMatrix(): array
    {
        return ['challenge_types' => [], 'wildcard' => false];
    }
}
