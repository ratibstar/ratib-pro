<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterInterface;

final class JobProcessorService
{
    /** @var list<JobHandlerInterface> */
    private array $handlers = [];

    public function __construct(
        private readonly QueueAdapterInterface $queueAdapter
    ) {
    }

    public function registerHandler(JobHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function processNext(string $queue): bool
    {
        $job = $this->queueAdapter->pop($queue);
        if ($job === null) {
            return false;
        }

        $this->processJob($job);

        return true;
    }

    public function processJob(Job $job): void
    {
        foreach ($this->handlers as $handler) {
            if (!$handler->supports($job->jobType)) {
                continue;
            }

            try {
                $handler->handle($job);
                $this->queueAdapter->acknowledge($job->jobId);

                return;
            } catch (\Throwable $e) {
                $this->queueAdapter->fail($job->jobId, $e->getMessage());
                throw $e;
            }
        }

        $this->queueAdapter->fail($job->jobId, 'No handler for job type: ' . $job->jobType);
    }
}
