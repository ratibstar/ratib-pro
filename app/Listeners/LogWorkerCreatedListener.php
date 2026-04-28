<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\WorkerCreated;
use App\Repositories\EventLogRepository;

final class LogWorkerCreatedListener
{
    public function __construct(private readonly EventLogRepository $eventLogs)
    {
    }

    public function __invoke(WorkerCreated $event): void
    {
        $this->eventLogs->create('WorkerCreated', [
            'worker_id' => $event->workerId,
            'name' => $event->name,
        ]);
    }
}
