<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Services;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\Contracts\ProvisioningOrchestratorInterface;
use Ratib\InfrastructureMarketplace\Domain\Contracts\QueueDispatcherInterface;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use Ratib\InfrastructureMarketplace\Provisioning\SyncQueueDispatcher;

final class ProvisioningOrchestrator implements ProvisioningOrchestratorInterface
{
    public function __construct(
        private readonly QueueDispatcherInterface $queue,
        private readonly InfrastructureEventEmitter $events
    ) {}

    public static function createDefault(): self
    {
        return new self(
            new SyncQueueDispatcher(),
            new InfrastructureEventEmitter()
        );
    }

    public function submit(ProvisioningJob $job): string
    {
        if (!ModuleConfig::isModuleEnabled()) {
            throw new \RuntimeException('Infrastructure marketplace module is disabled (RATIB_INFRA_MARKETPLACE_ENABLED).');
        }
        $id = $this->queue->enqueue($job);
        $this->events->provisioningSubmitted($job, $id);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcile(string $jobId): array
    {
        $this->events->provisioningReconcileRequested($jobId);

        return ['job_id' => $jobId, 'state' => 'foundation_noop'];
    }
}
