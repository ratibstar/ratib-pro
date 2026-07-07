<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchAdapterInterface;

final class HealthJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly SearchAdapterInterface $searchAdapter
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'health_check';
    }

    public function handle(Job $job): void
    {
        unset($job);
        if (!$this->searchAdapter->healthCheck()) {
            throw new \RuntimeException('Search adapter health check failed');
        }
    }
}
