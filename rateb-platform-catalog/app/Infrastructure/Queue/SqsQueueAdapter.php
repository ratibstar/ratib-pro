<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;

/**
 * SQS adapter persists job metadata in catalog job_queue and uses AWS SQS API
 * for broker health checks when configured.
 */
final class SqsQueueAdapter implements QueueAdapterInterface
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
        $queueUrl = (string) (getenv('SQS_QUEUE_URL') ?: '');
        if ($queueUrl === '') {
            return true;
        }

        $region = (string) (getenv('AWS_REGION') ?: 'us-east-1');
        $host = $region . '.queue.amazonaws.com';
        $path = parse_url($queueUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Host: {$host}\r\n",
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents('https://' . $host . $path . '?Action=GetQueueAttributes&AttributeName=All', false, $context);

        return is_string($response) && str_contains($response, 'GetQueueAttributesResponse');
    }
}
