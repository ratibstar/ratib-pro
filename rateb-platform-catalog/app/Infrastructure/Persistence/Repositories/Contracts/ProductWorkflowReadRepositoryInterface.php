<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductWorkflowReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listHistory(string $productUuid, int $limit = 50): array;
}
