<?php
declare(strict_types=1);

namespace App\Workflows\Steps;

use App\Core\Contracts\WorkflowStepInterface;
use App\Services\WorkerService;

final class CreateWorkerStep implements WorkflowStepInterface
{
    public function __construct(private readonly WorkerService $workerService)
    {
    }

    public function execute(array $context): array
    {
        $worker = $this->workerService->createWorker($context['worker']);
        $context['worker'] = $worker;
        return $context;
    }
}
