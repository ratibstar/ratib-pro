<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Provisioning\Persistence;

final class ProvisioningJobLogRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


    /**
     * @param array<string, mixed> $context
     */
    public function append(int $jobId, string $level, string $message, array $context = []): void
    {
        $sql = 'INSERT INTO rateb_infra_job_logs (job_id, level, message, context_json, created_at)
                VALUES (:job_id, :level, :message, :context_json, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'level' => substr($level, 0, 16),
            'message' => substr($message, 0, 1000),
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byJobPublicId(string $publicId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.level, l.message, l.context_json, l.created_at
             FROM rateb_infra_job_logs l
             INNER JOIN rateb_infra_provisioning_jobs j ON j.id = l.job_id
             WHERE j.public_id = :public_id
             ORDER BY l.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':public_id', $publicId);
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}

