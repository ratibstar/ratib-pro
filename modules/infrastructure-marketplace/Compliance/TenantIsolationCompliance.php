<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Compliance;

use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;

final class TenantIsolationCompliance
{
    private InfrastructureAuditLogger $audit;

    public function __construct(InfrastructureAuditLogger $audit) {
        $this->audit = $audit;
    }


    public function assertTenantOperation(TenantContext $tenant, string $operation): void
    {
        if ($tenant->tenantId() === null && $tenant->agencyId() === null) {
            throw new \RuntimeException('Tenant context missing for operation: ' . $operation);
        }
    }

    public function logAccess(TenantContext $tenant, string $actor, string $action): void
    {
        $this->audit->appendImmutable('tenant_isolation_access', [
            'actor' => $actor,
            'tenant_id' => $tenant->tenantId(),
            'agency_id' => $tenant->agencyId(),
            'action' => $action,
        ]);
    }
}

