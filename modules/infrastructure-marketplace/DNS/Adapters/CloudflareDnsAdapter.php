<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\DNS\Adapters;

use Ratib\InfrastructureMarketplace\Domain\Contracts\DnsProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;

final class CloudflareDnsAdapter implements DnsProviderInterface
{
    public function applyRecords(TenantContext $tenant, string $zoneFqdn, array $records): array
    {
        unset($tenant, $zoneFqdn, $records);
        return ['provider' => 'cloudflare_dns', 'state' => 'skeleton_only'];
    }

    public function purgeZone(TenantContext $tenant, string $zoneFqdn): array
    {
        unset($tenant, $zoneFqdn);
        return ['provider' => 'cloudflare_dns', 'state' => 'skeleton_only'];
    }

    public function getCapabilityMatrix(): array
    {
        return ['provider' => 'cloudflare_dns', 'ready' => false];
    }
}

