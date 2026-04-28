<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\WorkflowExecutionCompleted;
use App\Events\WorkflowExecutionFailed;
use App\Events\WorkflowExecutionStarted;
use App\Repositories\EventLogRepository;

final class LogWorkflowExecutionEventListener
{
    public function __construct(private readonly EventLogRepository $eventLogs)
    {
    }

    public function onStarted(WorkflowExecutionStarted $event): void
    {
        $this->eventLogs->create('WorkflowExecutionStarted', [
            'workflow_id' => $event->workflowId,
            'workflow_name' => $event->workflowName,
            'mode' => $event->mode,
            'actor' => $event->actor,
            'sequence_number' => $event->sequenceNumber,
            'event_chain' => $event->eventChain,
            'timestamp' => $event->timestamp,
        ]);
    }

    public function onCompleted(WorkflowExecutionCompleted $event): void
    {
        $this->eventLogs->create('WorkflowExecutionCompleted', [
            'workflow_id' => $event->workflowId,
            'workflow_name' => $event->workflowName,
            'mode' => $event->mode,
            'actor' => $event->actor,
            'sequence_number' => $event->sequenceNumber,
            'event_chain' => $event->eventChain,
            'timestamp' => $event->timestamp,
        ]);
    }

    public function onFailed(WorkflowExecutionFailed $event): void
    {
        $this->eventLogs->create('WorkflowExecutionFailed', [
            'workflow_id' => $event->workflowId,
            'workflow_name' => $event->workflowName,
            'mode' => $event->mode,
            'actor' => $event->actor,
            'sequence_number' => $event->sequenceNumber,
            'error' => $event->error,
            'event_chain' => $event->eventChain,
            'timestamp' => $event->timestamp,
        ]);
    }
}
