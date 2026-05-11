<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Observability;

use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;

final class InfrastructureMetrics
{
    private InfrastructureEventEmitter $events;

    public function __construct(InfrastructureEventEmitter $events) {
        $this->events = $events;
    }


    public function markLatencyMs(string $operation, float $ms, ?string $jobPublicId = null): void
    {
        $this->events->metric('provisioning_latency_ms', [
            'operation' => $operation,
            'latency_ms' => round($ms, 2),
            'job_public_id' => $jobPublicId,
        ]);
    }

    public function incrementFailureCounter(string $operation, string $reason): void
    {
        $this->events->metric('provisioning_failure_count', [
            'operation' => $operation,
            'reason' => $reason,
        ]);
    }

    public function reconciliationReport(string $jobPublicId, string $status): void
    {
        $this->events->metric('reconciliation_report', [
            'job_public_id' => $jobPublicId,
            'status' => $status,
        ]);
    }

    public function queueDepth(int $depth): void
    {
        $this->events->metric('queue_depth', ['depth' => $depth]);
    }

    public function workerHealth(string $worker, string $status): void
    {
        $this->events->metric('worker_health', [
            'worker' => $worker,
            'status' => $status,
        ]);
    }

    public function providerErrorCounter(string $provider, string $reason): void
    {
        $this->events->metric('provider_error_count', [
            'provider' => $provider,
            'reason' => $reason,
        ]);
    }

    public function externalDependencyStatus(string $dependency, string $status): void
    {
        $this->events->metric('external_dependency_status', [
            'dependency' => $dependency,
            'status' => $status,
        ]);
    }

    public function providerSla(string $provider, float $uptimeRatio): void
    {
        $this->events->metric('provider_sla_ratio', [
            'provider' => $provider,
            'uptime_ratio' => round($uptimeRatio, 5),
        ]);
    }

    public function provisioningSuccessRatio(float $ratio): void
    {
        $this->events->metric('provisioning_success_ratio', [
            'ratio' => round($ratio, 5),
        ]);
    }

    public function orderConversionMetric(string $stage, int $count): void
    {
        $this->events->metric('order_conversion_metric', [
            'stage' => $stage,
            'count' => $count,
        ]);
    }

    public function queuePressure(float $pressureRatio): void
    {
        $this->events->metric('queue_pressure_ratio', [
            'ratio' => round($pressureRatio, 5),
        ]);
    }

    public function lifecycleEvent(string $eventType, string $state): void
    {
        $this->events->metric('lifecycle_event_analytics', [
            'event_type' => $eventType,
            'state' => $state,
        ]);
    }
}

