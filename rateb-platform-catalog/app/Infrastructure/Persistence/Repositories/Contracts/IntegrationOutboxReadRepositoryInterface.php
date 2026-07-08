<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface IntegrationOutboxReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchPending(int $limit): array;
}
