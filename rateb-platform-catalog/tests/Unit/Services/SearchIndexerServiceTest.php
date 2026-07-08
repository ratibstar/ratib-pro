<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Events\VariantCreated;
use Rateb\PlatformCatalog\Application\Listeners\SearchIndexingListener;
use Rateb\PlatformCatalog\Application\Policies\PermissivePolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\QueueAdminPolicy;
use Rateb\PlatformCatalog\Application\Services\QueueService;
use Rateb\PlatformCatalog\Application\Services\SearchIndexerService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Infrastructure\Search\InMemorySearchAdapter;
use Rateb\PlatformCatalog\Tests\Support\EmptyJobQueueWriteRepository;

catalog_test('SearchIndexerService indexes product document', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $read = new class extends \Rateb\PlatformCatalog\Tests\Support\StubSearchIndexReadRepository {
        public function buildProductDocument(string $productUuid, string $locale): ?array
        {
            return [
                'uuid' => $productUuid,
                'name' => 'Indexed',
                'sku' => 'SKU',
                'boost_score' => 1,
            ];
        }
    };

    $queueRead = new class implements SearchIndexQueueReadRepositoryInterface {
        public function listPending(int $limit = 100): array
        {
            return [];
        }
    };

    $queueWrite = new class implements SearchIndexQueueWriteRepositoryInterface {
        public function enqueue(string $entityType, string $entityUuid, string $locale, string $action = 'upsert'): string
        {
            return 'q1';
        }

        public function markCompleted(string $uuid): void
        {
        }

        public function markFailed(string $uuid, string $error): void
        {
        }
    };

    $service = new SearchIndexerService($adapter, $read, $queueRead, $queueWrite);
    $service->indexProduct('p1', 'en');

    $result = $adapter->search(new \Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery('', 'en', 'product', [], 'relevance', 10, 0));
    catalog_assert_same(1, $result->total);
});

catalog_test('SearchIndexingListener enqueues variant reindex job', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $indexer = new SearchIndexerService(
        $adapter,
        new \Rateb\PlatformCatalog\Tests\Support\StubSearchIndexReadRepository(),
        new class implements SearchIndexQueueReadRepositoryInterface {
            public function listPending(int $limit = 100): array
            {
                return [];
            }
        },
        new class implements SearchIndexQueueWriteRepositoryInterface {
            public function enqueue(string $entityType, string $entityUuid, string $locale, string $action = 'upsert'): string
            {
                return 'q';
            }

            public function markCompleted(string $uuid): void
            {
            }

            public function markFailed(string $uuid, string $error): void
            {
            }
        }
    );

    $pushed = false;
    $repo = new EmptyJobQueueWriteRepository();
    $queue = new class($repo, $pushed) implements \Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterInterface {
        public function __construct(private EmptyJobQueueWriteRepository $repo, private bool &$pushed)
        {
        }

        public function push(Job $job): string
        {
            $this->pushed = $job->jobType === 'variant_reindex';

            return $this->repo->push($job);
        }

        public function pushDelayed(Job $job, int $delaySeconds): string
        {
            return $this->push($job);
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
    };

    $queueService = new QueueService(
        $queue,
        new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueReadRepositoryInterface {
            public function findByJobId(string $jobId): ?array
            {
                return null;
            }

            public function countByQueueAndStatus(): array
            {
                return [];
            }
        },
        $repo,
        new QueueAdminPolicy(new PermissivePolicyGuard())
    );

    $listener = new SearchIndexingListener($indexer, $queueService);
    $listener->onVariantChanged(new VariantCreated('p1', 'v1', 'en'));

    catalog_assert_true($pushed);
});
