<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Redis\RedisConnectionInterface;
use Rateb\PlatformCatalog\Infrastructure\Redis\RedisConfig;

final class RedisQueueAdapter implements QueueAdapterInterface
{
    public function __construct(
        private readonly JobQueueWriteRepositoryInterface $repository,
        private readonly ?RedisConnectionInterface $redis = null
    ) {
    }

    private function redis(): RedisConnectionInterface
    {
        return $this->redis ?? new RedisClient(RedisConfig::fromEnvironment());
    }

    public function push(Job $job): string
    {
        $jobId = $this->repository->push($job);
        $this->redis()->lpush($this->queueKey($job->queue), $this->serializeJob($job));

        return $jobId;
    }

    public function pushDelayed(Job $job, int $delaySeconds): string
    {
        $availableAt = (new \DateTimeImmutable())->modify('+' . $delaySeconds . ' seconds');
        $jobId = $this->repository->push($job, $availableAt);
        $score = (float) $availableAt->format('U.u');
        $this->redis()->zadd($this->delayedKey($job->queue), $score, $this->serializeJob($job));

        return $jobId;
    }

    public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job
    {
        $this->promoteDelayed($queue);
        $serialized = $this->redis()->rpop($this->queueKey($queue));
        if ($serialized === null) {
            return $this->repository->pop($queue, $workerId, $visibilityTimeoutSeconds);
        }

        $job = $this->deserializeJob($serialized);
        $this->repository->pop($queue, $workerId, $visibilityTimeoutSeconds);

        return $job;
    }

    public function acknowledge(string $jobId): void
    {
        $this->repository->acknowledge($jobId);
    }

    public function fail(string $jobId, string $reason): void
    {
        $this->repository->fail($jobId, $reason);
    }

    public function retry(string $jobId): void
    {
        $this->repository->retry($jobId);
    }

    public function healthCheck(): bool
    {
        return $this->redis()->ping();
    }

    private function promoteDelayed(string $queue): void
    {
        $now = microtime(true);
        $ready = $this->redis()->zrangebyscore($this->delayedKey($queue), 0, $now, 100);
        foreach ($ready as $serialized) {
            $this->redis()->zrem($this->delayedKey($queue), $serialized);
            $this->redis()->lpush($this->queueKey($queue), $serialized);
        }
    }

    private function queueKey(string $queue): string
    {
        return 'queue:' . $queue;
    }

    private function delayedKey(string $queue): string
    {
        return 'queue:' . $queue . ':delayed';
    }

    private function serializeJob(Job $job): string
    {
        return json_encode([
            'job_id' => $job->jobId,
            'queue' => $job->queue,
            'job_type' => $job->jobType,
            'payload' => $job->payload,
            'idempotency_key' => $job->idempotencyKey,
            'max_attempts' => $job->maxAttempts,
            'attempts' => $job->attempts,
        ], JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function deserializeJob(string $serialized): Job
    {
        $decoded = json_decode($serialized, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid Redis job payload');
        }

        return new Job(
            jobId: (string) ($decoded['job_id'] ?? ''),
            queue: (string) ($decoded['queue'] ?? ''),
            jobType: (string) ($decoded['job_type'] ?? ''),
            payload: is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [],
            idempotencyKey: isset($decoded['idempotency_key']) ? (string) $decoded['idempotency_key'] : null,
            maxAttempts: (int) ($decoded['max_attempts'] ?? 5),
            attempts: (int) ($decoded['attempts'] ?? 0)
        );
    }
}
