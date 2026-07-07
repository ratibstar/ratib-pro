<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishWriteRepositoryInterface;

final class MysqlScheduledPublishWriteRepository extends BaseRepository implements ScheduledPublishWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'products';
    }

    public function clearPublishAt(string $productUuid): void
    {
        $this->writePdo->prepare(
            'UPDATE products SET publish_at = NULL, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        )->execute(['uuid' => $productUuid]);
    }

    public function clearArchiveAt(string $productUuid): void
    {
        $this->writePdo->prepare(
            'UPDATE products SET archive_at = NULL, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        )->execute(['uuid' => $productUuid]);
    }
}
