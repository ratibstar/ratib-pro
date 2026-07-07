<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class ExportChunkJobHandler implements JobHandlerInterface
{
    public function supports(string $jobType): bool
    {
        return $jobType === 'export_chunk';
    }

    public function handle(Job $job): void
    {
        unset($job);
        // Placeholder for Phase 2.7 — export pipeline deferred.
    }
}
