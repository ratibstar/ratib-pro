<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\HealthService;
use Rateb\PlatformCatalog\Core\Response;

final class HealthController
{
    public function __construct(
        private readonly HealthService $healthService
    ) {
    }

    public function health(): void
    {
        Response::json([
            'data' => $this->healthService->liveness(),
            'meta' => [],
            'errors' => [],
        ]);
    }

    public function ready(): void
    {
        $payload = $this->healthService->readiness();
        $status = ($payload['status'] ?? '') === 'ready' ? 200 : 503;

        Response::json([
            'data' => $payload,
            'meta' => [],
            'errors' => $status === 200 ? [] : [['message' => 'Readiness check failed']],
        ], $status);
    }
}
