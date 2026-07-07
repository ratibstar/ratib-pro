<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface;

final class MysqlWorkflowCommentReadRepository extends BaseRepository implements WorkflowCommentReadRepositoryInterface
{
    protected function table(): string
    {
        return 'workflow_comments';
    }

    public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));

        return $this->fetchAll(
            'SELECT uuid, workflow_action, from_status, to_status, comment, commented_by, created_at
             FROM workflow_comments
             WHERE entity_type = :entity_type AND entity_uuid = :entity_uuid AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT ' . $limit,
            ['entity_type' => $entityType, 'entity_uuid' => $entityUuid]
        );
    }
}
