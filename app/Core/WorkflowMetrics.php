<?php
declare(strict_types=1);

namespace App\Core;

use App\Repositories\WorkflowRepository;

final class WorkflowMetrics
{
    /** @var WorkflowRepository */
    private $workflowRepository;
    /** @var array<int, float> */
    private array $startedAt = [];
    private int $totalStarted = 0;
    private int $totalCompleted = 0;
    private int $totalFailed = 0;
    private int $activeWorkflows = 0;
    private float $avgExecutionTime = 0.0;
    private int $totalExecutionTime = 0;
    /** @var array<string, array{started:int,completed:int,failed:int}> */
    private array $perWorkflowType = [];
    /** @var array<int, array{started:int,completed:int,failed:int}> */
    private array $perCountry = [];

    public function __construct(WorkflowRepository $workflowRepository)
    {
        $this->workflowRepository = $workflowRepository;
    }

    public function markStarted(int $workflowId): void
    {
        $this->totalStarted++;
        $this->activeWorkflows++;
        $this->startedAt[$workflowId] = microtime(true);
        try {
            $this->workflowRepository->incrementMetricsTotals();
            $this->incrementDimensions($workflowId, 'started');
        } catch (\Throwable $e) {
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
            $this->incrementDimensions($workflowId, 'completed');
        } catch (\Throwable $e) {
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
            $this->incrementDimensions($workflowId, 'failed');
        } catch (\Throwable $e) {
            // metrics are best-effort and must not affect workflow execution
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'global' => [
                'total_started' => $this->totalStarted,
                'total_completed' => $this->totalCompleted,
                'total_failed' => $this->totalFailed,
                'active_workflows' => $this->activeWorkflows,
                'avg_execution_time_ms' => (int) round($this->avgExecutionTime),
            ],
            'per_workflow_type' => $this->perWorkflowType,
            'per_country' => $this->perCountry,
        ];
    }

    private function incrementDimensions(int $workflowId, string $bucket): void
    {
        $dims = $this->workflowRepository->findDimensionsById($workflowId);
        if (!is_array($dims)) {
            return;
        }
        $wName = trim((string) ($dims['workflow_name'] ?? ''));
        if ($wName !== '') {
            if (!isset($this->perWorkflowType[$wName])) {
                $this->perWorkflowType[$wName] = ['started' => 0, 'completed' => 0, 'failed' => 0];
            }
            $this->perWorkflowType[$wName][$bucket]++;
        }
        $countryId = (int) ($dims['country_id'] ?? 0);
        if ($countryId > 0) {
            if (!isset($this->perCountry[$countryId])) {
                $this->perCountry[$countryId] = ['started' => 0, 'completed' => 0, 'failed' => 0];
            }
            $this->perCountry[$countryId][$bucket]++;
        }
    }

    private function durationMs(int $workflowId): int
    {
        $start = $this->startedAt[$workflowId] ?? microtime(true);
        unset($this->startedAt[$workflowId]);
        return max(0, (int) round((microtime(true) - $start) * 1000));
    }
}
