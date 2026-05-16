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
        $workerId = (int) ($context['worker']['id'] ?? $context['worker_id'] ?? 0);
        if ($workerId <= 0) {
            return $context;
        }
        try {
            $this->trackingService->logMovement([
                'worker_id' => $workerId,
                'latitude' => (float) ($context['tracking']['latitude'] ?? 24.7136),
                'longitude' => (float) ($context['tracking']['longitude'] ?? 46.6753),
                'location_name' => (string) ($context['tracking']['location_name'] ?? 'Onboarding checkpoint'),
            ]);
        } catch (\Throwable $e) {
            // Mobile tracking is also provisioned via control API during Global AI; do not fail the whole workflow.
            error_log('StartTrackingStep: ' . $e->getMessage());
        }
        return $context;
    }
}
