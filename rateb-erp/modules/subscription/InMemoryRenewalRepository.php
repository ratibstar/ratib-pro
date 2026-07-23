<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * In-memory renewal persistence for unit tests (no DB / no payments).
 */
final class InMemoryRenewalRepository implements RenewalStore
{
    private InMemorySubscriptionEngineStore $engine;

    /** @var list<array<string, mixed>> */
    private array $history = [];

    /** @var list<array<string, mixed>> */
    private array $audits = [];

    private int $historySeq = 0;
    private int $auditSeq = 0;

    public function __construct(InMemorySubscriptionEngineStore $engine)
    {
        $this->engine = $engine;
    }

    public function reactivateEngineRow(
        int $companyId,
        string $newExpiryYmd,
        string $todayYmd
    ): bool {
        unset($todayYmd);
        return $this->engine->patchByCompanyId($companyId, [
            'subscription_end' => $newExpiryYmd,
            'current_status' => SubscriptionStatus::ACTIVE,
            'suspended_at' => null,
            'renewed_at' => gmdate('Y-m-d H:i:s'),
            'grace_started_at' => null,
            'grace_end_at' => null,
        ]);
    }

    public function insertHistory(
        int $companyId,
        ?string $previousExpiry,
        string $newExpiry,
        string $period,
        int $actorId,
        ?string $reference
    ): int {
        $this->historySeq++;
        $this->history[] = [
            'id' => $this->historySeq,
            'company_id' => $companyId,
            'previous_expiry_date' => $previousExpiry,
            'new_expiry_date' => $newExpiry,
            'period' => $period,
            'actor_id' => $actorId,
            'reference' => $reference,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        return $this->historySeq;
    }

    public function insertLifecycleAudit(
        int $companyId,
        string $action,
        string $oldStatus,
        string $newStatus,
        int $actorId
    ): int {
        $this->auditSeq++;
        $this->audits[] = [
            'id' => $this->auditSeq,
            'company_id' => $companyId,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'actor_id' => $actorId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        return $this->auditSeq;
    }

    /** @return list<array<string, mixed>> */
    public function history(): array
    {
        return $this->history;
    }

    /** @return list<array<string, mixed>> */
    public function audits(): array
    {
        return $this->audits;
    }
}
