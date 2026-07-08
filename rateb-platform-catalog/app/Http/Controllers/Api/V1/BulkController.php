<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\BulkService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class BulkController
{
    public function __construct(
        private readonly BulkService $bulkService
    ) {
    }

    /** @param array<string, string> $params */
    public function importProducts(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->bulkService->startImport(Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 202);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function exportProducts(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->bulkService->startExport(Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 202);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function publishProducts(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->bulkService->bulkPublish(Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 202);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function archiveProducts(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->bulkService->bulkArchive(Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 202);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
        ApiEnvelope::error([['message' => $e->getMessage()]], $status);
    }
}
