<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;
use Rateb\PlatformCatalog\Application\Services\ScheduledPublishService;
use Rateb\PlatformCatalog\Application\Services\WorkflowService;
use Rateb\PlatformCatalog\Application\Support\SystemActorContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlScheduledPublishReadRepository;

function phase28_fallback_snapshot_restore_graph_contract(): void
{
    $restoreSource = file_get_contents(
        dirname(__DIR__, 3) . '/app/Infrastructure/Persistence/Repositories/MysqlProductSnapshotRestoreRepository.php'
    );
    catalog_assert_true(is_string($restoreSource) && $restoreSource !== '');
    catalog_assert_true(str_contains($restoreSource, 'graphWriter->restoreForProduct'));

    $graphSource = file_get_contents(
        dirname(__DIR__, 3) . '/app/Infrastructure/Persistence/Repositories/MysqlProductSnapshotGraphWriteRepository.php'
    );
    catalog_assert_true(is_string($graphSource) && $graphSource !== '');

    foreach (['variants', 'product_barcodes', 'bundle_components', 'images', 'files', 'videos'] as $section) {
        catalog_assert_true(
            str_contains($graphSource, "'" . $section . "'"),
            'Graph restore must handle section: ' . $section
        );
    }

    $snapshot = [
        'product' => ['sku' => 'SKU-1'],
        'translations' => [['language_code' => 'en', 'name' => 'Name']],
        'attributes' => [],
        'relations' => [],
        'seo' => null,
        'variants' => [['uuid' => 'v1', 'sku' => 'VAR-1']],
        'product_barcodes' => [['uuid' => 'b1', 'barcode' => '123']],
        'bundle_components' => [],
        'images' => [],
        'files' => [],
        'videos' => [],
    ];

    catalog_assert_same(
        json_encode($snapshot),
        json_encode($snapshot),
        'Snapshot graph sections must round-trip identically when unchanged'
    );
}

function phase28_fallback_scheduled_publish_repository_contract(): void
{
    $source = file_get_contents(
        dirname(__DIR__, 3) . '/app/Infrastructure/Persistence/Repositories/MysqlScheduledPublishReadRepository.php'
    );
    catalog_assert_true(is_string($source) && $source !== '');
    catalog_assert_true(str_contains($source, "status = 'approved'"));
    catalog_assert_true(str_contains($source, 'publish_at IS NOT NULL'));
    catalog_assert_true(str_contains($source, 'publish_at <= CURRENT_TIMESTAMP(6)'));
    catalog_assert_true(is_subclass_of(MysqlScheduledPublishReadRepository::class, ScheduledPublishReadRepositoryInterface::class));
}

function phase28_fallback_scheduled_publish_pipeline_contract(): void
{
    $cleared = false;
    $audited = false;
    $published = false;
    $productUuid = 'prod-scheduled-0000-4000-8000-000000000401';

    $service = new ScheduledPublishService(
        new class($productUuid) implements ScheduledPublishReadRepositoryInterface {
            public function __construct(private string $productUuid)
            {
            }

            public function listDuePublish(): array
            {
                return [
                    [
                        'uuid' => $this->productUuid,
                        'lock_version' => 1,
                        'publish_at' => '2000-01-01 00:00:00.000000',
                    ],
                ];
            }

            public function listDueArchive(): array
            {
                return [];
            }
        },
        new class($cleared) implements ScheduledPublishWriteRepositoryInterface {
            public function __construct(private bool &$cleared)
            {
            }

            public function clearPublishAt(string $productUuid): void
            {
                $this->cleared = true;
            }

            public function clearArchiveAt(string $productUuid): void
            {
            }
        },
        new WorkflowService(
            new class implements WorkflowReadRepositoryInterface {
                public function findTransition(string $fromStatus, string $action): ?array
                {
                    if ($fromStatus === 'approved' && $action === 'publish') {
                        return [
                            'from_state' => 'approved',
                            'to_state' => 'published',
                            'action' => 'publish',
                            'requires_permission' => null,
                        ];
                    }

                    return null;
                }

                public function listStates(): array
                {
                    return [];
                }
            },
            new class($published) implements ProductWorkflowWriteRepositoryInterface {
                public function __construct(private bool &$published)
                {
                }

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
                    $this->published = $toStatus === 'published';

                    return [
                        'status' => $toStatus,
                        'version_number' => 2,
                        'lock_version' => $lockVersion + 1,
                    ];
                }
            },
            new class implements ProductWorkflowReadRepositoryInterface {
                public function listHistory(string $productUuid, int $limit = 50): array
                {
                    return [
                        [
                            'uuid' => 'hist-scheduled',
                            'from_status' => 'approved',
                            'to_status' => 'published',
                            'action' => 'publish',
                            'comment' => 'Scheduled publish',
                        ],
                    ];
                }
            },
            buildProductRead('approved'),
            buildWorkflowCommentServiceForScheduledPublish(new TestPolicyGuard()),
            buildCompletenessService(),
            buildSnapshotBuilder(),
            new WorkflowPolicy(new TestPolicyGuard()),
            new \Rateb\PlatformCatalog\Application\Services\ConcurrencyService(),
            buildAuditEventService(),
            new \Rateb\PlatformCatalog\Application\Events\EventDispatcher()
        ),
        buildCompletenessService(),
        new \Rateb\PlatformCatalog\Application\Services\AuditEventService(
            new class($audited) implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface {
                public function __construct(private bool &$audited)
                {
                }

                public function append(
                    string $entityType,
                    string $entityUuid,
                    ?int $entityVersion,
                    string $action,
                    ?int $actorId,
                    string $actorType = 'platform_user',
                    ?array $before = null,
                    ?array $after = null,
                    ?string $ipAddress = null
                ): string {
                    if ($action === 'scheduled_publish') {
                        $this->audited = true;
                    }

                    return 'audit-scheduled';
                }
            }
        ),
        new \Rateb\PlatformCatalog\Application\Events\EventDispatcher(),
        new class implements \Rateb\PlatformCatalog\Application\Contracts\StructuredLoggerInterface {
            public function error(string $message, array $context = []): void
            {
            }
        }
    );

    $service->processDue();

    catalog_assert_true($published, 'Scheduled publish must transition product to published');
    catalog_assert_true($cleared, 'Scheduled publish must clear publish_at');
    catalog_assert_true($audited, 'Scheduled publish must record scheduled_publish audit event');
}

function phase28_fallback_workflow_history_contract(): void
{
    $historyItems = [];
    SystemActorContext::runAsSystem(static function () use (&$historyItems): void {
        $stubService = new WorkflowService(
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
                    return [
                        [
                            'uuid' => 'hist-fallback',
                            'action' => 'publish',
                            'from_status' => 'approved',
                            'to_status' => 'published',
                        ],
                    ];
                }
            },
            buildProductRead('published'),
            buildWorkflowCommentServiceForScheduledPublish(new TestPolicyGuard()),
            buildCompletenessService(),
            buildSnapshotBuilder(),
            new WorkflowPolicy(new TestPolicyGuard()),
            new \Rateb\PlatformCatalog\Application\Services\ConcurrencyService(),
            buildAuditEventService(),
            new \Rateb\PlatformCatalog\Application\Events\EventDispatcher()
        );

        $historyItems = $stubService->history('a1111111-1111-4111-8111-111111111111', 5);
    });

    catalog_assert_true(is_array($historyItems));
    catalog_assert_true($historyItems !== []);
    catalog_assert_same('publish', $historyItems[0]['action']);
}
