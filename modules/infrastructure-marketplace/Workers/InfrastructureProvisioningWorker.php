<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Workers;

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use Ratib\InfrastructureMarketplace\Compliance\TenantIsolationCompliance;
use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Provisioning\Execution\ProvisioningExecutionEngine;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobLogRepository;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use Ratib\InfrastructureMarketplace\Provisioning\Queue\DatabaseQueueDispatcher;
use Ratib\InfrastructureMarketplace\Services\ProviderRegistry;

final class InfrastructureProvisioningWorker
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly string $workerName = 'infra-worker-1'
    ) {}

    public static function main(): void
    {
        require_once __DIR__ . '/bootstrap.php';
        $name = getenv('RATIB_INFRA_WORKER_NAME');
        $worker = new self(is_string($name) && $name !== '' ? $name : 'infra-worker-1');
        $worker->run();
    }

    public function run(): void
    {
        $pdo = DatabaseConnectionFactory::createPdo();
        $events = new InfrastructureEventEmitter();
        $metrics = new InfrastructureMetrics($events);
        $jobs = new ProvisioningJobRepository($pdo);
        $logs = new ProvisioningJobLogRepository($pdo);
        $queue = new DatabaseQueueDispatcher($jobs, $logs);
        $audit = new InfrastructureAuditLogger($pdo, $events);
        $compliance = new TenantIsolationCompliance($audit);
        $providers = ProviderRegistry::fromEnvironment();
        $engine = new ProvisioningExecutionEngine($jobs, $logs, $events, $metrics, $audit, $compliance, $providers);

        $this->registerSignalHandlers($events);
        $sleepMicros = 500000;
        $lockTtl = ModuleConfig::workerLockTtlSeconds();

        while (!$this->shouldStop) {
            $loopStart = microtime(true);
            $recovered = $jobs->recoverExpiredLocks($lockTtl);
            $metrics->queueDepth($jobs->queueDepth());
            if ($recovered > 0) {
                $events->structuredLog('warn', 'Recovered expired worker locks', ['count' => $recovered, 'worker' => $this->workerName]);
            }

            $row = $queue->lockNext();
            if ($row === null) {
                $this->heartbeat($pdo, $metrics);
                usleep($sleepMicros);
                continue;
            }

            $jobId = (int) ($row['id'] ?? 0);
            $attempts = (int) ($row['attempts'] ?? 0) + 1;
            $maxAttempts = (int) ($row['max_attempts'] ?? ModuleConfig::queueMaxAttempts());

            try {
                $engine->process($row, $this->workerName);
                $jobs->markSuccess($jobId);
                $logs->append($jobId, 'info', 'Worker marked job success', ['worker' => $this->workerName]);
            } catch (\Throwable $e) {
                $queue->fail($jobId, $attempts, $maxAttempts, 'worker_runtime_failure');
                $metrics->incrementFailureCounter('worker_job', 'runtime_exception');
                $events->structuredLog('error', 'Worker execution failure', ['job_id' => $jobId, 'worker' => $this->workerName]);
            }

            $elapsedMs = (microtime(true) - $loopStart) * 1000;
            $metrics->markLatencyMs('worker_loop', $elapsedMs, (string) ($row['public_id'] ?? ''));

            if (memory_get_usage(true) > (int) (getenv('RATIB_INFRA_WORKER_MEMORY_MAX') ?: 268435456)) {
                $events->structuredLog('warn', 'Worker stopping due to memory threshold', ['worker' => $this->workerName]);
                $this->shouldStop = true;
            }
        }
    }

    private function registerSignalHandlers(InfrastructureEventEmitter $events): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function () use ($events): void {
            $events->structuredLog('info', 'SIGTERM received. Worker shutting down.');
            $this->shouldStop = true;
        });
        pcntl_signal(SIGINT, function () use ($events): void {
            $events->structuredLog('info', 'SIGINT received. Worker shutting down.');
            $this->shouldStop = true;
        });
    }

    private function heartbeat(\PDO $pdo, InfrastructureMetrics $metrics): void
    {
        $sql = 'INSERT INTO ratib_infra_worker_heartbeats (worker_name, heartbeat_at, memory_bytes)
                VALUES (:worker_name, NOW(), :memory_bytes)
                ON DUPLICATE KEY UPDATE heartbeat_at = NOW(), memory_bytes = VALUES(memory_bytes)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'worker_name' => $this->workerName,
            'memory_bytes' => memory_get_usage(true),
        ]);
        $metrics->workerHealth($this->workerName, 'healthy');
    }
}

if (PHP_SAPI === 'cli' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    InfrastructureProvisioningWorker::main();
}

