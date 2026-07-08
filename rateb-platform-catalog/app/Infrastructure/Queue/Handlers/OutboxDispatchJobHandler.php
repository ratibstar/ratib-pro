<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Application\Services\IntegrationOutboxService;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class OutboxDispatchJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly IntegrationOutboxService $outboxService
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'outbox_dispatch';
    }

    public function handle(Job $job): void
    {
        $limit = max(1, min(200, (int) ($job->payload['limit'] ?? 50)));
        $this->outboxService->dispatchPending($limit);
    }
}
