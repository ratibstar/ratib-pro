<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domain\Contracts;

use Ratib\InfrastructureMarketplace\Domain\TenantContext;

interface SslProviderInterface
{
    /**
     * Issue or begin ACME/order flow depending on backing system.
     *
     * @return array<string, mixed>
     */
    public function provisionCertificate(TenantContext $tenant, string $fqdn, array $options = []): array;

    public function revokeCertificate(TenantContext $tenant, string $externalReference): array;

    public function getCapabilityMatrix(): array;
}
