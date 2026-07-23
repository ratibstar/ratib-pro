<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Request-scoped cache for the current subscription alert (at most one history query).
 */
final class SubscriptionAlertRuntime
{
    private static bool $resolved = false;
    private static ?SubscriptionAlertViewModel $alert = null;

    public static function get(): ?SubscriptionAlertViewModel
    {
        return self::$alert;
    }

    public static function isResolved(): bool
    {
        return self::$resolved;
    }

    public static function set(?SubscriptionAlertViewModel $alert): void
    {
        self::$alert = $alert;
        self::$resolved = true;
    }

    public static function reset(): void
    {
        self::$alert = null;
        self::$resolved = false;
    }
}
