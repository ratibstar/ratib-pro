<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowReadRepositoryInterface;

final class MysqlProductWorkflowReadRepository extends BaseRepository implements ProductWorkflowReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_workflow_history';
    }

    public function listHistory(string $productUuid, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->fetchAll(
            'SELECT uuid, from_status, to_status, action, actor_id, comment, entity_version, created_at
             FROM product_workflow_history
             WHERE product_uuid = :uuid
             ORDER BY id DESC
             LIMIT ' . $limit,
            ['uuid' => $productUuid]
        );
    }
}
