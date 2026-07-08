<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;

/**
 * RabbitMQ adapter delegates persistence to the database job queue while using
 * the RabbitMQ management HTTP API for broker health checks when configured.
 */
final class RabbitMqQueueAdapter implements QueueAdapterInterface
{
    public function __construct(
        private readonly JobQueueWriteRepositoryInterface $repository
    ) {
    }

    public function push(Job $job): string
    {
        return $this->repository->push($job);
    }

    public function pushDelayed(Job $job, int $delaySeconds): string
    {
        $availableAt = (new \DateTimeImmutable())->modify('+' . $delaySeconds . ' seconds');

        return $this->repository->push($job, $availableAt);
    }

    public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job
    {
        return $this->repository->pop($queue, $workerId, $visibilityTimeoutSeconds);
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
        $host = (string) (getenv('RABBITMQ_MANAGEMENT_HOST') ?: '');
        if ($host === '') {
            return true;
        }

        $url = rtrim($host, '/') . '/api/overview';
        $user = (string) (getenv('RABBITMQ_USER') ?: 'guest');
        $password = (string) (getenv('RABBITMQ_PASSWORD') ?: 'guest');
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Basic ' . base64_encode($user . ':' . $password),
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);

        return is_string($response) && $response !== '';
    }
}
