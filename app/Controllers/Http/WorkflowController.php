<?php
declare(strict_types=1);

namespace App\Controllers\Http;

use App\Services\WorkflowService;

final class WorkflowController
{
    public function __construct(private readonly WorkflowService $workflowService)
    {
    }

    /** @param array<string, mixed> $payload */
    public function onboardWorker(array $payload): array
    {
        return $this->workflowService->runWorkerOnboarding($payload);
    }
}
