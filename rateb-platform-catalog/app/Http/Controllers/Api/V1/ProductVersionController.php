<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\ProductVersionConflictException;
use Rateb\PlatformCatalog\Application\Services\ProductVersionService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class ProductVersionController
{
    public function __construct(
        private readonly ProductVersionService $versionService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params): void
    {
        try {
            $limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;
            $items = $this->versionService->list($params['uuid'], $limit);
            ApiEnvelope::success($items);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $version = (int) $params['version'];
            $item = $this->versionService->get($params['uuid'], $version);
            ApiEnvelope::success($item);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function compare(array $params): void
    {
        try {
            $from = isset($_GET['from']) ? (int) $_GET['from'] : 0;
            $to = isset($_GET['to']) ? (int) $_GET['to'] : 0;
            if ($from <= 0 || $to <= 0) {
                ApiEnvelope::error([['message' => 'from and to query parameters are required']], 422);

                return;
            }
            $result = $this->versionService->compare($params['uuid'], $from, $to);
            ApiEnvelope::success($result);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function restore(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $version = (int) $params['version'];
            $result = $this->versionService->restore($params['uuid'], $version, $payload);
            ApiEnvelope::success($result);
        } catch (ProductVersionConflictException $e) {
            ApiEnvelope::error(
                [['error' => 'version_conflict', 'current_lock_version' => $e->currentLockVersion]],
                409
            );
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) $e->getCode();
        if ($status < 400 || $status > 599) {
            $status = 400;
        }
        ApiEnvelope::error([['message' => $e->getMessage()]], $status);
    }
}
