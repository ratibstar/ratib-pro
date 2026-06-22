<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Security;

/**
 * Request-scoped authenticated agent context (never trust client-supplied tenant/agent IDs).
 */
final class AuthContext
{
    private static ?int $userId = null;
    private static ?int $tenantId = null;
    private static ?int $agentId = null;
    /** @var list<string> */
    private static array $permissions = [];
    private static ?string $sessionToken = null;

    public static function set(int $tenantId, int $agentId, ?int $userId = null, array $permissions = [], ?string $sessionToken = null): void
    {
        self::$tenantId = $tenantId;
        self::$agentId = $agentId;
        self::$userId = $userId;
        self::$permissions = $permissions;
        self::$sessionToken = $sessionToken;
    }

    public static function clear(): void
    {
        self::$userId = null;
        self::$tenantId = null;
        self::$agentId = null;
        self::$permissions = [];
        self::$sessionToken = null;
    }

    public static function isAuthenticated(): bool
    {
        if (self::$tenantId === null || self::$tenantId < 1) {
            return false;
        }
        if (self::$agentId !== null && self::$agentId > 0) {
            return true;
        }
        return self::can('rcc.ops.view');
    }

    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            throw new \RuntimeException('Authentication required.', 401);
        }
    }

    public static function tenantId(): int
    {
        if (self::$tenantId === null || self::$tenantId < 1) {
            throw new \RuntimeException('Authentication required.', 401);
        }
        return (int) self::$tenantId;
    }

    public static function agentId(): int
    {
        self::requireAuth();
        if (self::$agentId === null || self::$agentId < 1) {
            throw new \RuntimeException('Agent context required.', 403);
        }
        return (int) self::$agentId;
    }

    public static function agentIdOrZero(): int
    {
        return self::$agentId !== null && self::$agentId > 0 ? (int) self::$agentId : 0;
    }

    public static function userId(): ?int
    {
        return self::$userId;
    }

    public static function sessionToken(): ?string
    {
        return self::$sessionToken;
    }

    /** @return list<string> */
    public static function permissions(): array
    {
        return self::$permissions;
    }

    public static function can(string $permission): bool
    {
        return in_array($permission, self::$permissions, true);
    }

    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            throw new \RuntimeException('Permission denied: ' . $permission, 403);
        }
    }
}
