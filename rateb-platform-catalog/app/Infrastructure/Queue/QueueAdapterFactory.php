<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;

final class QueueAdapterFactory
{
    public static function create(JobQueueWriteRepositoryInterface $jobQueueWrite): QueueAdapterInterface
    {
        $adapter = strtolower((string) (getenv('QUEUE_ADAPTER') ?: 'database'));

        return match ($adapter) {
            'redis' => new RedisQueueAdapter($jobQueueWrite),
            'rabbitmq' => new RabbitMqQueueAdapter($jobQueueWrite),
            'sqs' => new SqsQueueAdapter($jobQueueWrite),
            default => new DatabaseQueueAdapter($jobQueueWrite),
        };
    }
}
