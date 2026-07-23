<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Persistence port for renewal history, lifecycle audit, and engine reactivation.
 */
interface RenewalStore
{
    public function reactivateEngineRow(
        int $companyId,
        string $newExpiryYmd,
        string $todayYmd
    ): bool;

    public function insertHistory(
        int $companyId,
        ?string $previousExpiry,
        string $newExpiry,
        string $period,
        int $actorId,
        ?string $reference
    ): int;

    public function insertLifecycleAudit(
        int $companyId,
        string $action,
        string $oldStatus,
        string $newStatus,
        int $actorId
    ): int;
}
