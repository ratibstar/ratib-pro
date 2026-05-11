<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\Persistence;

final class ProvisioningJobLogRepository
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function append(int $jobId, string $level, string $message, array $context = []): void
    {
        $sql = 'INSERT INTO ratib_infra_job_logs (job_id, level, message, context_json, created_at)
                VALUES (:job_id, :level, :message, :context_json, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'level' => substr($level, 0, 16),
            'message' => substr($message, 0, 1000),
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES),
        ]);
    }
}

