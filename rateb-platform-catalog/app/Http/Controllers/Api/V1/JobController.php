<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\QueueService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;

final class JobController
{
    public function __construct(
        private readonly QueueService $queueService
    ) {
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $job = $this->queueService->getJobStatus($params['job_id']);
            if ($job === null) {
                ApiEnvelope::error([['message' => 'Job not found']], 404);

                return;
            }
            ApiEnvelope::success($job);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }
}
