<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface WorkflowCommentWriteRepositoryInterface
{
    public function add(
        string $entityType,
        int $entityId,
        string $entityUuid,
        string $workflowAction,
        ?string $fromStatus,
        ?string $toStatus,
        string $comment,
        ?int $commentedBy
    ): string;
}
