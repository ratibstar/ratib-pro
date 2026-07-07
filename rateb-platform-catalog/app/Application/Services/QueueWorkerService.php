<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class QueueWorkerService
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly JobQueueWriteRepositoryInterface $jobWriteRepository,
        private readonly JobProcessorService $processor,
        private readonly int $visibilityTimeoutSeconds = 300,
        private readonly int $workerLockTtlSeconds = 60
    ) {
    }

    public function requestShutdown(): void
    {
        $this->shouldStop = true;
    }

    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }

    public function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        $handler = function (): void {
            $this->requestShutdown();
        };
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }

    /**
     * @param list<string> $queues
     */
    public function run(string $workerId, array $queues, int $maxJobs = 0, int $sleepSeconds = 1): int
    {
        $processed = 0;
        $this->registerSignalHandlers();

        while (!$this->shouldStop && ($maxJobs === 0 || $processed < $maxJobs)) {
            $this->jobWriteRepository->recoverStaleJobs($this->visibilityTimeoutSeconds);
            $worked = false;

            foreach ($queues as $queue) {
                if (!$this->jobWriteRepository->acquireWorkerLock($workerId, $queue, $this->workerLockTtlSeconds)) {
                    continue;
                }

                try {
                    $job = $this->jobWriteRepository->pop($queue, $workerId, $this->visibilityTimeoutSeconds);
                    if ($job === null) {
                        continue;
                    }

                    $worked = true;
                    $this->jobWriteRepository->heartbeat($job->jobId, $workerId, $this->visibilityTimeoutSeconds);
                    $this->processor->processJob($job);
                    $processed++;
                } catch (\Throwable $e) {
                    fwrite(STDERR, 'Job failed on queue ' . $queue . ': ' . $e->getMessage() . PHP_EOL);
                } finally {
                    $this->jobWriteRepository->releaseWorkerLock($workerId, $queue);
                }
            }

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }

            if (!$worked) {
                if ($maxJobs > 0) {
                    break;
                }
                sleep($sleepSeconds);
            }
        }

        return $processed;
    }
}
