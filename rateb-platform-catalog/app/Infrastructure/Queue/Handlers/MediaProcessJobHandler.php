<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class MediaProcessJobHandler implements JobHandlerInterface
{
    public function supports(string $jobType): bool
    {
        return $jobType === 'image_process';
    }

    public function handle(Job $job): void
    {
        unset($job);
        // Placeholder for Phase 2.7 — async media pipeline deferred.
    }
}
