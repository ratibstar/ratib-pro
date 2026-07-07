<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Support\Uuid;

final class SchedulerService
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly ScheduledPublishService $scheduledPublishService
    ) {
    }

    public function run(): void
    {
        $this->scheduledPublishService->processDue();

        $this->queueService->enqueueSystem('maintenance', 'health_check', [
            'scheduled_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], 'health_check:' . gmdate('Y-m-d-H'));

        $this->queueService->enqueueSystem('maintenance', 'cleanup_jobs', [
            'scheduled_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], 'cleanup_jobs:' . gmdate('Y-m-d'));
    }
}
