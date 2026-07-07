<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\QueueService;
use Rateb\PlatformCatalog\Application\Services\SearchAdminService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class AdminQueueController
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly SearchAdminService $searchAdminService
    ) {
    }

    /** @param array<string, string> $params */
    public function queueStatus(array $params = []): void
    {
        unset($params);
        try {
            $status = $this->queueService->getQueueStatus();
            ApiEnvelope::success($status);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }

    /** @param array<string, string> $params */
    public function requestReindex(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->searchAdminService->requestReindex(Request::jsonBody());
            ApiEnvelope::success($result, [], [], 202);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }

    /** @param array<string, string> $params */
    public function replayJob(array $params): void
    {
        try {
            $replayed = $this->queueService->replayJob($params['job_id']);
            if (!$replayed) {
                ApiEnvelope::error([['message' => 'Job not found or not dead']], 404);

                return;
            }
            ApiEnvelope::success(['job_id' => $params['job_id'], 'replayed' => true]);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }
}
