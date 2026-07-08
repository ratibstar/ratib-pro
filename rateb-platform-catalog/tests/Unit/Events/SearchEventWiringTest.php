<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductCreated;
use Rateb\PlatformCatalog\Application\Listeners\SearchIndexingListener;
use Rateb\PlatformCatalog\Application\Services\QueueService;
use Rateb\PlatformCatalog\Application\Services\SearchIndexerService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Search\InMemorySearchAdapter;
use Rateb\PlatformCatalog\Tests\Support\EmptyJobQueueWriteRepository;

catalog_test('ProductService dispatches ProductCreated', static function (): void {
    $events = new EventDispatcher();
    $dispatched = false;
    $events->listen('ProductCreated', static function (ProductCreated $event) use (&$dispatched): void {
        $dispatched = true;
        catalog_assert_same('new-product', $event->payload()['product_uuid']);
    });

    $read = new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface {
        public function findByUuid(string $uuid, \Rateb\PlatformCatalog\Application\DTO\LocaleContext $locale): ?array
        {
            return ['uuid' => $uuid, 'sku' => 'SKU', 'lock_version' => 1];
        }

        public function list(\Rateb\PlatformCatalog\Application\DTO\LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listFiltered(\Rateb\PlatformCatalog\Application\DTO\LocaleContext $locale, \Rateb\PlatformCatalog\Application\DTO\ProductListFilter $filter, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listByFamilyUuid(string $familyUuid, \Rateb\PlatformCatalog\Application\DTO\LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function findLockVersion(string $uuid): ?int
        {
            return 1;
        }

        public function findWorkflowMeta(string $uuid): ?array
        {
            return null;
        }
    };

    $write = new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWriteRepositoryInterface {
        public function create(array $data): string
        {
            return '';
        }

        public function update(string $uuid, array $data): bool
        {
            return false;
        }

        public function softDelete(string $uuid, ?int $actorId = null): bool
        {
            return false;
        }

        public function createWithTranslations(array $productData, array $translations, ?int $actorId = null): string
        {
            return 'new-product';
        }

        public function updateWithTranslations(string $uuid, array $productData, array $translations, int $expectedLockVersion, ?int $actorId = null): int
        {
            return 1;
        }
    };

    $guard = new \Rateb\PlatformCatalog\Tests\Support\ConfigurablePolicyGuard(true);

    $service = new \Rateb\PlatformCatalog\Application\Services\ProductService(
        $read,
        $write,
        new \Rateb\PlatformCatalog\Application\Policies\ProductPolicy($guard),
        new \Rateb\PlatformCatalog\Application\Services\LocaleResolverService(),
        new \Rateb\PlatformCatalog\Application\Services\ConcurrencyService(),
        $events
    );

    $service->create(['sku' => 'SKU-NEW'], new \Rateb\PlatformCatalog\Application\DTO\LocaleContext('en', 'ar'));
    catalog_assert_true($dispatched);
});

catalog_test('SearchIndexingListener removes product from all locale indexes on delete', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $adapter->indexProduct(['uuid' => 'p1', 'name' => 'X', 'boost_score' => 1], 'en');
    $adapter->indexProduct(['uuid' => 'p1', 'name' => 'X', 'boost_score' => 1], 'ar');

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
            public int $deleteEnqueues = 0;

            public function enqueue(string $entityType, string $entityUuid, string $locale, string $action = 'upsert'): string
            {
                if ($action === 'delete') {
                    $this->deleteEnqueues++;
                }

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

    $queueWrite = new EmptyJobQueueWriteRepository();
    $listener = new SearchIndexingListener($indexer, new QueueService(
        new class($queueWrite) implements \Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterInterface {
            public function __construct(private EmptyJobQueueWriteRepository $repo)
            {
            }

            public function push(\Rateb\PlatformCatalog\Infrastructure\Queue\Job $job): string
            {
                return $this->repo->push($job);
            }

            public function pushDelayed(\Rateb\PlatformCatalog\Infrastructure\Queue\Job $job, int $delaySeconds): string
            {
                return $this->repo->push($job);
            }

            public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?\Rateb\PlatformCatalog\Infrastructure\Queue\Job
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
                return null;
            }

            public function countByQueueAndStatus(): array
            {
                return [];
            }
        },
        $queueWrite,
        new \Rateb\PlatformCatalog\Application\Policies\QueueAdminPolicy(new \Rateb\PlatformCatalog\Application\Policies\PermissivePolicyGuard())
    ));

    $listener->onProductDeleted(new \Rateb\PlatformCatalog\Application\Events\ProductDeleted('p1'));

    $enResult = $adapter->search(new \Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery('', 'en', 'product'));
    $arResult = $adapter->search(new \Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery('', 'ar', 'product'));
    catalog_assert_same(0, $enResult->total);
    catalog_assert_same(0, $arResult->total);
});
