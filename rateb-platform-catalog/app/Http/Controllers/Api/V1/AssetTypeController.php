<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\AssetTypeService;
use Rateb\PlatformCatalog\Application\Services\FileService;
use Rateb\PlatformCatalog\Application\Services\MediaService;
use Rateb\PlatformCatalog\Application\Services\VideoService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class AssetTypeController
{
    public function __construct(
        private readonly AssetTypeService $assetTypeService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            $result = $this->assetTypeService->list(null, $limit, $offset);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
        ApiEnvelope::error([['message' => $e->getMessage()]], $status);
    }
}
