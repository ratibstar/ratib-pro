<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\SearchQueryService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;

final class SearchController
{
    public function __construct(
        private readonly SearchQueryService $searchQueryService
    ) {
    }

    /** @param array<string, string> $params */
    public function search(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->searchQueryService->searchProducts($_GET);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function searchVariants(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->searchQueryService->searchVariants($_GET);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function barcode(array $params): void
    {
        try {
            $result = $this->searchQueryService->resolveBarcode($params['barcode']);
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Barcode not found']], 404, $result['meta']);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
        $headers = $status === 503 ? ['Retry-After' => '60'] : [];
        ApiEnvelope::error([['message' => $e->getMessage()]], $status, [], $headers);
    }
}
