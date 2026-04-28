<?php
declare(strict_types=1);

namespace App\Core;

use App\Repositories\WorkflowRepository;

final class WorkflowMetrics
{
    /** @var array<int, float> */
    private array $startedAt = [];
    private int $totalStarted = 0;
    private int $totalCompleted = 0;
    private int $totalFailed = 0;
    private int $activeWorkflows = 0;
    private float $avgExecutionTime = 0.0;
    private int $totalExecutionTime = 0;

    public function __construct(private readonly WorkflowRepository $workflowRepository)
    {
    }

    public function markStarted(int $workflowId): void
    {
        $this->totalStarted++;
        $this->activeWorkflows++;
        $this->startedAt[$workflowId] = microtime(true);
        try {
            $this->workflowRepository->incrementMetricsTotals();
        } catch (\Throwable) {
            // metrics are best-effort and must not affect workflow execution
        }
    }

    public function markCompleted(int $workflowId): void
    {
        $durationMs = $this->durationMs($workflowId);
        $this->totalCompleted++;
        $this->activeWorkflows = max(0, $this->activeWorkflows - 1);
        $this->totalExecutionTime += $durationMs;
        $this->avgExecutionTime = $this->totalCompleted > 0
            ? $this->totalExecutionTime / $this->totalCompleted
            : 0.0;
        try {
            $this->workflowRepository->incrementMetricsSuccess($durationMs);
        } catch (\Throwable) {
            // metrics are best-effort and must not affect workflow execution
        }
    }

    public function markFailed(int $workflowId): void
    {
        $durationMs = $this->durationMs($workflowId);
        $this->totalFailed++;
        $this->activeWorkflows = max(0, $this->activeWorkflows - 1);
        try {
            $this->workflowRepository->incrementMetricsFailure($durationMs);
        } catch (\Throwable) {
            // metrics are best-effort and must not affect workflow execution
        }
    }

    private function durationMs(int $workflowId): int
    {
        $start = $this->startedAt[$workflowId] ?? microtime(true);
        unset($this->startedAt[$workflowId]);
        return max(0, (int) round((microtime(true) - $start) * 1000));
    }
}
