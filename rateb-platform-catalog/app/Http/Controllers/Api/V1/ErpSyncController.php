<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\ErpSyncService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;

final class ErpSyncController
{
    public function __construct(
        private readonly ErpSyncService $erpSyncService
    ) {
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $companyId = (int) $params['company_id'];
            $since = isset($_GET['since']) ? (string) $_GET['since'] : null;
            $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;
            $result = $this->erpSyncService->syncStatus($companyId, $since, $limit);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }
}
