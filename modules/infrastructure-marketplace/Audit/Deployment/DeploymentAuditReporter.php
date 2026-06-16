<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Audit\Deployment;

final class DeploymentAuditReporter
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


    /**
     * @param array<string, mixed> $prelaunchReport
     */
    public function record(string $releaseId, string $environment, array $prelaunchReport): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rateb_infra_deployment_audits
             (release_id, environment, prelaunch_status, prelaunch_score, matrix_json, snapshot_json, created_at)
             VALUES
             (:release_id, :environment, :prelaunch_status, :prelaunch_score, :matrix_json, :snapshot_json, NOW())'
        );
        $stmt->execute([
            'release_id' => substr($releaseId, 0, 128),
            'environment' => substr($environment, 0, 32),
            'prelaunch_status' => substr((string) ($prelaunchReport['status'] ?? 'WARN'), 0, 16),
            'prelaunch_score' => (int) ($prelaunchReport['score'] ?? 0),
            'matrix_json' => json_encode($prelaunchReport['matrix'] ?? [], JSON_UNESCAPED_SLASHES),
            'snapshot_json' => json_encode($prelaunchReport, JSON_UNESCAPED_SLASHES),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, release_id, environment, prelaunch_status, prelaunch_score, created_at
             FROM rateb_infra_deployment_audits
             ORDER BY id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}

