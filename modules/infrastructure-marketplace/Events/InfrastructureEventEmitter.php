<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Events;

use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningJob;

/**
 * Bridges to core emitEvent(...) when present; prefix CONTROL_* aligns with EventBus allowances.
 */
final class InfrastructureEventEmitter
{
    public function provisioningSubmitted(ProvisioningJob $job, string $queueReference): void
    {
        $this->emit('CONTROL_INFRA_JOB_SUBMITTED', 'info', 'Infrastructure provisioning job queued', [
            'tenant_id' => $job->tenant()->tenantId(),
            'agency_id' => $job->tenant()->agencyId(),
            'queue_reference' => $queueReference,
            'steps' => $job->steps(),
            'correlation_id' => $job->correlationId(),
        ]);
    }

    public function provisioningReconcileRequested(string $jobId): void
    {
        $this->emit('CONTROL_INFRA_RECONCILE', 'info', 'Infrastructure reconcile requested', [
            'job_id' => $jobId,
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function metric(string $name, array $metadata): void
    {
        $this->emit('CONTROL_INFRA_METRIC', 'info', 'Infrastructure metric recorded: ' . $name, [
            'metric' => $name,
            'data' => $metadata,
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function structuredLog(string $level, string $message, array $metadata = []): void
    {
        $this->emit('CONTROL_INFRA_LOG', $level, $message, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function emit(string $type, string $level, string $message, array $metadata): void
    {
        $path = dirname(__DIR__, 3) . '/admin/core/EventBus.php';
        if (!is_file($path)) {
            return;
        }
        require_once $path;
        if (!function_exists('emitEvent')) {
            return;
        }
        emitEvent($type, $level, $message, $metadata);
    }
}
