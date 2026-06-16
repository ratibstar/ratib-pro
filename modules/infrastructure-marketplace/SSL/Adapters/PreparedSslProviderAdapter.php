<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\SSL\Adapters;

use RATEB\InfrastructureMarketplace\Domain\Contracts\SslProviderInterface;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;

final class PreparedSslProviderAdapter implements SslProviderInterface
{
    public function provisionCertificate(TenantContext $tenant, string $fqdn, array $options = []): array
    {
        unset($tenant, $fqdn, $options);
        return ['provider' => 'prepared_ssl', 'state' => 'skeleton_only'];
    }

    public function revokeCertificate(TenantContext $tenant, string $externalReference): array
    {
        unset($tenant, $externalReference);
        return ['provider' => 'prepared_ssl', 'state' => 'skeleton_only'];
    }

    public function getCapabilityMatrix(): array
    {
        return ['provider' => 'prepared_ssl', 'ready' => false];
    }
}

