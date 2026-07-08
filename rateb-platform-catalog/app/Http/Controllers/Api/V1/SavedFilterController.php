<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\SavedFilterService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class SavedFilterController
{
    public function __construct(
        private readonly SavedFilterService $savedFilterService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $entityType = isset($_GET['entity_type']) ? (string) $_GET['entity_type'] : null;
            $result = $this->savedFilterService->list($entityType);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $result = $this->savedFilterService->getByUuid($params['uuid']);
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Saved filter not found']], 404);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->savedFilterService->create(Request::jsonBody());
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
            $result = $this->savedFilterService->update($params['uuid'], Request::jsonBody());
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Saved filter not found']], 404);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
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
            $deleted = $this->savedFilterService->delete($params['uuid']);
            if (!$deleted) {
                ApiEnvelope::error([['message' => 'Saved filter not found']], 404);

                return;
            }
            ApiEnvelope::success(['uuid' => $params['uuid'], 'deleted' => true]);
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
