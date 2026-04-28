<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\EventDispatcher;
use App\Domain\Contracts\ViolationRepositoryInterface;
use App\Events\ViolationDetected;

final class ComplianceService
{
    public function __construct(
        private readonly ViolationRepositoryInterface $violationRepository,
        private readonly EventDispatcher $events
    ) {
    }

    /** @param array<string, mixed> $trackingPayload */
    public function inspectMovement(array $trackingPayload): ?int
    {
        $speed = (float) ($trackingPayload['speed_kmh'] ?? 0.0);
        if ($speed <= 120.0) {
            return null;
        }

        $violationId = $this->violationRepository->create([
            'worker_id' => (int) $trackingPayload['worker_id'],
            'violation_type' => 'speed_anomaly',
            'severity' => 'high',
            'details' => 'Speed exceeded safe threshold.',
        ]);

        $this->events->event(new ViolationDetected((int) $trackingPayload['worker_id'], 'speed_anomaly', 'high'));
        return $violationId;
    }
}
