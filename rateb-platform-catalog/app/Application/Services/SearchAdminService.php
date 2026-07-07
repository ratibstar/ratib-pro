<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\SearchPolicy;

final class SearchAdminService
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly SearchPolicy $policy
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{job_id: string}
     */
    public function requestReindex(array $payload): array
    {
        $this->policy->manage();
        $locale = (string) ($payload['locale'] ?? 'en');
        $scope = (string) ($payload['scope'] ?? 'locale');
        $jobType = $scope === 'full' ? 'search_full_reindex' : 'search_locale_reindex';

        $jobId = $this->queueService->enqueue('search', $jobType, [
            'locale' => $locale,
            'last_product_id' => (int) ($payload['last_product_id'] ?? 0),
            'batch_size' => (int) ($payload['batch_size'] ?? 500),
            'product_uuid' => $payload['product_uuid'] ?? null,
        ], $jobType . ':' . $locale . ':' . ($payload['product_uuid'] ?? 'all'));

        return ['job_id' => $jobId];
    }
}
