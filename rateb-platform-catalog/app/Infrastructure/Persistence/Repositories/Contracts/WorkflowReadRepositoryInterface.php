<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface WorkflowReadRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findTransition(string $fromStatus, string $action): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listStates(): array;
}
