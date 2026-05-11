<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Ordering;

final class LifecycleTracker
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


    public function syncFromProvisioningJob(string $jobPublicId, string $state): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ratib_infra_orders
             SET status = :state, updated_at = NOW()
             WHERE provisioning_job_public_id = :job_public_id'
        );
        $stmt->execute([
            'state' => strtoupper($state),
            'job_public_id' => $jobPublicId,
        ]);
    }
}

