<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Services;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\Contracts\ProvisioningOrchestratorInterface;
use Ratib\InfrastructureMarketplace\Domain\Contracts\QueueDispatcherInterface;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobLogRepository;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use Ratib\InfrastructureMarketplace\Provisioning\Queue\DatabaseQueueDispatcher;
use Ratib\InfrastructureMarketplace\Provisioning\SyncQueueDispatcher;

final class ProvisioningOrchestrator implements ProvisioningOrchestratorInterface
{
    public function __construct(
        private readonly QueueDispatcherInterface $queue,
        private readonly InfrastructureEventEmitter $events,
        private readonly ?InfrastructureMetrics $metrics = null
    ) {}

    public static function createDefault(): self
    {
        return new self(
            new SyncQueueDispatcher(),
            new InfrastructureEventEmitter()
        );
    }

    public static function createFromPdo(\PDO $pdo): self
    {
        $events = new InfrastructureEventEmitter();
        $metrics = new InfrastructureMetrics($events);

        if (ModuleConfig::defaultQueueDriver() === 'database') {
            $queue = new DatabaseQueueDispatcher(
                new ProvisioningJobRepository($pdo),
                new ProvisioningJobLogRepository($pdo)
            );
            return new self($queue, $events, $metrics);
        }

        return new self(new SyncQueueDispatcher(), $events, $metrics);
    }

    public function submit(ProvisioningJob $job): string
    {
        if (!ModuleConfig::isModuleEnabled()) {
            throw new \RuntimeException('Infrastructure marketplace module is disabled (RATIB_INFRA_MARKETPLACE_ENABLED).');
        }
        $started = microtime(true);
        $id = $this->queue->enqueue($job);
        $this->events->provisioningSubmitted($job, $id);
        $this->metrics?->markLatencyMs('enqueue', (microtime(true) - $started) * 1000, $id);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcile(string $jobId): array
    {
        $this->events->provisioningReconcileRequested($jobId);
        $this->metrics?->reconciliationReport($jobId, 'requested');

        return ['job_id' => $jobId, 'state' => 'foundation_noop'];
    }
}
