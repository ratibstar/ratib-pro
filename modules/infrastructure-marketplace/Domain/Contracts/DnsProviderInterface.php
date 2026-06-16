<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Domain\Contracts;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;

interface DnsProviderInterface
{
    /**
     * @param list<array{name:string,type:string,target:string,ttl?:int}> $records
     *
     * @return array<string, mixed>
     */
    public function applyRecords(TenantContext $tenant, string $zoneFqdn, array $records): array;

    public function purgeZone(TenantContext $tenant, string $zoneFqdn): array;

    public function getCapabilityMatrix(): array;
}
