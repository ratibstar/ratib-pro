<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Workers;

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use Ratib\InfrastructureMarketplace\Compliance\TenantIsolationCompliance;
use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureAlertingService;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Observability\ProviderEventBus;
use Ratib\InfrastructureMarketplace\Provisioning\Execution\ProvisioningExecutionEngine;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobLogRepository;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use Ratib\InfrastructureMarketplace\Provisioning\Queue\DatabaseQueueDispatcher;
use Ratib\InfrastructureMarketplace\Services\ProviderRegistry;

final class InfrastructureProvisioningWorker
{
    private bool $shouldStop = false;
    private string $workerName;


    public function __construct(string $workerName = 'infra-worker-1') {
        $this->workerName = $workerName;
    }


    public static function main(): void
    {
        require_once __DIR__ . '/bootstrap.php';
        $name = getenv('RATIB_INFRA_WORKER_NAME');
        $default = 'infra-worker-' . substr(sha1((string) gethostname() . ':' . (string) getmypid()), 0, 8);
        $worker = new self(is_string($name) && $name !== '' ? $name : $default);
        $worker->run();
    }

    public function run(): void
    {
        if (ModuleConfig::executionKillSwitch()) {
            return;
        }
        $pdo = DatabaseConnectionFactory::createPdo();
        $events = new InfrastructureEventEmitter();
        $metrics = new InfrastructureMetrics($events);
        $alerts = new InfrastructureAlertingService($events);
        $jobs = new ProvisioningJobRepository($pdo);
        $logs = new ProvisioningJobLogRepository($pdo);
        $queue = new DatabaseQueueDispatcher($jobs, $logs);
        $audit = new InfrastructureAuditLogger($pdo, $events);
        $compliance = new TenantIsolationCompliance($audit);
        $providers = ProviderRegistry::fromEnvironmentOrActivationTable($pdo);
        $engine = new ProvisioningExecutionEngine($jobs, $logs, $events, $metrics, $audit, $compliance, $providers);

        $this->registerSignalHandlers($events);
        $sleepMicros = 500000;
        $lockTtl = ModuleConfig::workerLockTtlSeconds();
        $processedJobs = 0;
        $maxLoopJobs = ModuleConfig::workerMaxLoopJobs();

        while (!$this->shouldStop) {
            $loopStart = microtime(true);
            if (ModuleConfig::executionKillSwitch()) {
                $events->structuredLog('warn', 'Execution kill switch triggered; worker stopping.', ['worker' => $this->workerName]);
                break;
            }
            $recovered = $jobs->recoverExpiredLocks($lockTtl);
            $depth = $jobs->queueDepth();
            $metrics->queueDepth($depth);
            $metrics->queuePressure(min(1, $depth / max(1, ModuleConfig::queuePressureThreshold())));
            if ($depth > ModuleConfig::queuePressureThreshold()) {
                $events->structuredLog('warn', 'Queue saturation alert', ['depth' => $depth, 'worker' => $this->workerName]);
                $alerts->queueSaturation($depth, ModuleConfig::queuePressureThreshold());
            }
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
                $alerts->workerFailure($this->workerName, 'job_exception');
                $isRetry = $attempts < $maxAttempts;
                ProviderEventBus::log('orchestration', 'provisioning_queue', $isRetry ? 'retries' : 'failures', [
                    'request_id' => bin2hex(random_bytes(8)),
                    'operation_name' => 'worker_execute',
                    'status' => $isRetry ? 'retry' : 'failed',
                    'retry_count' => $attempts,
                    'tenant_id' => isset($row['tenant_id']) ? (int) $row['tenant_id'] : null,
                    'agency_id' => isset($row['agency_id']) ? (int) $row['agency_id'] : null,
                    'payload' => [
                        'job_id' => $jobId,
                        'public_id' => (string) ($row['public_id'] ?? ''),
                        'max_attempts' => $maxAttempts,
                    ],
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
            }
            $processedJobs++;

            $elapsedMs = (microtime(true) - $loopStart) * 1000;
            $metrics->markLatencyMs('worker_loop', $elapsedMs, (string) ($row['public_id'] ?? ''));

            if (memory_get_usage(true) > (int) (getenv('RATIB_INFRA_WORKER_MEMORY_MAX') ?: 268435456)) {
                $events->structuredLog('warn', 'Worker stopping due to memory threshold', ['worker' => $this->workerName]);
                $this->shouldStop = true;
            }
            if ($processedJobs >= $maxLoopJobs) {
                $events->structuredLog('info', 'Worker recycling after max loop jobs', ['worker' => $this->workerName, 'processed_jobs' => $processedJobs]);
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

