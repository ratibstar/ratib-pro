<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\MediaJobReadRepositoryInterface;

final class MysqlMediaJobReadRepository extends BaseRepository implements MediaJobReadRepositoryInterface
{
    protected function table(): string
    {
        return 'media_jobs';
    }

    public function findByUuid(string $uuid): ?array
    {
        return $this->fetchOne(
            'SELECT mj.uuid, mj.product_image_id, mj.status, mj.attempts, mj.error_message,
                    mj.created_at, mj.updated_at, pi.uuid AS image_uuid, pi.storage_key
             FROM media_jobs mj
             LEFT JOIN product_images pi ON pi.id = mj.product_image_id
             WHERE mj.uuid = :uuid
             LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function listByStatus(string $status, int $limit): array
    {
        $limit = max(1, min(100, $limit));

        return $this->fetchAll(
            'SELECT uuid, product_image_id, status, attempts, error_message, created_at, updated_at
             FROM media_jobs
             WHERE status = :status
             ORDER BY id ASC
             LIMIT ' . $limit,
            ['status' => $status]
        );
    }
}
