<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Subscription Engine — single source of truth for tenant subscription status.
 *
 * Phase 2: read-only observation of stored engine rows + date arithmetic for
 * daysRemaining / isExpired. No status transitions, notifications, billing,
 * renewal, or access enforcement.
 *
 * Isolation: never reference HR, POS, Inventory, Accounting, Payroll, CRM,
 * Procurement, Employees, Attendance, or any UI class.
 */
final class SubscriptionEngine
{
    private SubscriptionEngineStore $repository;
    private SubscriptionPolicy $policy;

    /** @var array<string, SubscriptionContext> */
    private array $contextCache = [];

    public function __construct(
        ?SubscriptionEngineStore $repository = null,
        ?SubscriptionPolicy $policy = null
    ) {
        $this->repository = $repository ?? new SubscriptionRepository();
        $this->policy = $policy ?? new SubscriptionPolicy();
    }

    /**
     * Policy collaborator reserved for future threshold phases (unused in Phase 2 reads).
     */
    public function policy(): SubscriptionPolicy
    {
        return $this->policy;
    }

    /**
     * Build immutable request snapshot (one repository read, cached per engine instance).
     */
    public function contextFor(int $companyId, ?string $todayYmd = null): SubscriptionContext
    {
        if ($companyId < 1) {
            return SubscriptionContext::absent(0);
        }

        $today = $todayYmd ?? gmdate('Y-m-d');
        $cacheKey = $companyId . ':' . $today;
        if (isset($this->contextCache[$cacheKey])) {
            return $this->contextCache[$cacheKey];
        }

        $row = $this->repository->findByCompanyId($companyId);
        $context = $row === null
            ? SubscriptionContext::absent($companyId)
            : SubscriptionContext::fromEngineRow($companyId, $row, $today);

        $this->contextCache[$cacheKey] = $context;
        return $context;
    }

    /**
     * Build context from an already-loaded engine row (batch / scheduler path).
     *
     * @param array<string, mixed> $row
     */
    public function contextFromRow(array $row, ?string $todayYmd = null): SubscriptionContext
    {
        $companyId = (int) ($row['company_id'] ?? 0);
        if ($companyId < 1) {
            return SubscriptionContext::absent(0);
        }
        $today = $todayYmd ?? gmdate('Y-m-d');
        return SubscriptionContext::fromEngineRow($companyId, $row, $today);
    }

    public function getStatus(int $companyId): string
    {
        return $this->contextFor($companyId)->status();
    }

    public function daysRemaining(int $companyId): int
    {
        return $this->contextFor($companyId)->daysRemaining();
    }

    public function isExpired(int $companyId): bool
    {
        return $this->contextFor($companyId)->isExpired();
    }

    public function isInGrace(int $companyId): bool
    {
        return $this->contextFor($companyId)->isInGrace();
    }

    public function isSuspended(int $companyId): bool
    {
        return $this->contextFor($companyId)->isSuspended();
    }

    /**
     * Advisory only — never used to block in Phase 2.
     */
    public function canAccessERP(int $companyId): bool
    {
        return $this->contextFor($companyId)->canAccessERP();
    }
}
