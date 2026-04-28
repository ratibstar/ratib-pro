<?php
declare(strict_types=1);

namespace App\Workflows\Steps;

use App\Core\Contracts\WorkflowStepInterface;
use App\Services\TrackingService;

final class StartTrackingStep implements WorkflowStepInterface
{
    public function __construct(private readonly TrackingService $trackingService)
    {
    }

    public function execute(array $context): array
    {
        $this->trackingService->logMovement([
            'worker_id' => (int) $context['worker']['id'],
            'latitude' => (float) ($context['tracking']['latitude'] ?? 24.7136),
            'longitude' => (float) ($context['tracking']['longitude'] ?? 46.6753),
            'location_name' => (string) ($context['tracking']['location_name'] ?? 'Onboarding checkpoint'),
        ]);
        return $context;
    }
}
