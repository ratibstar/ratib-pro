<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Application\Services\SearchIndexerService;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class VariantReindexJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly SearchIndexerService $indexerService
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'variant_reindex';
    }

    public function handle(Job $job): void
    {
        $locale = (string) ($job->payload['locale'] ?? 'en');
        $variantUuid = (string) ($job->payload['variant_uuid'] ?? '');
        if ($variantUuid === '') {
            throw new \InvalidArgumentException('variant_uuid is required');
        }

        $this->indexerService->indexVariant($variantUuid, $locale);
    }
}
