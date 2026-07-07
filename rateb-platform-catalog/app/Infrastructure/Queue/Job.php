<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

final class Job
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $queue,
        public readonly string $jobType,
        public readonly array $payload,
        public readonly ?string $idempotencyKey = null,
        public readonly int $maxAttempts = 5,
        public readonly int $attempts = 0
    ) {
    }
}
