<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Contracts\StructuredLoggerInterface;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;
use Rateb\PlatformCatalog\Application\Services\AuditEventService;
use Rateb\PlatformCatalog\Application\Services\ConcurrencyService;
use Rateb\PlatformCatalog\Application\Services\ScheduledPublishService;
use Rateb\PlatformCatalog\Application\Services\WorkflowService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowReadRepositoryInterface;

catalog_test('ScheduledPublishService records failure audit and log without stopping batch', static function (): void {
    $logged = [];
    $audited = [];
    $cleared = false;

    $logger = new class($logged) implements StructuredLoggerInterface {
        /** @param list<array<string, mixed>> $logged */
        public function __construct(private array &$logged)
        {
        }

        public function error(string $message, array $context = []): void
        {
            $this->logged[] = ['message' => $message, 'context' => $context];
        }
    };

    $audit = new AuditEventService(new class($audited) implements AuditEventWriteRepositoryInterface {
        /** @param list<array<string, mixed>> $audited */
        public function __construct(private array &$audited)
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
            $this->audited[] = [
                'entityType' => $entityType,
                'entityUuid' => $entityUuid,
                'action' => $action,
                'before' => $before,
                'after' => $after,
            ];

            return 'audit-fail-1';
        }
    });

    $productUuid = 'prod-fail-0000-4000-8000-000000000301';

    $workflow = new WorkflowService(
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
                throw new RuntimeException('simulated scheduled publish failure', 500);
            }
        },
        new class implements ProductWorkflowReadRepositoryInterface {
            public function listHistory(string $productUuid, int $limit = 50): array
            {
                return [];
            }
        },
        buildProductRead('approved'),
        buildWorkflowCommentServiceForScheduledPublish(new TestPolicyGuard()),
        buildCompletenessService(),
        buildSnapshotBuilder(),
        new WorkflowPolicy(new TestPolicyGuard()),
        new ConcurrencyService(),
        buildAuditEventService(),
        new EventDispatcher()
    );

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
        $workflow,
        buildCompletenessService(),
        $audit,
        new EventDispatcher(),
        $logger
    );

    $service->processDue();

    catalog_assert_false($cleared, 'publish_at must remain when publish fails');
    catalog_assert_same(1, count($logged));
    catalog_assert_same('scheduled_publish_failed', $logged[0]['message']);
    catalog_assert_same($productUuid, $logged[0]['context']['product_uuid']);
    catalog_assert_same(1, count($audited));
    catalog_assert_same('scheduled_publish_failed', $audited[0]['action']);
    catalog_assert_same($productUuid, $audited[0]['entityUuid']);
});

function buildWorkflowCommentServiceForScheduledPublish(TestPolicyGuard $guard): \Rateb\PlatformCatalog\Application\Services\WorkflowCommentService
{
    return new \Rateb\PlatformCatalog\Application\Services\WorkflowCommentService(
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
    );
}
