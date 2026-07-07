<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\ConcurrencyService;
use Rateb\PlatformCatalog\Application\Services\ProductService;
use Rateb\PlatformCatalog\Application\Services\ProductVersionConflictException;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class ProductController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ConcurrencyService $concurrencyService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            $result = $this->productService->list($this->productService->buildListFilterFromQuery(), $limit, $offset);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $result = $this->productService->getByUuid($params['uuid']);
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Product not found']], 404, $result['meta']);

                return;
            }

            $headers = [];
            if ($result['lock_version'] !== null) {
                $headers['ETag'] = $this->concurrencyService->formatEtag((int) $result['lock_version']);
            }

            ApiEnvelope::success($result['item'], $result['meta'], [], 200, $headers);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        unset($params);
        try {
            $payload = Request::jsonBody();
            $result = $this->productService->create($payload);
            ApiEnvelope::success($result['item'], $result['meta'], [], 201);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function update(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $result = $this->productService->update($params['uuid'], $payload);
            $headers = ['ETag' => $this->concurrencyService->formatEtag((int) $result['item']['lock_version'])];
            ApiEnvelope::success($result['item'], $result['meta'], [], 200, $headers);
        } catch (ProductVersionConflictException $e) {
            ApiEnvelope::error(
                [['error' => 'version_conflict', 'current_lock_version' => $e->currentLockVersion]],
                409
            );
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function destroy(array $params): void
    {
        try {
            $deleted = $this->productService->delete($params['uuid']);
            if (!$deleted) {
                ApiEnvelope::error([['message' => 'Product not found']], 404);

                return;
            }
            ApiEnvelope::success(['uuid' => $params['uuid'], 'deleted' => true]);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function listByFamily(array $params): void
    {
        try {
            $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            $result = $this->productService->listByFamilyUuid($params['uuid'], $limit, $offset);
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
