<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Read-only tenant bootstrap for SubscriptionContext.
 *
 * Loads Engine state early; never redirects, blocks, notifies, or alters auth.
 * Failures are swallowed so ERP behavior stays unchanged if the table is missing.
 */
final class SubscriptionBootstrap
{
    /**
     * Bind SubscriptionContext for the active tenant company.
     * Pass null/0 to clear (guest / no tenant).
     */
    public static function bindForCompany(?int $companyId): void
    {
        if ($companyId === null || $companyId < 1) {
            SubscriptionRuntime::reset();
            return;
        }

        try {
            $engine = new SubscriptionEngine();
            SubscriptionRuntime::bind($engine->contextFor($companyId));
        } catch (\Throwable $e) {
            error_log('RATEB subscription bootstrap: ' . $e->getMessage());
            SubscriptionRuntime::bind(SubscriptionContext::absent($companyId));
        }
    }

    /**
     * Ensure a context exists for TenantContext company when accessors are used
     * before explicit bootstrap (e.g. late API company bind).
     */
    public static function ensureFromTenantContext(): void
    {
        if (!class_exists(\Rateb\App\Core\TenantContext::class, false)
            && !class_exists(\Rateb\App\Core\TenantContext::class)) {
            return;
        }

        $companyId = \Rateb\App\Core\TenantContext::companyId();
        $companyIdInt = $companyId !== null ? (int) $companyId : 0;

        if ($companyIdInt < 1) {
            if (SubscriptionRuntime::isBound()) {
                SubscriptionRuntime::reset();
            }
            return;
        }

        $current = SubscriptionRuntime::get();
        if (SubscriptionRuntime::isBound()
            && $current !== null
            && $current->companyId() === $companyIdInt) {
            return;
        }

        self::bindForCompany($companyIdInt);
    }
}
