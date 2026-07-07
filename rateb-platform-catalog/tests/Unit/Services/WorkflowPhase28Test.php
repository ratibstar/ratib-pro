<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductPublished;
use Rateb\PlatformCatalog\Application\Events\VersionCreated;
use Rateb\PlatformCatalog\Application\Policies\ChangeRequestPolicy;
use Rateb\PlatformCatalog\Application\Policies\CompletenessPolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductPolicy;
use Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;
use Rateb\PlatformCatalog\Application\Services\AuditEventService;
use Rateb\PlatformCatalog\Application\Services\CategorySchemaService;
use Rateb\PlatformCatalog\Application\Services\ChangeRequestService;
use Rateb\PlatformCatalog\Application\Services\CompletenessService;
use Rateb\PlatformCatalog\Application\Services\ConcurrencyService;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Tests\Support\EmptyProductSeoReadRepository;
use Rateb\PlatformCatalog\Application\Services\ProductSnapshotBuilder;
use Rateb\PlatformCatalog\Application\Services\ProductVersionService;
use Rateb\PlatformCatalog\Application\Services\WorkflowCommentService;
use Rateb\PlatformCatalog\Application\Services\WorkflowGateException;
use Rateb\PlatformCatalog\Application\Services\WorkflowService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations\MigrationRunner;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessDataReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotRestoreRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVersionReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVersionWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowReadRepositoryInterface;

catalog_test('MigrationRunner normalizes workflow versioning migration', static function (): void {
    catalog_assert_same('010_workflow_versioning', MigrationRunner::normalizeMigrationKey('010_workflow_versioning.php'));
});

catalog_test('WorkflowService submits draft product for review', static function (): void {
    $counter = new stdClass();
    $counter->n = 0;
    $workflow = buildWorkflowHarness('draft', 'pending_review', 'submit', false, $counter);
    $result = $workflow['service']->submit('prod-1', ['actor_id' => 9, 'lock_version' => 1]);
    catalog_assert_same('pending_review', $result['to_status']);
    catalog_assert_same(1, $counter->n);
});

catalog_test('WorkflowService blocks approve when completeness fails', static function (): void {
    $workflow = buildWorkflowHarness('pending_review', 'approved', 'approve', blocking: true);
    try {
        $workflow['service']->approve('prod-1', ['lock_version' => 1]);
        throw new RuntimeException('Expected gate exception');
    } catch (WorkflowGateException $e) {
        catalog_assert_true($e->isBlocking());
        catalog_assert_true(in_array('name_default', $e->failedRules(), true));
    }
});

catalog_test('ProductVersionService compares snapshots', static function (): void {
    $service = buildVersionService();
    $compare = $service->compare('prod-1', 1, 2);
    catalog_assert_same(1, $compare['from_version']);
    catalog_assert_same(2, $compare['to_version']);
    catalog_assert_true(count($compare['differences']) >= 1);
});

catalog_test('ChangeRequestService creates and approves request', static function (): void {
    $events = new EventDispatcher();
    $created = false;
    $events->listen('ChangeRequestSubmitted', static function () use (&$created): void {
        $created = true;
    });

    $write = new class implements ChangeRequestWriteRepositoryInterface {
        public string $lastUuid = 'cr-1';

        public function create(
            int $productId,
            string $requestType,
            array $proposedChanges,
            int $currentVersion,
            ?int $submittedBy,
            array $items
        ): string {
            unset($productId, $requestType, $proposedChanges, $currentVersion, $submittedBy, $items);

            return $this->lastUuid;
        }

        public function assignReviewer(string $uuid, int $reviewerId): bool
        {
            return true;
        }

        public function approve(string $uuid, ?int $reviewedBy, ?string $note): bool
        {
            return true;
        }

        public function reject(string $uuid, ?int $reviewedBy, ?string $note): bool
        {
            return false;
        }

        public function markApplied(string $uuid): bool
        {
            return true;
        }

        public function applyApproved(
            string $uuid,
            string $productUuid,
            int $expectedLockVersion,
            int $expectedCurrentVersion,
            array $productData,
            array $translations,
            ?array $seoData,
            array $versionSnapshot,
            ?int $actorId
        ): array {
            unset($uuid, $productUuid, $expectedLockVersion, $expectedCurrentVersion, $productData, $translations, $seoData, $versionSnapshot, $actorId);

            return ['version_number' => 2, 'lock_version' => 2, 'version_uuid' => 'ver-1'];
        }
    };

    $read = new class($write) implements ChangeRequestReadRepositoryInterface {
        public function __construct(private ChangeRequestWriteRepositoryInterface $write)
        {
        }

        public function findByUuid(string $uuid): ?array
        {
            return [
                'id' => 1,
                'uuid' => $uuid,
                'product_uuid' => 'prod-1',
                'status' => 'pending',
                'proposed_changes' => ['sku' => 'NEW'],
            ];
        }

        public function list(?string $status = null, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listItems(int $changeRequestId): array
        {
            return [['field_path' => 'sku', 'old_value' => 'OLD', 'new_value' => 'NEW']];
        }
    };

    $guard = new TestPolicyGuard();
    $service = new ChangeRequestService(
        $read,
        $write,
        buildProductRead('draft'),
        buildSnapshotBuilder(),
        new WorkflowCommentService(
            new class implements WorkflowCommentReadRepositoryInterface {
                public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array
                {
                    return [];
                }
            },
            new class implements WorkflowCommentWriteRepositoryInterface {
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
                    return 'comment-1';
                }
            },
            buildProductRead('draft'),
            new WorkflowPolicy($guard),
            buildAuditEventService()
        ),
        new class implements WorkflowCommentReadRepositoryInterface {
            public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array
            {
                return [];
            }
        },
        new ChangeRequestPolicy($guard),
        new ConcurrencyService(),
        buildAuditEventService(),
        new LocaleResolverService(),
        $events
    );

    $item = $service->create([
        'product_uuid' => 'prod-1',
        'proposed_changes' => ['sku' => 'NEW'],
    ]);
    catalog_assert_true($created);
    catalog_assert_same('cr-1', $item['uuid']);
    catalog_assert_true($service->approve('cr-1', ['review_note' => 'ok']));
});

catalog_test('CompletenessService scores locale with missing name as blocking', static function (): void {
    $service = buildCompletenessService(blocking: true);
    $result = $service->evaluateForTransition('prod-1', 1, 10, 'pending_review', 'approved');
    catalog_assert_true($result['blocking_failed']);
});

/**
 * @return array{service: WorkflowService}
 */
function buildWorkflowHarness(
    string $from,
    string $to,
    string $action,
    bool $blocking = false,
    ?stdClass $counter = null
): array {
    if ($counter === null) {
        $counter = new stdClass();
        $counter->n = 0;
    }
    $events = new EventDispatcher();
    $events->listen('ProductPublished', static function (ProductPublished $event): void {
        catalog_assert_same('prod-1', $event->payload()['product_uuid']);
    });
    $events->listen('VersionCreated', static function (VersionCreated $event): void {
        catalog_assert_same('prod-1', $event->payload()['product_uuid']);
    });

    $workflowRead = new class($from, $to, $action) implements WorkflowReadRepositoryInterface {
        public function __construct(
            private string $from,
            private string $to,
            private string $action
        ) {
        }

        public function findTransition(string $fromStatus, string $action): ?array
        {
            if ($fromStatus === $this->from && $action === $this->action) {
                return [
                    'from_state' => $this->from,
                    'to_state' => $this->to,
                    'action' => $this->action,
                    'requires_permission' => null,
                ];
            }

            return null;
        }

        public function listStates(): array
        {
            return [];
        }
    };

    $workflowWrite = new class($counter, $to) implements ProductWorkflowWriteRepositoryInterface {
        public function __construct(
            private stdClass $counter,
            private string $to
        ) {
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
            unset($productUuid, $fromStatus, $toStatus, $lockVersion, $actorId, $comment, $versionSnapshot, $versionChangeType, $versionChangeSummary);
            $this->counter->n++;

            return [
                'history_uuid' => 'hist-1',
                'from_status' => 'draft',
                'to_status' => $this->to,
                'version_number' => 2,
                'lock_version' => 2,
                'product_id' => 1,
                'version_uuid' => $action === 'publish' ? 'ver-1' : null,
            ];
        }
    };

    $workflowHistoryRead = new class implements ProductWorkflowReadRepositoryInterface {
        public function listHistory(string $productUuid, int $limit = 50): array
        {
            return [];
        }
    };

    $guard = new TestPolicyGuard();
    $completeness = buildCompletenessService($blocking);

    $service = new WorkflowService(
        $workflowRead,
        $workflowWrite,
        $workflowHistoryRead,
        buildProductRead($from),
        new WorkflowCommentService(
            new class implements WorkflowCommentReadRepositoryInterface {
                public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array
                {
                    return [];
                }
            },
            new class implements WorkflowCommentWriteRepositoryInterface {
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
            buildProductRead($from),
            new WorkflowPolicy($guard),
            buildAuditEventService()
        ),
        $completeness,
        buildSnapshotBuilder(),
        new WorkflowPolicy($guard),
        new ConcurrencyService(),
        buildAuditEventService(),
        $events
    );

    return ['service' => $service];
}

function buildCompletenessService(bool $blocking = false): CompletenessService
{
    $guard = new TestPolicyGuard();
    $events = new EventDispatcher();

    return new CompletenessService(
        new class($blocking) implements CompletenessRuleReadRepositoryInterface {
            public function __construct(private bool $blocking)
            {
            }

            public function listActive(?string $entityType = 'product'): array
            {
                return [[
                    'code' => 'name_default',
                    'entity_type' => 'product',
                    'locale' => 'en',
                    'required_fields' => ['name'],
                    'is_blocking' => $this->blocking,
                    'weight' => 1.0,
                    'status' => 'active',
                ]];
            }

            public function listAll(): array
            {
                return $this->listActive();
            }

            public function findByCode(string $code): ?array
            {
                return null;
            }
        },
        new class implements CompletenessRuleWriteRepositoryInterface {
            public function updateByCode(string $code, array $data): bool
            {
                return true;
            }
        },
        new class implements ProductCompletenessReadRepositoryInterface {
            public function listByProductUuid(string $productUuid): array
            {
                return [];
            }

            public function findByProductAndLocale(string $productUuid, string $locale): ?array
            {
                return null;
            }
        },
        new class implements ProductCompletenessWriteRepositoryInterface {
            public function upsert(
                int $productId,
                string $locale,
                float $score,
                bool $blockingFailed,
                array $failedRules
            ): void {
            }
        },
        buildProductRead('draft'),
        new class implements CompletenessDataReadRepositoryInterface {
            public function listProductTranslationsByLocale(string $productUuid): array
            {
                return ['en' => ['language_code' => 'en', 'name' => '', 'short_description' => null, 'description' => null]];
            }

            public function listSeoTranslationsByLocale(string $productUuid): array
            {
                return [];
            }

            public function listImageTranslationsByLocale(string $productUuid): array
            {
                return [];
            }

            public function listVariantTranslationsByLocale(string $productUuid): array
            {
                return [];
            }

            public function listAttributeValuesByLocale(string $productUuid): array
            {
                return [];
            }
        },
        new CategorySchemaService(
            new class implements CategorySchemaReadRepositoryInterface {
                public function listRequiredForCategory(int $categoryId): array
                {
                    return [];
                }

                public function listResolvedSchemaForCategory(int $categoryId): array
                {
                    return [];
                }

                public function findCategoryIdByUuid(string $categoryUuid): ?int
                {
                    return null;
                }
            },
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaWriteRepositoryInterface {
                public function replaceForCategory(int $categoryId, array $items, ?int $actorId = null): void
                {
                }
            },
            new class implements CompletenessDataReadRepositoryInterface {
                public function listProductTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listSeoTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listImageTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listVariantTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listAttributeValuesByLocale(string $productUuid): array
                {
                    return [];
                }
            },
            new \Rateb\PlatformCatalog\Application\Policies\CategorySchemaPolicy($guard),
            buildAuditEventService()
        ),
        new CompletenessPolicy($guard),
        new LocaleResolverService(),
        $events,
        buildAuditEventService()
    );
}

function buildVersionService(): ProductVersionService
{
    $guard = new TestPolicyGuard();
    $events = new EventDispatcher();

    return new ProductVersionService(
        new class implements ProductVersionReadRepositoryInterface {
            public function listByProductUuid(string $productUuid, int $limit = 50): array
            {
                return [];
            }

            public function findByProductAndVersion(string $productUuid, int $versionNumber): ?array
            {
                $snapshots = [
                    1 => ['product' => ['sku' => 'A'], 'translations' => [], 'attributes' => []],
                    2 => ['product' => ['sku' => 'B'], 'translations' => [], 'attributes' => []],
                ];

                return [
                    'uuid' => 'v' . $versionNumber,
                    'version_number' => $versionNumber,
                    'snapshot' => $snapshots[$versionNumber] ?? [],
                ];
            }
        },
        new class implements ProductVersionWriteRepositoryInterface {
            public function create(
                int $productId,
                int $versionNumber,
                string $changeType,
                array $snapshot,
                int $entityVersion,
                ?string $changeSummary,
                ?int $actorId
            ): string {
                return 'ver-uuid';
            }
        },
        new class implements ProductSnapshotRestoreRepositoryInterface {
            public function restore(
                string $productUuid,
                array $snapshot,
                int $expectedLockVersion,
                ?int $actorId,
                string $changeSummary
            ): array {
                return [
                    'version_number' => 3,
                    'lock_version' => 2,
                    'product_id' => 1,
                    'version_uuid' => 'restored-ver',
                ];
            }
        },
        buildProductRead('approved'),
        buildSnapshotBuilder(),
        new ProductPolicy($guard),
        new ConcurrencyService(),
        buildAuditEventService(),
        new LocaleResolverService(),
        $events
    );
}

function buildSnapshotBuilder(): ProductSnapshotBuilder
{
    return new ProductSnapshotBuilder(
        buildProductRead('draft'),
        new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationReadRepositoryInterface {
            public function listForProduct(string $productUuid, LocaleContext $locale): array
            {
                return [];
            }
        },
        new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeReadRepositoryInterface {
            public function findByUuid(string $uuid, LocaleContext $locale): ?array
            {
                return null;
            }

            public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listByProductUuid(string $productUuid, LocaleContext $locale): array
            {
                return [];
            }

            public function listTranslationsGrouped(array $productAttributeIds): array
            {
                return [];
            }
        },
        new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationReadRepositoryInterface {
            public function findByUuid(string $uuid, LocaleContext $locale): ?array
            {
                return null;
            }

            public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listByProductUuid(string $productUuid, LocaleContext $locale): array
            {
                return [];
            }
        },
        new EmptyProductSeoReadRepository(),
        new class implements CompletenessDataReadRepositoryInterface {
            public function listProductTranslationsByLocale(string $productUuid): array
            {
                return [];
            }

            public function listSeoTranslationsByLocale(string $productUuid): array
            {
                return [];
            }

            public function listImageTranslationsByLocale(string $productUuid): array
            {
                return [];
            }

            public function listVariantTranslationsByLocale(string $productUuid): array
            {
                return [];
            }

            public function listAttributeValuesByLocale(string $productUuid): array
            {
                return [];
            }
        },
        new \Rateb\PlatformCatalog\Tests\Support\EmptyProductSnapshotGraphReadRepository()
    );
}

function buildAuditEventService(): AuditEventService
{
    return new AuditEventService(new class implements AuditEventWriteRepositoryInterface {
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
            return 'audit-1';
        }
    });
}

function buildProductRead(string $status): ProductReadRepositoryInterface
{
    return new class($status) implements ProductReadRepositoryInterface {
        public function __construct(private string $status)
        {
        }

        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return ['uuid' => $uuid, 'sku' => 'SKU', 'lock_version' => 1, 'status' => $this->status];
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
            return [
                'id' => 1,
                'uuid' => $uuid,
                'sku' => 'SKU',
                'status' => $this->status,
                'lock_version' => 1,
                'version_number' => 1,
                'category_id' => 10,
                'category_uuid' => 'cat-1',
            ];
        }
    };
}
