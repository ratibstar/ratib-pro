<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Application\Services\SearchIndexerService;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class SearchReindexJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly SearchIndexerService $indexerService
    ) {
    }

    public function supports(string $jobType): bool
    {
        return in_array($jobType, ['search_reindex', 'search_locale_reindex'], true);
    }

    public function handle(Job $job): void
    {
        $locale = (string) ($job->payload['locale'] ?? 'en');
        $productUuid = (string) ($job->payload['product_uuid'] ?? '');

        if ($job->jobType === 'search_locale_reindex' && $productUuid === '') {
            $batchSize = (int) ($job->payload['batch_size'] ?? 500);
            $afterId = (int) ($job->payload['last_product_id'] ?? 0);
            $this->indexerService->reindexLocale($locale, $batchSize, $afterId);
            $this->indexerService->processSearchIndexQueue(500);

            return;
        }

        if ($productUuid === '') {
            throw new \InvalidArgumentException('product_uuid is required');
        }

        $this->indexerService->indexProduct($productUuid, $locale);
    }
}
