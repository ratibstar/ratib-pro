<?php
declare(strict_types=1);

namespace App\Accounting\EventStore;

final class AccountingEventStore
{
    public function __construct(
        private readonly AccountingEventRepository $repository = new AccountingEventRepository(),
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->repository->tableExists();
    }

    /**
     * Immutable append — payload is never updated after insert.
     *
     * @param array<string, mixed> $event Normalized gateway event
     */
    public function persistPending(array $event, string $eventUuid): ?int
    {
        return $this->repository->append(
            eventUuid: $eventUuid,
            sourceSystem: (string) $event['source_system'],
            eventType: (string) $event['event_type'],
            payload: $event,
            companyId: isset($event['company_id']) ? (int) $event['company_id'] : null,
            branchId: array_key_exists('branch_id', $event) && $event['branch_id'] !== null
                ? (int) $event['branch_id']
                : null,
            status: 'pending'
        );
    }

    public function markProcessed(string $eventUuid): bool
    {
        return $this->repository->updateStatus($eventUuid, 'processed');
    }

    public function markFailed(string $eventUuid): bool
    {
        return $this->repository->updateStatus($eventUuid, 'failed');
    }

    public function findByUuid(string $eventUuid): ?AccountingEvent
    {
        return $this->repository->findByUuid($eventUuid);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<AccountingEvent>
     */
    public function fetchForReplay(array $filters): array
    {
        return $this->repository->findByFilters($filters);
    }
}
