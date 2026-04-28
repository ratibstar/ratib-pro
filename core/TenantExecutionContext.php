<?php
/**
 * EN: Handles core framework/runtime behavior in `core/TenantExecutionContext.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/TenantExecutionContext.php`.
 */

/**
 * TenantExecutionContext
 *
 * Central request-lifecycle tenant identity holder.
 * - Locks tenant once set (prevents mid-request switching).
 * - Supports explicit system context bypass.
 */
final class TenantExecutionContext
{
    private static ?int $tenantId = null;
    private static bool $systemContext = false;
    private static bool $initialized = false;
    private static bool $locked = false;
    private static bool $resolvedFromLegacy = false;

    /**
     * Initialize or update context safely.
     * If tenant is already set, any different tenant id causes RuntimeException.
     */
    public static function initialize(?int $tenantId, bool $systemContext = false, bool $resolvedFromLegacy = false): void
    {
        $normalizedTenantId = ($tenantId !== null && $tenantId > 0) ? (int) $tenantId : null;

        if (self::$locked && self::$tenantId !== null && $normalizedTenantId !== null && self::$tenantId !== $normalizedTenantId) {
            throw new RuntimeException(
                'TenantExecutionContext: tenant context is locked (attempted switch from ' .
                self::$tenantId . ' to ' . $normalizedTenantId . ')'
            );
        }

        if (self::$tenantId !== null && $normalizedTenantId !== null && self::$tenantId !== $normalizedTenantId) {
            throw new RuntimeException(
                'TenantExecutionContext: attempted tenant switch from ' .
                self::$tenantId . ' to ' . $normalizedTenantId
            );
        }

        if (self::$tenantId === null && $normalizedTenantId !== null) {
            self::$tenantId = $normalizedTenantId;
        }

        if ($systemContext) {
            self::$systemContext = true;
        }
        if ($resolvedFromLegacy && self::$tenantId !== null) {
            self::$resolvedFromLegacy = true;
        }

        self::$initialized = true;
    }

    /**
     * Lock tenant identity for this request lifecycle.
     */
    public static function lock(): void
    {
        self::$locked = true;
        self::$initialized = true;
    }

    /**
     * Endpoint classification can mark request as system context.
     * This never modifies tenant identity.
     */
    public static function markSystemContext(bool $isSystemContext): void
    {
        if ($isSystemContext) {
            self::$systemContext = true;
        }
        self::$initialized = true;
    }

    /**
     * Backward-compatible helper used across control-panel code.
     */
    public static function setTenant(int $tenantId): void
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new RuntimeException('TenantExecutionContext: invalid tenant id.');
        }
        self::initialize($tenantId, false, false);
    }

    /**
     * Backward-compatible helper used across control-panel code.
     */
    public static function setSystemContext(): void
    {
        self::initialize(self::$tenantId, true, self::$resolvedFromLegacy);
    }

    public static function getTenantId(): ?int
    {
        return self::$tenantId;
    }

    /**
     * Require tenant id for tenant-scoped execution.
     * System context may bypass tenant requirement.
     */
    public static function requireTenantId(): int
    {
        if (self::$systemContext) {
            throw new RuntimeException('TenantExecutionContext: tenant id is not required in system context.');
        }
        if (self::$tenantId === null || self::$tenantId <= 0) {
            throw new RuntimeException('TenantExecutionContext: missing tenant context.');
        }
        return self::$tenantId;
    }

    public static function isSystemContext(): bool
    {
        return self::$systemContext;
    }

    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    public static function isLocked(): bool
    {
        return self::$locked;
    }

    public static function wasResolvedFromLegacy(): bool
    {
        return self::$resolvedFromLegacy;
    }
}

