<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\WorkerMoved;
use App\Repositories\EventLogRepository;

final class HandleWorkerMovedListener
{
    public function __construct(private readonly EventLogRepository $eventLogs)
    {
    }

    public function __invoke(WorkerMoved $event): void
    {
        $this->eventLogs->create('WorkerMoved', [
            'worker_id' => $event->workerId,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
        ]);
    }
}
