<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;
use Rateb\PlatformCatalog\Application\Services\WorkflowCommentService;
use Rateb\PlatformCatalog\Application\Services\WorkflowService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowReadRepositoryInterface;
use Rateb\PlatformCatalog\Tests\Support\ConfigurablePolicyGuard;

catalog_test('WorkflowService history returns workflow rows when permitted', static function (): void {
    $historyRead = new class implements ProductWorkflowReadRepositoryInterface {
        public function listHistory(string $productUuid, int $limit = 50): array
        {
            catalog_assert_same('a1111111-1111-4111-8111-111111111111', $productUuid);
            catalog_assert_same(25, $limit);

            return [
                [
                    'uuid' => 'hist-1',
                    'from_status' => 'approved',
                    'to_status' => 'published',
                    'action' => 'publish',
                ],
            ];
        }
    };

    $service = new WorkflowService(
        new class implements WorkflowReadRepositoryInterface {
            public function findTransition(string $fromStatus, string $action): ?array
            {
                return null;
            }

            public function listStates(): array
            {
                return [];
            }
        },
        new class implements ProductWorkflowWriteRepositoryInterface {
            public function transitionStatus(
                string $productUuid,
                string $fromStatus,
                string $toStatus,
                string $action,
                int $lockVersion,
                ?int $actorId,
                ?string $comment,
                ?array $versionSnapshot = null,
                ?string $versionChangeType = null,
                ?string $versionChangeSummary = null
            ): array {
                return [];
            }
        },
        $historyRead,
        new class implements ProductReadRepositoryInterface {
            public function findByUuid(string $uuid, LocaleContext $locale): ?array
            {
                return null;
            }

            public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listFiltered(LocaleContext $locale, \Rateb\PlatformCatalog\Application\DTO\ProductListFilter $filter, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function findLockVersion(string $uuid): ?int
            {
                return 1;
            }

            public function findWorkflowMeta(string $uuid): ?array
            {
                return ['id' => 1, 'category_id' => 1, 'status' => 'published', 'version_number' => 2, 'lock_version' => 2];
            }
        },
        new WorkflowCommentService(
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface {
                public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array
                {
                    return [];
                }
            },
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentWriteRepositoryInterface {
                public function add(
                    string $entityType,
                    int $entityId,
                    string $entityUuid,
                    string $workflowAction,
                    ?string $fromStatus,
                    ?string $toStatus,
                    string $comment,
                    ?int $commentedBy
                ): string {
                    return 'c1';
                }
            },
            new class implements ProductReadRepositoryInterface {
                public function findByUuid(string $uuid, LocaleContext $locale): ?array
                {
                    return null;
                }

                public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function listFiltered(LocaleContext $locale, \Rateb\PlatformCatalog\Application\DTO\ProductListFilter $filter, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
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
            },
            new WorkflowPolicy(new TestPolicyGuard()),
            buildAuditEventService()
        ),
        buildCompletenessService(),
        buildSnapshotBuilder(),
        new WorkflowPolicy(new TestPolicyGuard()),
        new \Rateb\PlatformCatalog\Application\Services\ConcurrencyService(),
        buildAuditEventService(),
        new \Rateb\PlatformCatalog\Application\Events\EventDispatcher()
    );

    $items = $service->history('a1111111-1111-4111-8111-111111111111', 25);
    catalog_assert_same(1, count($items));
    catalog_assert_same('publish', $items[0]['action']);
});

catalog_test('WorkflowService history rejects invalid uuid', static function (): void {
    $service = buildWorkflowHarness('approved', 'published', 'publish')['service'];

    try {
        $service->history('not-a-uuid');
        throw new RuntimeException('Expected invalid uuid');
    } catch (\InvalidArgumentException $e) {
        catalog_assert_same('Invalid product uuid', $e->getMessage());
    }
});

catalog_test('WorkflowService history denies without catalog.products.view', static function (): void {
    $guard = new ConfigurablePolicyGuard(static fn (string $slug): bool => $slug !== 'catalog.products.view');
    $harness = buildWorkflowHarness('approved', 'published', 'publish');
    $service = new WorkflowService(
        new class implements WorkflowReadRepositoryInterface {
            public function findTransition(string $fromStatus, string $action): ?array
            {
                return null;
            }

            public function listStates(): array
            {
                return [];
            }
        },
        new class implements ProductWorkflowWriteRepositoryInterface {
            public function transitionStatus(
                string $productUuid,
                string $fromStatus,
                string $toStatus,
                string $action,
                int $lockVersion,
                ?int $actorId,
                ?string $comment,
                ?array $versionSnapshot = null,
                ?string $versionChangeType = null,
                ?string $versionChangeSummary = null
            ): array {
                return [];
            }
        },
        new class implements ProductWorkflowReadRepositoryInterface {
            public function listHistory(string $productUuid, int $limit = 50): array
            {
                return [];
            }
        },
        buildProductRead('approved'),
        new WorkflowCommentService(
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface {
                public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array
                {
                    return [];
                }
            },
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentWriteRepositoryInterface {
                public function add(
                    string $entityType,
                    int $entityId,
                    string $entityUuid,
                    string $workflowAction,
                    ?string $fromStatus,
                    ?string $toStatus,
                    string $comment,
                    ?int $commentedBy
                ): string {
                    return 'c1';
                }
            },
            buildProductRead('approved'),
            new WorkflowPolicy($guard),
            buildAuditEventService()
        ),
        buildCompletenessService(),
        buildSnapshotBuilder(),
        new WorkflowPolicy($guard),
        new \Rateb\PlatformCatalog\Application\Services\ConcurrencyService(),
        buildAuditEventService(),
        new \Rateb\PlatformCatalog\Application\Events\EventDispatcher()
    );

    try {
        $service->history('a1111111-1111-4111-8111-111111111111');
        throw new RuntimeException('Expected forbidden');
    } catch (\RuntimeException $e) {
        catalog_assert_same(403, (int) $e->getCode());
    }
});
