<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Provisioning\Execution;

use RATEB\InfrastructureMarketplace\Observability\InfrastructureMetrics;

final class TimeoutEscalationService
{
    private \PDO $pdo;
    private InfrastructureMetrics $metrics;

    public function __construct(\PDO $pdo, InfrastructureMetrics $metrics) {
        $this->pdo = $pdo;
        $this->metrics = $metrics;
    }


    public function escalateStuckRunning(int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rateb_infra_provisioning_jobs
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

