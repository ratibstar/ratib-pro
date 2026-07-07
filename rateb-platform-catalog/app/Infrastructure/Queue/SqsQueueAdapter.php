<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

final class SqsQueueAdapter implements QueueAdapterInterface
{
    public function push(Job $job): string
    {
        unset($job);
        throw new \LogicException('SqsQueueAdapter is not implemented in Phase 2.7');
    }

    public function pushDelayed(Job $job, int $delaySeconds): string
    {
        unset($job, $delaySeconds);
        throw new \LogicException('SqsQueueAdapter is not implemented in Phase 2.7');
    }

    public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job
    {
        unset($queue, $workerId, $visibilityTimeoutSeconds);
        throw new \LogicException('SqsQueueAdapter is not implemented in Phase 2.7');
    }

    public function acknowledge(string $jobId): void
    {
        unset($jobId);
        throw new \LogicException('SqsQueueAdapter is not implemented in Phase 2.7');
    }

    public function fail(string $jobId, string $reason): void
    {
        unset($jobId, $reason);
        throw new \LogicException('SqsQueueAdapter is not implemented in Phase 2.7');
    }

    public function retry(string $jobId): void
    {
        unset($jobId);
        throw new \LogicException('SqsQueueAdapter is not implemented in Phase 2.7');
    }
}
