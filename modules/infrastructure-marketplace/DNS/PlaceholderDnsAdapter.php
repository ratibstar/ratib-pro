<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\DNS;

use Ratib\InfrastructureMarketplace\Domain\Contracts\DnsProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;

final class PlaceholderDnsAdapter implements DnsProviderInterface
{
    public function applyRecords(TenantContext $tenant, string $zoneFqdn, array $records): array
    {
        unset($tenant, $zoneFqdn, $records);

        return ['provider' => 'placeholder', 'state' => 'not_configured'];
    }

    public function purgeZone(TenantContext $tenant, string $zoneFqdn): array
    {
        unset($tenant, $zoneFqdn);

        return ['provider' => 'placeholder', 'state' => 'not_configured'];
    }

    public function getCapabilityMatrix(): array
    {
        return ['record_types_supported' => []];
    }
}
