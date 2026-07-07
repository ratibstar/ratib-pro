<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueReadRepositoryInterface;

final class MysqlSearchIndexQueueReadRepository extends BaseRepository implements SearchIndexQueueReadRepositoryInterface
{
    protected function table(): string
    {
        return 'search_index_queue';
    }

    public function listPending(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return $this->fetchAll(
            'SELECT uuid, entity_type, entity_uuid, locale, action, attempts
             FROM search_index_queue
             WHERE status = "pending"
             ORDER BY created_at ASC
             LIMIT ' . $limit
        );
    }
}
