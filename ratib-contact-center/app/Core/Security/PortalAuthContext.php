<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Security;

/** Customer self-service portal auth — separate from agent AuthContext. */
final class PortalAuthContext
{
    private static ?int $portalUserId = null;
    private static ?int $tenantId = null;
    private static ?int $contactId = null;
    private static ?string $sessionToken = null;

    public static function set(int $tenantId, int $portalUserId, int $contactId, string $sessionToken): void
    {
        self::$tenantId = $tenantId;
        self::$portalUserId = $portalUserId;
        self::$contactId = $contactId;
        self::$sessionToken = $sessionToken;
    }

    public static function clear(): void
    {
        self::$portalUserId = null;
        self::$tenantId = null;
        self::$contactId = null;
        self::$sessionToken = null;
    }

    public static function isAuthenticated(): bool
    {
        return self::$portalUserId !== null && self::$portalUserId > 0 && self::$tenantId !== null && self::$tenantId > 0;
    }

    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            throw new \RuntimeException('Portal authentication required.', 401);
        }
    }

    public static function tenantId(): int
    {
        self::requireAuth();
        return (int) self::$tenantId;
    }

    public static function portalUserId(): int
    {
        self::requireAuth();
        return (int) self::$portalUserId;
    }

    public static function contactId(): int
    {
        self::requireAuth();
        return (int) self::$contactId;
    }
}
