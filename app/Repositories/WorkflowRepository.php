<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\BaseModel;

final class WorkflowRepository extends BaseModel
{
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
}
