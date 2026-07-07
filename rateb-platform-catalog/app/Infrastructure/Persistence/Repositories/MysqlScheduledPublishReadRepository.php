<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishReadRepositoryInterface;

final class MysqlScheduledPublishReadRepository extends BaseRepository implements ScheduledPublishReadRepositoryInterface
{
    protected function table(): string
    {
        return 'products';
    }

    public function listDuePublish(): array
    {
        return $this->fetchAll(
            "SELECT uuid, lock_version, publish_at
             FROM products
             WHERE status = 'approved'
               AND publish_at IS NOT NULL
               AND publish_at <= CURRENT_TIMESTAMP(6)
               AND deleted_at IS NULL
             ORDER BY publish_at ASC
             LIMIT 100"
        );
    }

    public function listDueArchive(): array
    {
        return $this->fetchAll(
            "SELECT uuid, lock_version, archive_at
             FROM products
             WHERE status = 'published'
               AND archive_at IS NOT NULL
               AND archive_at <= CURRENT_TIMESTAMP(6)
               AND deleted_at IS NULL
             ORDER BY archive_at ASC
             LIMIT 100"
        );
    }
}
