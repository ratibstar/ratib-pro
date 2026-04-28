<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\EventDispatcher;
use App\Domain\Contracts\TrackingLogRepositoryInterface;
use App\Domain\Contracts\WorkerRepositoryInterface;
use App\Events\WorkerMoved;
use InvalidArgumentException;

final class TrackingService
{
    public function __construct(
        private readonly TrackingLogRepositoryInterface $trackingLogRepository,
        private readonly WorkerRepositoryInterface $workerRepository,
        private readonly EventDispatcher $events
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function logMovement(array $payload): int
    {
        $worker = $this->workerRepository->findById((int) ($payload['worker_id'] ?? 0));
        if ($worker === null) {
            throw new InvalidArgumentException('Worker not found for tracking.');
        }

        $logId = $this->trackingLogRepository->create($payload);

        $this->events->event(
            new WorkerMoved(
                (int) $payload['worker_id'],
                (float) $payload['latitude'],
                (float) $payload['longitude']
            )
        );

        return $logId;
    }
}
