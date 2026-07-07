<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\QueueAdminPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterInterface;
use Rateb\PlatformCatalog\Support\Uuid;

final class QueueService
{
    public function __construct(
        private readonly QueueAdapterInterface $queueAdapter,
        private readonly JobQueueReadRepositoryInterface $jobReadRepository,
        private readonly JobQueueWriteRepositoryInterface $jobWriteRepository,
        private readonly QueueAdminPolicy $policy
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(string $queue, string $jobType, array $payload, ?string $idempotencyKey = null): string
    {
        $this->policy->manage();
        $job = new Job(
            jobId: Uuid::v4(),
            queue: $queue,
            jobType: $jobType,
            payload: $payload,
            idempotencyKey: $idempotencyKey
        );

        return $this->queueAdapter->push($job);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueueSystem(string $queue, string $jobType, array $payload, ?string $idempotencyKey = null): string
    {
        $job = new Job(
            jobId: Uuid::v4(),
            queue: $queue,
            jobType: $jobType,
            payload: $payload,
            idempotencyKey: $idempotencyKey
        );

        return $this->queueAdapter->push($job);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJobStatus(string $jobId): ?array
    {
        $this->policy->view();
        $row = $this->jobReadRepository->findByJobId($jobId);

        return $row !== null ? $this->formatJob($row) : null;
    }

    /**
     * @return array{queues: array<string, int>, raw: array<string, int>}
     */
    public function getQueueStatus(): array
    {
        $this->policy->view();
        $raw = $this->jobReadRepository->countByQueueAndStatus();
        $queues = [];
        foreach ($raw as $key => $count) {
            [$queue, $status] = array_pad(explode(':', $key, 2), 2, '');
            $queues[$queue] = ($queues[$queue] ?? 0) + $count;
        }

        return ['queues' => $queues, 'raw' => $raw];
    }

    public function replayJob(string $jobId): bool
    {
        $this->policy->manage();

        return $this->jobWriteRepository->replayDead($jobId);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatJob(array $row): array
    {
        $payload = json_decode((string) ($row['payload'] ?? '{}'), true);

        return [
            'job_id' => $row['job_id'],
            'queue' => $row['queue'],
            'job_type' => $row['job_type'],
            'status' => $row['status'],
            'attempts' => (int) ($row['attempts'] ?? 0),
            'max_attempts' => (int) ($row['max_attempts'] ?? 5),
            'payload' => is_array($payload) ? $payload : [],
            'last_error' => $row['last_error'] ?? null,
            'available_at' => $row['available_at'] ?? null,
            'started_at' => $row['started_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
