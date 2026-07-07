<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface SearchIndexQueueWriteRepositoryInterface
{
    public function enqueue(string $entityType, string $entityUuid, string $locale, string $action = 'upsert'): string;

    public function markCompleted(string $uuid): void;

    public function markFailed(string $uuid, string $error): void;
}
