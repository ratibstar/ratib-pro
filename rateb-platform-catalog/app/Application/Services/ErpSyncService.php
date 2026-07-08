<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\ErpSyncPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ErpSyncReadRepositoryInterface;

final class ErpSyncService
{
    public function __construct(
        private readonly ErpSyncReadRepositoryInterface $readRepository,
        private readonly ErpSyncPolicy $policy
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function syncStatus(int $companyId, ?string $sinceToken = null, int $limit = 100): array
    {
        $this->policy->view();
        $result = $this->readRepository->syncStatusForCompany($companyId, $sinceToken, $limit);

        return [
            'items' => $result['items'],
            'meta' => [
                'company_id' => $companyId,
                'since' => $result['since'],
                'count' => count($result['items']),
                'limit' => $limit,
            ],
        ];
    }
}
