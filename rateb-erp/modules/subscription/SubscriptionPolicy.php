<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Policy surface for subscription lifecycle thresholds and access rules.
 *
 * Phase 1 foundation: method contracts only. No threshold evaluation,
 * no billing rules, no auto-renewal, no license checks.
 *
 * Future extension points:
 * - warning / critical day thresholds
 * - grace-period interpretation
 * - access allow/deny matrices by status
 *
 * Isolation: this class must never reference HR, POS, Inventory, Accounting,
 * Payroll, CRM, Procurement, Employees, Attendance, or any UI class.
 */
final class SubscriptionPolicy
{
    /**
     * Days remaining at/below which status is WARNING.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function warningThresholdDays(): int
    {
        throw new \LogicException(
            'SubscriptionPolicy::warningThresholdDays() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Days remaining at/below which status is CRITICAL.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function criticalThresholdDays(): int
    {
        throw new \LogicException(
            'SubscriptionPolicy::criticalThresholdDays() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Whether the given status may access the ERP application surface.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function allowsErpAccess(string $status): bool
    {
        throw new \LogicException(
            'SubscriptionPolicy::allowsErpAccess() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Whether the given status is considered within grace.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function isGraceStatus(string $status): bool
    {
        throw new \LogicException(
            'SubscriptionPolicy::isGraceStatus() is not implemented in Phase 1 foundation.'
        );
    }
}
