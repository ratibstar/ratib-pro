<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface JobQueueReadRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByJobId(string $jobId): ?array;

    /**
     * @return array<string, int>
     */
    public function countByQueueAndStatus(): array;
}
