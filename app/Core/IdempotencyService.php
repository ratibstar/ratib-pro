<?php
declare(strict_types=1);

namespace App\Core;

use App\Repositories\WorkflowRepository;
use RuntimeException;

final class IdempotencyService
{
    private const STALE_LOCK_SECONDS = 30;

    public function __construct(private readonly WorkflowRepository $workflowRepository)
    {
    }

    public function resolveWorkflowId(string $idempotencyKey): ?int
    {
        if ($idempotencyKey === '') {
            return null;
        }
        return $this->workflowRepository->findWorkflowIdByIdempotencyKey($idempotencyKey);
    }

    /**
     * Acquire key atomically.
     * Returns existing workflow_id when key already mapped.
     */
    /** @return array{status:string, workflow_id:int|null} */
    public function acquire(string $idempotencyKey): array
    {
        if ($idempotencyKey === '') {
            return ['status' => 'none', 'workflow_id' => null];
        }
        $this->workflowRepository->beginTransaction();
        try {
            $row = $this->workflowRepository->findIdempotencyRowForUpdate($idempotencyKey);
            if ($row !== null) {
                $workflowId = isset($row['workflow_id']) ? (int) $row['workflow_id'] : 0;
                if ($workflowId > 0) {
                    $this->workflowRepository->commit();
                    return ['status' => 'existing', 'workflow_id' => $workflowId];
                }

                $lockedAt = (string) ($row['locked_at'] ?? '');
                $isStale = $lockedAt === '' || (time() - strtotime($lockedAt)) > self::STALE_LOCK_SECONDS;
                if ($isStale) {
                    $this->workflowRepository->refreshIdempotencyLock($idempotencyKey);
                    $this->workflowRepository->commit();
                    return ['status' => 'acquired', 'workflow_id' => null];
                }

                $this->workflowRepository->commit();
                return ['status' => 'blocked', 'workflow_id' => null];
            }

            $this->workflowRepository->insertIdempotencyLock($idempotencyKey);
            $this->workflowRepository->commit();
            return ['status' => 'acquired', 'workflow_id' => null];
        } catch (\Throwable $exception) {
            $this->workflowRepository->rollback();
            throw new RuntimeException('Failed to acquire idempotency lock.', 0, $exception);
        }
    }

    public function remember(string $idempotencyKey, int $workflowId): void
    {
        if ($idempotencyKey === '') {
            return;
        }
        $this->workflowRepository->attachIdempotencyWorkflow($idempotencyKey, $workflowId);
    }

    public function releaseOnFailure(string $idempotencyKey): void
    {
        if ($idempotencyKey === '') {
            return;
        }
        $this->workflowRepository->releaseIdempotencyWorkflow($idempotencyKey);
    }
}
