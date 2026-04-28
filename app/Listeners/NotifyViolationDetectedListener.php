<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\ViolationDetected;
use App\Repositories\EventLogRepository;
use App\Services\NotificationService;

final class NotifyViolationDetectedListener
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EventLogRepository $eventLogs
    ) {
    }

    public function __invoke(ViolationDetected $event): void
    {
        $this->notificationService->sendWorkerNotification(
            $event->workerId,
            sprintf('Violation detected: %s (%s)', $event->violationType, $event->severity),
            'compliance@gov.local'
        );

        $this->eventLogs->create('ViolationDetected', [
            'worker_id' => $event->workerId,
            'violation_type' => $event->violationType,
            'severity' => $event->severity,
        ]);
    }
}
