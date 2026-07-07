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
            'redis' => new RedisQueueAdapter(),
            'rabbitmq' => new RabbitMqQueueAdapter(),
            'sqs' => new SqsQueueAdapter(),
            default => new DatabaseQueueAdapter($jobQueueWrite),
        };
    }
}
