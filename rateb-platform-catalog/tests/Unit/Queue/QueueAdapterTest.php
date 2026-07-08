<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Policies\PermissivePolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\QueueAdminPolicy;
use Rateb\PlatformCatalog\Application\Services\QueueService;
use Rateb\PlatformCatalog\Infrastructure\Queue\DatabaseQueueAdapter;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Infrastructure\Queue\RabbitMqQueueAdapter;
use Rateb\PlatformCatalog\Infrastructure\Queue\RedisQueueAdapter;
use Rateb\PlatformCatalog\Infrastructure\Queue\SqsQueueAdapter;
use Rateb\PlatformCatalog\Infrastructure\Search\OpenSearchAdapter as SearchOpenSearchAdapter;
use Rateb\PlatformCatalog\Tests\Support\EmptyJobQueueWriteRepository;

catalog_test('DatabaseQueueAdapter delegates push to repository', static function (): void {
    $captured = null;
    $repo = new class($captured) extends EmptyJobQueueWriteRepository {
        public function __construct(private mixed &$captured)
        {
        }

        public function push(Job $job, ?\DateTimeImmutable $availableAt = null): string
        {
            $this->captured = $job->jobType;

            return $job->jobId;
        }
    };

    $adapter = new DatabaseQueueAdapter($repo);
    $adapter->push(new Job('j1', 'search', 'health_check', []));
    catalog_assert_same('health_check', $captured);
});

catalog_test('RedisQueueAdapter delegates push to repository', static function (): void {
    $captured = null;
    $repo = new class($captured) extends EmptyJobQueueWriteRepository {
        public function __construct(private mixed &$captured)
        {
        }

        public function push(Job $job, ?\DateTimeImmutable $availableAt = null): string
        {
            $this->captured = $job->jobType;

            return $job->jobId;
        }
    };

    $redis = new class implements \Rateb\PlatformCatalog\Infrastructure\Redis\RedisConnectionInterface {
        public function ping(): bool
        {
            return true;
        }

        public function get(string $key): ?string
        {
            return null;
        }

        public function set(string $key, string $value, ?int $ttlSeconds = null): void
        {
        }

        public function del(string $key): void
        {
        }

        public function incr(string $key): int
        {
            return 1;
        }

        public function expire(string $key, int $ttlSeconds): void
        {
        }

        public function lpush(string $key, string $value): void
        {
        }

        public function rpop(string $key): ?string
        {
            return null;
        }

        public function zadd(string $key, float $score, string $member): void
        {
        }

        public function zrangebyscore(string $key, float $min, float $max, int $limit = 1): array
        {
            return [];
        }

        public function zrem(string $key, string $member): void
        {
        }
    };

    $adapter = new RedisQueueAdapter($repo, $redis);
    $adapter->push(new Job('j1', 'search', 'health_check', []));
    catalog_assert_same('health_check', $captured);
});

catalog_test('RabbitMq and Sqs adapters delegate push to repository', static function (): void {
    $captured = null;
    $repo = new class($captured) extends EmptyJobQueueWriteRepository {
        public function __construct(private mixed &$captured)
        {
        }

        public function push(Job $job, ?\DateTimeImmutable $availableAt = null): string
        {
            $this->captured = $job->jobType;

            return $job->jobId;
        }
    };

    foreach ([new RabbitMqQueueAdapter($repo), new SqsQueueAdapter($repo)] as $adapter) {
        $adapter->push(new Job('j2', 'integration', 'webhook_dispatch', []));
        catalog_assert_same('webhook_dispatch', $captured);
    }
});

catalog_test('OpenSearch adapter falls back to in-memory search in testing', static function (): void {
    $adapter = new SearchOpenSearchAdapter();
    $result = $adapter->search(new \Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery('x', 'en', 'product'));
    catalog_assert_true($result instanceof \Rateb\PlatformCatalog\Infrastructure\Search\SearchResult);
    catalog_assert_same(0, $result->total);
});

catalog_test('QueueService returns formatted job status', static function (): void {
    $service = new QueueService(
        new class(new EmptyJobQueueWriteRepository()) implements \Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterInterface {
            public function __construct(private EmptyJobQueueWriteRepository $repo)
            {
            }

            public function push(Job $job): string
            {
                return $job->jobId;
            }

            public function pushDelayed(Job $job, int $delaySeconds): string
            {
                return $job->jobId;
            }

            public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job
            {
                return $this->repo->pop($queue, $workerId, $visibilityTimeoutSeconds);
            }

            public function acknowledge(string $jobId): void
            {
            }

            public function fail(string $jobId, string $reason): void
            {
            }

            public function retry(string $jobId): void
            {
            }
        },
        new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueReadRepositoryInterface {
            public function findByJobId(string $jobId): ?array
            {
                return [
                    'job_id' => $jobId,
                    'queue' => 'search',
                    'job_type' => 'search_full_reindex',
                    'status' => 'pending',
                    'payload' => '{"locale":"en"}',
                    'attempts' => 0,
                    'max_attempts' => 5,
                ];
            }

            public function countByQueueAndStatus(): array
            {
                return ['search:pending' => 2];
            }
        },
        new EmptyJobQueueWriteRepository(),
        new QueueAdminPolicy(new PermissivePolicyGuard())
    );

    $job = $service->getJobStatus('job-1');
    catalog_assert_true($job !== null);
    catalog_assert_same('search_full_reindex', $job['job_type']);

    $status = $service->getQueueStatus();
    catalog_assert_same(2, $status['queues']['search']);
});

catalog_test('MeilisearchAdapter requires host outside testing', static function (): void {
    $previous = getenv('APP_ENV');
    putenv('APP_ENV=production');
    try {
        new \Rateb\PlatformCatalog\Infrastructure\Search\MeilisearchAdapter('');
        catalog_assert_true(false, 'Expected RuntimeException');
    } catch (RuntimeException $e) {
        catalog_assert_true(str_contains($e->getMessage(), 'MEILISEARCH_HOST'));
    } finally {
        if ($previous === false) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $previous);
        }
    }
});
