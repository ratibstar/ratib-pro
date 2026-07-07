<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ScheduledPublishWriteRepositoryInterface
{
    public function clearPublishAt(string $productUuid): void;

    public function clearArchiveAt(string $productUuid): void;
}
