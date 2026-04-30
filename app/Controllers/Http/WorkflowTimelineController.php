<?php
declare(strict_types=1);

namespace App\Controllers\Http;

use App\Services\WorkflowTimelineService;

final class WorkflowTimelineController
{
    public function __construct(private readonly WorkflowTimelineService $timeline)
    {
    }

    /** @return array<string, mixed> */
    public function show(int $workflowId): array
    {
        return $this->timeline->getTimeline($workflowId);
    }
}
