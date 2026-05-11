<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Observability;

use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;

final class InfrastructureMetrics
{
    public function __construct(
        private readonly InfrastructureEventEmitter $events
    ) {}

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
}

