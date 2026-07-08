<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Tests\Support;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

class EmptyJobQueueWriteRepository implements JobQueueWriteRepositoryInterface
{
    public function push(Job $job, ?\DateTimeImmutable $availableAt = null): string
    {
        return $job->jobId;
    }

    public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job
    {
        return null;
    }

    public function heartbeat(string $jobId, string $workerId, int $visibilityTimeoutSeconds = 300): void
    {
    }

    public function recoverStaleJobs(int $visibilityTimeoutSeconds = 300): int
    {
        return 0;
    }

    public function acquireWorkerLock(string $workerId, string $queue, int $ttlSeconds = 60): bool
    {
        return true;
    }

    public function releaseWorkerLock(string $workerId, string $queue): void
    {
    }

    public function touchWorkerLock(string $workerId, string $queue, int $ttlSeconds = 60): void
    {
    }

    public function acknowledge(string $jobId): void
    {
    }

    public function fail(string $jobId, string $reason): void
    {
    }

    public function retry(string $jobId): void
    {
    }

    public function replayDead(string $jobId): bool
    {
        return false;
    }

    public function cancelPending(string $jobId): bool
    {
        return false;
    }
}
