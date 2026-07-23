<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Request-scoped binder for the immutable SubscriptionContext.
 *
 * Not a global bag of mutable subscription fields — only holds one immutable
 * snapshot (or null) for the current request, matching BranchContext/TenantContext
 * request-scope style without a DI container.
 *
 * Write via SubscriptionBootstrap only. Callers read through subscription().
 */
final class SubscriptionRuntime
{
    private static ?SubscriptionContext $context = null;
    private static bool $bound = false;

    public static function bind(?SubscriptionContext $context): void
    {
        self::$context = $context;
        self::$bound = true;
    }

    public static function get(): ?SubscriptionContext
    {
        return self::$context;
    }

    public static function isBound(): bool
    {
        return self::$bound;
    }

    public static function reset(): void
    {
        self::$context = null;
        self::$bound = false;
    }
}
