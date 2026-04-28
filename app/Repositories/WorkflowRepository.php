<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\BaseModel;

final class WorkflowRepository extends BaseModel
{
    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
    /** @param array<string, mixed> $context */
    public function start(string $name, array $context): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workflows (name, context_json, status, created_at, updated_at)
             VALUES (:name, :context_json, :status, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => $name,
            ':context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => 'running',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $context */
    public function complete(int $workflowId, array $context): void
    {
        $stmt = $this->db->prepare(
            'UPDATE workflows SET context_json = :context_json, status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $workflowId,
            ':context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => 'completed',
        ]);
    }

    public function fail(int $workflowId, string $failedStep, string $error): void
    {
        $stmt = $this->db->prepare(
            'UPDATE workflows SET failed_step = :failed_step, context_json = :context_json, status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $workflowId,
            ':failed_step' => $failedStep,
            ':context_json' => json_encode(['error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => 'failed',
        ]);
    }

    public function storeIdempotencyKey(string $idempotencyKey, int $workflowId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO idempotency_keys (idempotency_key, workflow_id, created_at, updated_at)
             VALUES (:idempotency_key, :workflow_id, NOW(), NOW())
             ON DUPLICATE KEY UPDATE workflow_id = VALUES(workflow_id), updated_at = NOW()'
        );
        $stmt->execute([
            ':idempotency_key' => $idempotencyKey,
            ':workflow_id' => $workflowId,
        ]);
    }

    public function findWorkflowIdByIdempotencyKey(string $idempotencyKey): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT workflow_id FROM idempotency_keys WHERE idempotency_key = :idempotency_key LIMIT 1'
        );
        $stmt->execute([':idempotency_key' => $idempotencyKey]);
        $row = $stmt->fetch();
        return $row ? (int) $row['workflow_id'] : null;
    }

    public function findIdempotencyRowForUpdate(string $idempotencyKey): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, idempotency_key, workflow_id, locked_at, expires_at FROM idempotency_keys
             WHERE idempotency_key = :idempotency_key LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':idempotency_key' => $idempotencyKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function insertIdempotencyLock(string $idempotencyKey): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO idempotency_keys (idempotency_key, workflow_id, locked_at, expires_at, created_at)
             VALUES (:idempotency_key, NULL, NOW(), NULL, NOW())'
        );
        $stmt->execute([':idempotency_key' => $idempotencyKey]);
    }

    public function refreshIdempotencyLock(string $idempotencyKey): void
    {
        $stmt = $this->db->prepare(
            'UPDATE idempotency_keys SET locked_at = NOW(), expires_at = NULL WHERE idempotency_key = :idempotency_key'
        );
        $stmt->execute([':idempotency_key' => $idempotencyKey]);
    }

    public function attachIdempotencyWorkflow(string $idempotencyKey, int $workflowId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE idempotency_keys
             SET workflow_id = :workflow_id,
                 locked_at = NULL,
                 expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
             WHERE idempotency_key = :idempotency_key'
        );
        $stmt->execute([
            ':idempotency_key' => $idempotencyKey,
            ':workflow_id' => $workflowId,
        ]);
    }

    public function releaseIdempotencyWorkflow(string $idempotencyKey): void
    {
        $stmt = $this->db->prepare(
            'UPDATE idempotency_keys SET workflow_id = NULL, locked_at = NULL, expires_at = NULL WHERE idempotency_key = :idempotency_key'
        );
        $stmt->execute([':idempotency_key' => $idempotencyKey]);
    }

    public function findById(int $workflowId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, status, context_json FROM workflows WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $workflowId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['context_json'] = json_decode((string) ($row['context_json'] ?? '{}'), true) ?? [];
        return $row;
    }

    public function incrementMetricsTotals(): void
    {
        $stmt = $this->db->prepare(
            'UPDATE workflow_metrics
             SET total_started = total_started + 1,
                 total_workflows = total_workflows + 1,
                 updated_at = NOW()
             WHERE id = 1'
        );
        $stmt->execute();
    }

    public function incrementMetricsSuccess(int $durationMs): void
    {
        $stmt = $this->db->prepare(
            'UPDATE workflow_metrics
             SET success_count = success_count + 1,
                 total_completed = total_completed + 1,
                 total_execution_ms = total_execution_ms + :duration_ms,
                 avg_execution_time_ms = CASE
                    WHEN (total_completed + 1) = 0 THEN 0
                    ELSE (total_execution_ms + :duration_ms) / (total_completed + 1)
                 END,
                 updated_at = NOW()
             WHERE id = 1'
        );
        $stmt->execute([':duration_ms' => $durationMs]);
    }

    public function incrementMetricsFailure(int $durationMs): void
    {
        $stmt = $this->db->prepare(
            'UPDATE workflow_metrics
             SET failure_count = failure_count + 1,
                 updated_at = NOW()
             WHERE id = 1'
        );
        $stmt->execute();
    }
}
