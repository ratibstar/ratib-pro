<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface WorkflowCommentReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array;
}
