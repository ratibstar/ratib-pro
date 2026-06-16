<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Services;

use RATEB\InfrastructureMarketplace\Config\ModuleConfig;
use RATEB\InfrastructureMarketplace\Domain\Contracts\ProvisioningOrchestratorInterface;
use RATEB\InfrastructureMarketplace\Domain\Contracts\QueueDispatcherInterface;
use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use RATEB\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use RATEB\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobLogRepository;
use RATEB\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use RATEB\InfrastructureMarketplace\Provisioning\Queue\DatabaseQueueDispatcher;
use RATEB\InfrastructureMarketplace\Provisioning\SyncQueueDispatcher;

final class ProvisioningOrchestrator implements ProvisioningOrchestratorInterface
{
    private QueueDispatcherInterface $queue;
    private InfrastructureEventEmitter $events;
    private ?InfrastructureMetrics $metrics;

    public function __construct(QueueDispatcherInterface $queue, InfrastructureEventEmitter $events, ?InfrastructureMetrics $metrics = null) {
        $this->queue = $queue;
        $this->events = $events;
        $this->metrics = $metrics;
    }


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
        $driver = ModuleConfig::defaultQueueDriver();
        $canUseDatabaseQueue = self::databaseQueueSchemaReady($pdo);

        if ($driver === 'database' && $canUseDatabaseQueue) {
            $queue = new DatabaseQueueDispatcher(
                new ProvisioningJobRepository($pdo),
                new ProvisioningJobLogRepository($pdo)
            );
            return new self($queue, $events, $metrics);
        }

        if ($driver === 'database' && !$canUseDatabaseQueue) {
            $events->structuredLog('warn', 'Database queue requested but queue schema is incomplete; falling back to sync dispatcher.');
        }

        if ($driver === 'redis') {
            if ($canUseDatabaseQueue) {
                $events->structuredLog('warn', 'Redis queue requested but no redis dispatcher is implemented; using database queue fallback.');
                $queue = new DatabaseQueueDispatcher(
                    new ProvisioningJobRepository($pdo),
                    new ProvisioningJobLogRepository($pdo)
                );

                return new self($queue, $events, $metrics);
            }
            $events->structuredLog('warn', 'Redis queue requested but unavailable; falling back to sync dispatcher.');
        }

        if ($driver === 'sync' && ModuleConfig::isModuleEnabled() && !ModuleConfig::dryRunMode() && $canUseDatabaseQueue) {
            $events->structuredLog('info', 'Live execution with sync queue detected; auto-promoting to database queue for recovery safety.');
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
            throw new \RuntimeException('Infrastructure marketplace module is disabled (RATEB_INFRA_MARKETPLACE_ENABLED).');
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

    private static function databaseQueueSchemaReady(\PDO $pdo): bool
    {
        try {
            return SchemaHelpers::tableExists($pdo, 'rateb_infra_provisioning_jobs')
                && SchemaHelpers::tableExists($pdo, 'rateb_infra_job_logs');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
