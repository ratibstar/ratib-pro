<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;

final class DatabaseQueueAdapter implements QueueAdapterInterface
{
    public function __construct(
        private readonly JobQueueWriteRepositoryInterface $repository
    ) {
    }

    public function push(Job $job): string
    {
        return $this->repository->push($job);
    }

    public function pushDelayed(Job $job, int $delaySeconds): string
    {
        $availableAt = (new \DateTimeImmutable())->modify('+' . $delaySeconds . ' seconds');

        return $this->repository->push($job, $availableAt);
    }

    public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job
    {
        return $this->repository->pop($queue, $workerId, $visibilityTimeoutSeconds);
    }

    public function acknowledge(string $jobId): void
    {
        $this->repository->acknowledge($jobId);
    }

    public function fail(string $jobId, string $reason): void
    {
        $this->repository->fail($jobId, $reason);
    }

    public function retry(string $jobId): void
    {
        $this->repository->retry($jobId);
    }
}
