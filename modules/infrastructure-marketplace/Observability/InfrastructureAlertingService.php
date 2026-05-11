<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Observability;

use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;

final class InfrastructureAlertingService
{
    private InfrastructureEventEmitter $events;

    public function __construct(InfrastructureEventEmitter $events) {
        $this->events = $events;
    }


    /**
     * @param array<string, mixed> $meta
     */
    public function providerOutage(string $provider, array $meta = []): void
    {
        $this->events->structuredLog('error', 'Provider outage alert', array_merge(['provider' => $provider], $meta));
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function failedProvisioning(string $jobPublicId, array $meta = []): void
    {
        $this->events->structuredLog('error', 'Failed provisioning alert', array_merge(['job_public_id' => $jobPublicId], $meta));
    }

    public function queueSaturation(int $depth, int $threshold): void
    {
        $this->events->structuredLog('warn', 'Queue saturation alert', ['depth' => $depth, 'threshold' => $threshold]);
    }

    public function workerFailure(string $worker, string $reason): void
    {
        $this->events->structuredLog('error', 'Worker failure alert', ['worker' => $worker, 'reason' => $reason]);
    }

    public function sslExpiration(string $fqdn, int $daysLeft): void
    {
        $this->events->structuredLog('warn', 'SSL expiration alert', ['fqdn' => $fqdn, 'days_left' => $daysLeft]);
    }

    public function reconciliationAnomaly(string $jobPublicId, string $reason): void
    {
        $this->events->structuredLog('warn', 'Reconciliation anomaly alert', ['job_public_id' => $jobPublicId, 'reason' => $reason]);
    }
}

