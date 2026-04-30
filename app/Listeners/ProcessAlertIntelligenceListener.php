<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\ViolationDetected;
use App\Events\WorkflowExecutionFailed;
use App\Services\AlertService;

final class ProcessAlertIntelligenceListener
{
    public function __construct(private readonly AlertService $alerts)
    {
    }

    public function onViolationDetected(ViolationDetected $event): void
    {
        $this->alerts->ingestViolationDetected($event);
    }

    public function onWorkflowFailed(WorkflowExecutionFailed $event): void
    {
        $this->alerts->ingestWorkflowFailed($event);
    }
}
