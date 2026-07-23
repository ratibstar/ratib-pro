<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * History persistence port — DB or in-memory for tests / future stores.
 */
interface NotificationHistoryStore
{
    /** @return list<array<string, mixed>> */
    public function listByCompanyId(int $companyId, int $limit = 50): array;

    /** @return array<string, mixed>|null */
    public function findLastByCompanyId(int $companyId): ?array;

    public function existsForTrigger(int $companyId, string $notificationType, int $triggerDay): bool;

    public function recordGenerated(NotificationDecision $decision): int;
}
