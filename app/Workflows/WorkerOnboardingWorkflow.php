<?php
declare(strict_types=1);

namespace App\Workflows;

use App\Core\Workflow;
use App\Core\Contracts\WorkflowStepInterface;
use App\Workflows\Steps\AssignEmployerStep;
use App\Workflows\Steps\CreateWorkerStep;
use App\Workflows\Steps\SendNotificationStep;
use App\Workflows\Steps\StartTrackingStep;
use App\Workflows\Steps\ValidateWorkerStep;

final class WorkerOnboardingWorkflow extends Workflow
{
    public function __construct(
        private readonly ValidateWorkerStep $validateWorker,
        private readonly CreateWorkerStep $createWorker,
        private readonly AssignEmployerStep $assignEmployer,
        private readonly StartTrackingStep $startTracking,
        private readonly SendNotificationStep $sendNotification
    ) {
    }

    /** @return WorkflowStepInterface[] */
    public function steps(): array
    {
        return [
            $this->validateWorker,
            $this->createWorker,
            $this->assignEmployer,
            $this->startTracking,
            $this->sendNotification,
        ];
    }

    public function maxRetries(): int
    {
        return 2;
    }
}
