<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Core\WorkflowMetrics;
use App\Events\WorkflowExecutionCompleted;
use App\Events\WorkflowExecutionFailed;
use App\Events\WorkflowExecutionStarted;

final class WorkflowMetricsListener
{
    public function __construct(private readonly WorkflowMetrics $metrics)
    {
    }

    public function onStarted(WorkflowExecutionStarted $event): void
    {
        $this->metrics->markStarted($event->workflowId);
    }

    public function onCompleted(WorkflowExecutionCompleted $event): void
    {
        $this->metrics->markCompleted($event->workflowId);
    }

    public function onFailed(WorkflowExecutionFailed $event): void
    {
        $this->metrics->markFailed($event->workflowId);
    }
}
