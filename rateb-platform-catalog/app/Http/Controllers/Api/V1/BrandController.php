<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\BrandService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;

final class BrandController
{
    public function __construct(
        private readonly BrandService $brandService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            $result = $this->brandService->list($limit, $offset);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $result = $this->brandService->getByUuid($params['uuid']);
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Brand not found']], 404, $result['meta']);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }
}
