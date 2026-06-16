<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Provisioning\Persistence;

use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use RATEB\InfrastructureMarketplace\Provisioning\Lifecycle\ProvisioningState;

final class ProvisioningJobRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


    public function insertQueued(ProvisioningJob $job, string $publicId, int $maxAttempts): void
    {
        $sql = 'INSERT INTO rateb_infra_provisioning_jobs (
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
            'status' => ProvisioningState::QUEUED,
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
            $sql = "SELECT * FROM rateb_infra_provisioning_jobs
                    WHERE status IN (' . $this->pdo->quote(ProvisioningState::QUEUED) . ',' . $this->pdo->quote(ProvisioningState::RETRYING) . ') AND available_at <= NOW()
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
            $update = $this->pdo->prepare('UPDATE rateb_infra_provisioning_jobs SET status = :status, locked_at = NOW(), updated_at = NOW() WHERE id = :id');
            $update->execute(['status' => ProvisioningState::RUNNING, 'id' => $job['id']]);
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
            "UPDATE rateb_infra_provisioning_jobs
             SET status = :status, locked_at = NULL, processed_at = NOW(), updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id, 'status' => ProvisioningState::COMPLETED]);
    }

    public function markRetryOrDead(int $id, int $attempts, int $maxAttempts, string $lastError, string $deadState): void
    {
        if ($attempts >= $maxAttempts) {
            $stmt = $this->pdo->prepare(
                'UPDATE rateb_infra_provisioning_jobs
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

        $base = min(1800, max(5, (int) pow(2, min(10, $attempts)) * 5));
        $jitter = random_int(0, max(1, (int) floor($base * 0.2)));
        $delay = $base + $jitter;
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_infra_provisioning_jobs
             SET status = :status, attempts = :attempts, locked_at = NULL, last_error = :last_error,
                 available_at = DATE_ADD(NOW(), INTERVAL :delay SECOND), updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => ProvisioningState::RETRYING,
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
            ProvisioningState::PENDING => 0,
            ProvisioningState::QUEUED => 0,
            ProvisioningState::RUNNING => 0,
            ProvisioningState::RETRYING => 0,
            ProvisioningState::WAITING_EXTERNAL => 0,
            ProvisioningState::COMPLETED => 0,
            ProvisioningState::FAILED => 0,
            ProvisioningState::DEAD_LETTER => 0,
            ProvisioningState::RECONCILING => 0,
            ProvisioningState::CANCELLED => 0,
        ];
        $rows = $this->pdo->query('SELECT status, COUNT(*) c FROM rateb_infra_provisioning_jobs GROUP BY status');
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

    public function queueDepth(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) AS c FROM rateb_infra_provisioning_jobs
             WHERE status IN (' . $this->pdo->quote(ProvisioningState::QUEUED) . ',' . $this->pdo->quote(ProvisioningState::RETRYING) . ',' . $this->pdo->quote(ProvisioningState::RUNNING) . ')'
        );
        if (!$stmt instanceof \PDOStatement) {
            return 0;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? (int) ($row['c'] ?? 0) : 0;
    }

    public function transitionState(int $jobId, string $fromState, string $toState): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_infra_provisioning_jobs
             SET status = :to_state, updated_at = NOW()
             WHERE id = :id AND status = :from_state'
        );
        $stmt->execute([
            'id' => $jobId,
            'from_state' => strtoupper($fromState),
            'to_state' => strtoupper($toState),
        ]);
        return $stmt->rowCount() > 0;
    }

    public function recoverExpiredLocks(int $lockTtlSeconds): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_infra_provisioning_jobs
             SET status = :retry_state, locked_at = NULL, available_at = NOW(), updated_at = NOW()
             WHERE status = :running_state AND locked_at IS NOT NULL
               AND locked_at < DATE_SUB(NOW(), INTERVAL :ttl SECOND)'
        );
        $stmt->execute([
            'retry_state' => ProvisioningState::RETRYING,
            'running_state' => ProvisioningState::RUNNING,
            'ttl' => $lockTtlSeconds,
        ]);
        return $stmt->rowCount();
    }

    public function requeueDeadLetter(string $publicId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_infra_provisioning_jobs
             SET status = :queued, attempts = 0, locked_at = NULL, available_at = NOW(), reconcile_required = 0, updated_at = NOW()
             WHERE public_id = :public_id AND status = :dead'
        );
        $stmt->execute([
            'queued' => ProvisioningState::QUEUED,
            'dead' => ProvisioningState::DEAD_LETTER,
            'public_id' => $publicId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function replayFromAnyState(string $publicId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_infra_provisioning_jobs
             SET status = :queued, attempts = 0, locked_at = NULL, available_at = NOW(), reconcile_required = 0, updated_at = NOW()
             WHERE public_id = :public_id'
        );
        $stmt->execute([
            'queued' => ProvisioningState::QUEUED,
            'public_id' => $publicId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentJobs(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, public_id, tenant_id, agency_id, status, attempts, max_attempts, reconcile_required, updated_at
             FROM rateb_infra_provisioning_jobs
             ORDER BY id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
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

