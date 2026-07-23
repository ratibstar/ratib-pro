<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Subscription Engine — single source of truth for tenant subscription status.
 *
 * Public API surface (Phase 1 contracts only):
 * - getStatus()
 * - daysRemaining()
 * - isExpired()
 * - isInGrace()
 * - isSuspended()
 * - canAccessERP()
 *
 * Out of scope (all phases until explicitly approved):
 * UI, redirects, notifications, cron, billing, payment gateways,
 * auto-renewal, subscription pages, license logic.
 *
 * Isolation: this class must never reference HR, POS, Inventory, Accounting,
 * Payroll, CRM, Procurement, Employees, Attendance, or any UI class.
 */
final class SubscriptionEngine
{
    private SubscriptionRepository $repository;
    private SubscriptionPolicy $policy;

    public function __construct(
        ?SubscriptionRepository $repository = null,
        ?SubscriptionPolicy $policy = null
    ) {
        $this->repository = $repository ?? new SubscriptionRepository();
        $this->policy = $policy ?? new SubscriptionPolicy();
    }

    /**
     * Canonical current status for the tenant (see SubscriptionStatus).
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function getStatus(int $companyId): string
    {
        throw new \LogicException(
            'SubscriptionEngine::getStatus() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Whole days remaining until subscription_end (may be negative when expired).
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function daysRemaining(int $companyId): int
    {
        throw new \LogicException(
            'SubscriptionEngine::daysRemaining() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Whether the subscription period has ended (before/without grace handling).
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function isExpired(int $companyId): bool
    {
        throw new \LogicException(
            'SubscriptionEngine::isExpired() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Whether the tenant is currently in grace.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function isInGrace(int $companyId): bool
    {
        throw new \LogicException(
            'SubscriptionEngine::isInGrace() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Whether the tenant is suspended.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function isSuspended(int $companyId): bool
    {
        throw new \LogicException(
            'SubscriptionEngine::isSuspended() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Whether ERP application access is allowed for this tenant.
     * Advisory only in Phase 1 — no middleware/login wiring.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function canAccessERP(int $companyId): bool
    {
        throw new \LogicException(
            'SubscriptionEngine::canAccessERP() is not implemented in Phase 1 foundation.'
        );
    }
}
