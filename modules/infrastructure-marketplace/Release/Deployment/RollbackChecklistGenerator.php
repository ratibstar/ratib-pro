<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Release\Deployment;

final class RollbackChecklistGenerator
{
    /**
     * @return list<string>
     */
    public function generate(string $releaseId): array
    {
        return [
            'Set RATEB_INFRA_EXECUTION_KILL_SWITCH=1',
            'Pause worker supervisors for infrastructure worker only',
            'Capture queue snapshot via /api/infrastructure-marketplace/ops-queue.php',
            'Capture provider activation snapshot via /api/infrastructure-marketplace/providers.php',
            'Verify no core SaaS/payment routes are impacted',
            'Keep deployment audit and prelaunch reports archived for release ' . $releaseId,
            'Rollback module-scoped release artifacts only if required',
            'Re-run prelaunch-health after stabilization',
        ];
    }
}

