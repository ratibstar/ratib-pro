<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\Persistence;

use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningJob;

final class ProvisioningJobRepository
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    public function insertQueued(ProvisioningJob $job, string $publicId, int $maxAttempts): void
    {
        $sql = 'INSERT INTO ratib_infra_provisioning_jobs (
            public_id, tenant_id, agency_id, correlation_id, status, attempts, max_attempts,
            reconcile_required, available_at, steps_json, payload_snapshot_json, created_at, updated_at
        ) VALUES (
            :public_id, :tenant_id, :agency_id, :correlation_id, :status, 0, :max_attempts,
            0, NOW(), :steps_json, :payload_snapshot_json, NOW(), NOW()
        )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'public_id' => $publicId,
            'tenant_id' => $job->tenant()->tenantId(),
            'agency_id' => $job->tenant()->agencyId(),
            'correlation_id' => $job->correlationId(),
            'status' => 'queued',
            'max_attempts' => $maxAttempts,
            'steps_json' => json_encode($job->steps(), JSON_UNESCAPED_SLASHES),
            'payload_snapshot_json' => $this->encodePayloads($job),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockNextAvailable(): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $sql = "SELECT * FROM ratib_infra_provisioning_jobs
                    WHERE status IN ('queued','retry_scheduled') AND available_at <= NOW()
                    ORDER BY id ASC
                    LIMIT 1
                    FOR UPDATE";
            $row = $this->pdo->query($sql);
            if (!$row instanceof \PDOStatement) {
                $this->pdo->commit();
                return null;
            }
            $job = $row->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($job)) {
                $this->pdo->commit();
                return null;
            }
            $update = $this->pdo->prepare('UPDATE ratib_infra_provisioning_jobs SET status = :status, locked_at = NOW(), updated_at = NOW() WHERE id = :id');
            $update->execute(['status' => 'processing', 'id' => $job['id']]);
            $this->pdo->commit();
            return $job;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function markSuccess(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ratib_infra_provisioning_jobs
             SET status = 'completed', locked_at = NULL, processed_at = NOW(), updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    public function markRetryOrDead(int $id, int $attempts, int $maxAttempts, string $lastError, string $deadState): void
    {
        if ($attempts >= $maxAttempts) {
            $stmt = $this->pdo->prepare(
                'UPDATE ratib_infra_provisioning_jobs
                 SET status = :dead_state, locked_at = NULL, last_error = :last_error, reconcile_required = 1, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'dead_state' => $deadState,
                'last_error' => substr($lastError, 0, 1000),
                'id' => $id,
            ]);
            return;
        }

        $delay = min(300, max(5, $attempts * 10));
        $stmt = $this->pdo->prepare(
            'UPDATE ratib_infra_provisioning_jobs
             SET status = :status, attempts = :attempts, locked_at = NULL, last_error = :last_error,
                 available_at = DATE_ADD(NOW(), INTERVAL :delay SECOND), updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'retry_scheduled',
            'attempts' => $attempts,
            'last_error' => substr($lastError, 0, 1000),
            'delay' => $delay,
            'id' => $id,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $out = [
            'queued' => 0,
            'processing' => 0,
            'retry_scheduled' => 0,
            'completed' => 0,
            'dead_letter' => 0,
        ];
        $rows = $this->pdo->query('SELECT status, COUNT(*) c FROM ratib_infra_provisioning_jobs GROUP BY status');
        if (!$rows instanceof \PDOStatement) {
            return $out;
        }
        while ($r = $rows->fetch(\PDO::FETCH_ASSOC)) {
            if (!is_array($r)) {
                continue;
            }
            $key = (string) ($r['status'] ?? '');
            $out[$key] = (int) ($r['c'] ?? 0);
        }
        return $out;
    }

    private function encodePayloads(ProvisioningJob $job): string
    {
        $mapped = [];
        foreach ($job->payloadByStep() as $step => $payload) {
            $mapped[(string) $step] = [
                'operation' => $payload->operation(),
                'attributes' => $payload->attributes(),
            ];
        }
        return (string) json_encode($mapped, JSON_UNESCAPED_SLASHES);
    }
}

