<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Access-guard API for subscription status.
 *
 * Phase 2: thin read-only delegates to SubscriptionEngine.
 * Still NOT connected to login, middleware, page blocking, or redirects.
 */
final class SubscriptionGuard
{
    private SubscriptionEngine $engine;

    public function __construct(?SubscriptionEngine $engine = null)
    {
        $this->engine = $engine ?? new SubscriptionEngine();
    }

    public function assertCanAccess(int $companyId): bool
    {
        return $this->engine->canAccessERP($companyId);
    }

    public function shouldDenyAccess(int $companyId): bool
    {
        return !$this->engine->canAccessERP($companyId);
    }

    public function shouldWarn(int $companyId): bool
    {
        $status = $this->engine->getStatus($companyId);
        return $status === SubscriptionStatus::WARNING
            || $status === SubscriptionStatus::CRITICAL;
    }

    public function currentStatus(int $companyId): string
    {
        return $this->engine->getStatus($companyId);
    }
}
