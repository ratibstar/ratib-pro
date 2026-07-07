<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

interface QueueAdapterInterface
{
    public function push(Job $job): string;

    public function pushDelayed(Job $job, int $delaySeconds): string;

    public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job;

    public function acknowledge(string $jobId): void;

    public function fail(string $jobId, string $reason): void;

    public function retry(string $jobId): void;
}
