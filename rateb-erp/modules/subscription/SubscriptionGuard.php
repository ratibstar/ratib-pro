<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Access-guard API for subscription status.
 *
 * Phase 1: method surface only.
 *
 * MUST NOT (this phase and until explicitly approved):
 * - connect to login
 * - connect to middleware
 * - block pages
 * - perform redirects
 * - send notifications
 *
 * Callers in later phases may ask the guard; the guard asks the Engine.
 * Nothing outside this module should implement subscription rules.
 *
 * Isolation: this class must never reference HR, POS, Inventory, Accounting,
 * Payroll, CRM, Procurement, Employees, Attendance, or any UI class.
 */
final class SubscriptionGuard
{
    private SubscriptionEngine $engine;

    public function __construct(?SubscriptionEngine $engine = null)
    {
        $this->engine = $engine ?? new SubscriptionEngine();
    }

    /**
     * True when the tenant may use the ERP application surface.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function assertCanAccess(int $companyId): bool
    {
        throw new \LogicException(
            'SubscriptionGuard::assertCanAccess() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * True when access should be denied (suspended / expired beyond grace, etc.).
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function shouldDenyAccess(int $companyId): bool
    {
        throw new \LogicException(
            'SubscriptionGuard::shouldDenyAccess() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * True when the tenant is in a non-blocking warning/critical window.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function shouldWarn(int $companyId): bool
    {
        throw new \LogicException(
            'SubscriptionGuard::shouldWarn() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Expose underlying engine status without applying side effects.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function currentStatus(int $companyId): string
    {
        throw new \LogicException(
            'SubscriptionGuard::currentStatus() is not implemented in Phase 1 foundation.'
        );
    }
}
