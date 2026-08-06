<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers\Api;

use Rateb\App\Logistics\Services\DriverApi\LogisticsDriverLocationApiService;

final class DriverLocationController extends LogisticsDriverApiController
{
    private LogisticsDriverLocationApiService $locations;

    public function __construct(
        ?LogisticsDriverLocationApiService $locations = null,
        ?\Rateb\App\Logistics\Services\DriverApi\LogisticsDriverContextService $contextService = null,
        ?\Rateb\App\Logistics\Services\DriverApi\LogisticsIdempotencyService $idempotency = null
    ) {
        parent::__construct($contextService, $idempotency);
        $this->locations = $locations ?? new LogisticsDriverLocationApiService();
    }

    public function update(): void
    {
        $context = $this->requireDriverContext();
        if ($context === null) {
            return;
        }
        $payload = $this->jsonBody();
        $this->respondIdempotent($context, 'location.update', $payload, function () use ($context, $payload) {
            return $this->locations->updateLocation($context, $payload);
        });
    }
}
