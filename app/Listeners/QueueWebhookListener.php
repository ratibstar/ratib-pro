<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\ViolationDetected;
use App\Events\WorkerCreated;
use App\Events\WorkflowExecutionCompleted;
use App\Services\WebhookService;

final class QueueWebhookListener
{
    public function __construct(private readonly WebhookService $webhooks)
    {
    }

    public function onWorkerCreated(WorkerCreated $event): void
    {
        $this->webhooks->enqueueWorkerCreated($event);
    }

    public function onViolationDetected(ViolationDetected $event): void
    {
        $this->webhooks->enqueueViolationDetected($event);
    }

    public function onWorkflowCompleted(WorkflowExecutionCompleted $event): void
    {
        $this->webhooks->enqueueWorkflowCompleted($event);
    }
}
