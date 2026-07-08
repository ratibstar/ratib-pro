<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IntegrationOutboxWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class CleanupJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly IdempotencyReadRepositoryInterface $idempotencyReadRepository,
        private readonly IntegrationOutboxWriteRepositoryInterface $outboxWriteRepository
    ) {
    }

    public function supports(string $jobType): bool
    {
        return in_array($jobType, ['cleanup_jobs', 'cleanup'], true);
    }

    public function handle(Job $job): void
    {
        unset($job);
        $this->idempotencyReadRepository->deleteExpired();
        $this->outboxWriteRepository->deleteExpiredDelivered(30);
    }
}
