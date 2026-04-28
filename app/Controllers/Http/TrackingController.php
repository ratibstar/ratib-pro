<?php
declare(strict_types=1);

namespace App\Controllers\Http;

use App\Services\ComplianceService;
use App\Services\TrackingService;

final class TrackingController
{
    public function __construct(
        private readonly TrackingService $trackingService,
        private readonly ComplianceService $complianceService
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function move(array $payload): array
    {
        $logId = $this->trackingService->logMovement($payload);
        $violationId = $this->complianceService->inspectMovement($payload);

        return [
            'tracking_log_id' => $logId,
            'violation_id' => $violationId,
        ];
    }
}
