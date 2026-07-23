<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Persistence boundary for Subscription Engine state.
 *
 * Owns reads/writes against dedicated engine tables only
 * (see migrations/210_subscription_engine_foundation.sql).
 *
 * MUST NOT:
 * - read or write rateb_subscriptions / rateb_plans / rateb_payments / rateb_invoices
 * - reference HR, POS, Inventory, Accounting, Payroll, CRM, Procurement
 * - perform status transitions or notifications
 *
 * Phase 1: contracts only — no SQL, no PDO, no business logic.
 */
final class SubscriptionRepository
{
    /**
     * Load engine row for a tenant company, or null if none.
     *
     * @return array<string, mixed>|null
     * @throws \LogicException Phase 1 — not implemented
     */
    public function findByCompanyId(int $companyId): ?array
    {
        throw new \LogicException(
            'SubscriptionRepository::findByCompanyId() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Persist engine state for a tenant. Shape is defined by the engine schema.
     *
     * @param array<string, mixed> $state
     * @throws \LogicException Phase 1 — not implemented
     */
    public function save(int $companyId, array $state): void
    {
        throw new \LogicException(
            'SubscriptionRepository::save() is not implemented in Phase 1 foundation.'
        );
    }

    /**
     * Current status string for a tenant, or null if no engine row.
     *
     * @throws \LogicException Phase 1 — not implemented
     */
    public function getCurrentStatus(int $companyId): ?string
    {
        throw new \LogicException(
            'SubscriptionRepository::getCurrentStatus() is not implemented in Phase 1 foundation.'
        );
    }
}
