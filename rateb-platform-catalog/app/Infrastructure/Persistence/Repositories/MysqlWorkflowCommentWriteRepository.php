<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentWriteRepositoryInterface;

final class MysqlWorkflowCommentWriteRepository extends BaseRepository implements WorkflowCommentWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'workflow_comments';
    }

    public function add(
        string $entityType,
        int $entityId,
        string $entityUuid,
        string $workflowAction,
        ?string $fromStatus,
        ?string $toStatus,
        string $comment,
        ?int $commentedBy
    ): string {
        $uuid = $this->newUuid();
        $this->writePdo->prepare(
            'INSERT INTO workflow_comments
             (uuid, entity_type, entity_id, entity_uuid, workflow_action, from_status, to_status, comment, commented_by)
             VALUES (:uuid, :entity_type, :entity_id, :entity_uuid, :workflow_action, :from_status, :to_status, :comment, :commented_by)'
        )->execute([
            'uuid' => $uuid,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_uuid' => $entityUuid,
            'workflow_action' => $workflowAction,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
            'commented_by' => $commentedBy,
        ]);

        return $uuid;
    }
}
