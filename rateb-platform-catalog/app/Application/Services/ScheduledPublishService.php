<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Contracts\StructuredLoggerInterface;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductScheduledArchive;
use Rateb\PlatformCatalog\Application\Events\ProductScheduledPublish;
use Rateb\PlatformCatalog\Application\Support\SystemActorContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishWriteRepositoryInterface;

final class ScheduledPublishService
{
    public function __construct(
        private readonly ScheduledPublishReadRepositoryInterface $readRepository,
        private readonly ScheduledPublishWriteRepositoryInterface $writeRepository,
        private readonly WorkflowService $workflowService,
        private readonly CompletenessService $completenessService,
        private readonly AuditEventService $auditEventService,
        private readonly EventDispatcher $events,
        private readonly StructuredLoggerInterface $logger
    ) {
    }

    public function processDue(): void
    {
        foreach ($this->readRepository->listDuePublish() as $row) {
            $this->processPublishRow($row);
        }

        foreach ($this->readRepository->listDueArchive() as $row) {
            $this->processArchiveRow($row);
        }
    }

    /**
     * @param array{uuid: string, lock_version: int, publish_at: string} $row
     */
    private function processPublishRow(array $row): void
    {
        $productUuid = (string) $row['uuid'];
        $publishAt = (string) $row['publish_at'];

        try {
            $result = SystemActorContext::runAsSystem(function () use ($productUuid, $row): array {
                return $this->workflowService->publish($productUuid, [
                    'lock_version' => (int) $row['lock_version'],
                    'actor_id' => SystemActorContext::SYSTEM_USER_ID,
                    'comment' => 'Scheduled publish',
                ]);
            });

            $this->writeRepository->clearPublishAt($productUuid);
            $this->completenessService->recalculateForProductUuid($productUuid);

            $this->auditEventService->record(
                'product',
                $productUuid,
                'scheduled_publish',
                (int) $result['version_number'],
                SystemActorContext::SYSTEM_USER_ID,
                ['publish_at' => $publishAt, 'status' => 'approved'],
                ['publish_at' => null, 'status' => 'published']
            );

            $this->events->dispatch(new ProductScheduledPublish($productUuid, (int) $result['version_number']));
        } catch (\Throwable $e) {
            $this->recordScheduledFailure('scheduled_publish_failed', $productUuid, [
                'publish_at' => $publishAt,
                'status' => 'approved',
            ], $e);
        }
    }

    /**
     * @param array{uuid: string, lock_version: int, archive_at: string} $row
     */
    private function processArchiveRow(array $row): void
    {
        $productUuid = (string) $row['uuid'];
        $archiveAt = (string) $row['archive_at'];

        try {
            $result = SystemActorContext::runAsSystem(function () use ($productUuid, $row): array {
                return $this->workflowService->archive($productUuid, [
                    'lock_version' => (int) $row['lock_version'],
                    'actor_id' => SystemActorContext::SYSTEM_USER_ID,
                    'comment' => 'Scheduled archive',
                ]);
            });

            $this->writeRepository->clearArchiveAt($productUuid);
            $this->completenessService->recalculateForProductUuid($productUuid);

            $this->auditEventService->record(
                'product',
                $productUuid,
                'scheduled_archive',
                (int) $result['version_number'],
                SystemActorContext::SYSTEM_USER_ID,
                ['archive_at' => $archiveAt, 'status' => 'published'],
                ['archive_at' => null, 'status' => 'archived']
            );

            $this->events->dispatch(new ProductScheduledArchive($productUuid));
        } catch (\Throwable $e) {
            $this->recordScheduledFailure('scheduled_archive_failed', $productUuid, [
                'archive_at' => $archiveAt,
                'status' => 'published',
            ], $e);
        }
    }

    /**
     * @param array<string, mixed> $before
     */
    private function recordScheduledFailure(
        string $action,
        string $productUuid,
        array $before,
        \Throwable $e
    ): void {
        $this->logger->error($action, [
            'product_uuid' => $productUuid,
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);

        $this->auditEventService->record(
            'product',
            $productUuid,
            $action,
            null,
            SystemActorContext::SYSTEM_USER_ID,
            $before,
            [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]
        );
    }
}
