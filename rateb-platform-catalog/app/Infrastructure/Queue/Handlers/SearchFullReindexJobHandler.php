<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Application\Services\SearchIndexerService;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class SearchFullReindexJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly SearchIndexerService $indexerService
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'search_full_reindex';
    }

    public function handle(Job $job): void
    {
        $locale = (string) ($job->payload['locale'] ?? 'en');
        $afterId = (int) ($job->payload['last_product_id'] ?? 0);
        $batchSize = (int) ($job->payload['batch_size'] ?? 500);

        $this->indexerService->reindexLocale($locale, $batchSize, $afterId);
        $this->indexerService->processSearchIndexQueue(500);
    }
}
