<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class CleanupJobHandler implements JobHandlerInterface
{
    public function supports(string $jobType): bool
    {
        return $jobType === 'cleanup_jobs';
    }

    public function handle(Job $job): void
    {
        unset($job);
        // Placeholder — retention cleanup wired in later ops phase.
    }
}
