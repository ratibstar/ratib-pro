<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\Execution;

use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;

final class TimeoutEscalationService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly InfrastructureMetrics $metrics
    ) {}

    public function escalateStuckRunning(int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ratib_infra_provisioning_jobs
             SET status = 'RECONCILING', reconcile_required = 1, updated_at = NOW()
             WHERE status = 'RUNNING'
               AND updated_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)"
        );
        $stmt->execute(['minutes' => $minutes]);
        $count = $stmt->rowCount();
        if ($count > 0) {
            $this->metrics->lifecycleEvent('timeout_escalation', 'RECONCILING');
        }
        return $count;
    }
}

