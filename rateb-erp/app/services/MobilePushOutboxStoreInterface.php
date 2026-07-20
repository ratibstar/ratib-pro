<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Persistence port for push outbox (testable without DB).
 */
interface MobilePushOutboxStoreInterface
{
    /**
     * @param array<string,mixed> $row
     * @return int inserted id, or 0 if duplicate skipped
     */
    public function insertIgnoreDuplicate(array $row): int;

    /**
     * @return list<array<string,mixed>>
     */
    public function claimPending(int $limit): array;

    /** @param array<string,mixed> $patch */
    public function update(int $id, array $patch): void;

    public function findById(int $id): ?array;

    public function countByNotification(int $notificationId): int;
}
