<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Hosting\Contracts;

use Ratib\InfrastructureMarketplace\Domain\Contracts\HostingProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;

interface HostingProvisioningInterface extends HostingProviderInterface
{
    /**
     * @return list<array{name:string,display_name:string}>
     */
    public function listPackages(TenantContext $tenant): array;

    /**
     * @return array<string, mixed>
     */
    public function unsuspendAccount(TenantContext $tenant, string $externalReference): array;

    /**
     * @return array<string, mixed>
     */
    public function terminateAccount(TenantContext $tenant, string $externalReference): array;

    /**
     * @return array<string, mixed>
     */
    public function usageMetrics(TenantContext $tenant, string $externalReference): array;
}

