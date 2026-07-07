<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface SearchIndexQueueReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listPending(int $limit = 100): array;
}
