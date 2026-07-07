<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ScheduledPublishReadRepositoryInterface
{
    /**
     * @return list<array{uuid: string, lock_version: int, publish_at: string}>
     */
    public function listDuePublish(): array;

    /**
     * @return list<array{uuid: string, lock_version: int, archive_at: string}>
     */
    public function listDueArchive(): array;
}
