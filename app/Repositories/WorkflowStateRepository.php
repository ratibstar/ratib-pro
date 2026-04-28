<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\BaseModel;
use RuntimeException;
use JsonException;

final class WorkflowStateRepository extends BaseModel
{
    private const STALE_RUNNING_SECONDS = 300;

    /** @param array<string, mixed> $context */
    public function persist(
        int $workflowId,
        string $currentStep,
        string $status,
        array $context,
        ?string $errorMessage = null
    ): void
    {
        try {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid workflow state context JSON.', 0, $exception);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO workflow_states (workflow_id, current_step, status, context_json, error_message, updated_at)
             VALUES (:workflow_id, :current_step, :status, :context_json, :error_message, NOW())
             ON DUPLICATE KEY UPDATE
               current_step = VALUES(current_step),
               status = VALUES(status),
               context_json = VALUES(context_json),
               error_message = VALUES(error_message),
               updated_at = NOW()'
        );
        $stmt->execute([
            ':workflow_id' => $workflowId,
            ':current_step' => $currentStep,
            ':status' => $status,
            ':context_json' => $contextJson,
            ':error_message' => $errorMessage,
        ]);
    }

    public function latestByWorkflowId(int $workflowId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT workflow_id, current_step, status, context_json, error_message, updated_at
             FROM workflow_states WHERE workflow_id = :workflow_id LIMIT 1'
        );
        $stmt->execute([':workflow_id' => $workflowId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['context_json'] = json_decode((string) ($row['context_json'] ?? '{}'), true) ?? [];
        return $row;
    }

    public function isRunningStateStale(array $state): bool
    {
        if ((string) ($state['status'] ?? '') !== 'running') {
            return false;
        }
        $updatedAt = (string) ($state['updated_at'] ?? '');
        if ($updatedAt === '') {
            return true;
        }
        $updatedTs = strtotime($updatedAt);
        if ($updatedTs === false) {
            return true;
        }
        return (time() - $updatedTs) > self::STALE_RUNNING_SECONDS;
    }
}
