<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

interface JobQueueWriteRepositoryInterface
{
    public function push(Job $job, ?\DateTimeImmutable $availableAt = null): string;

    public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job;

    public function heartbeat(string $jobId, string $workerId, int $visibilityTimeoutSeconds = 300): void;

    public function recoverStaleJobs(int $visibilityTimeoutSeconds = 300): int;

    public function acquireWorkerLock(string $workerId, string $queue, int $ttlSeconds = 60): bool;

    public function releaseWorkerLock(string $workerId, string $queue): void;

    public function touchWorkerLock(string $workerId, string $queue, int $ttlSeconds = 60): void;

    public function acknowledge(string $jobId): void;

    public function fail(string $jobId, string $reason): void;

    public function retry(string $jobId): void;

    public function replayDead(string $jobId): bool;

    public function cancelPending(string $jobId): bool;
}
