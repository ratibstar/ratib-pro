<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core;

/**
 * Request-scoped tenant isolation for multi-tenant IVR runtime.
 */
final class TenantContext
{
    private static ?int $tenantId = null;
    private static ?int $erpCompanyId = null;
    private static string $locale = 'ar';

    public static function set(int $tenantId, ?int $erpCompanyId = null, string $locale = 'ar'): void
    {
        self::$tenantId = $tenantId;
        self::$erpCompanyId = $erpCompanyId;
        self::$locale = in_array($locale, ['en', 'ar'], true) ? $locale : 'ar';
    }

    public static function tenantId(): ?int
    {
        return self::$tenantId;
    }

    public static function erpCompanyId(): ?int
    {
        return self::$erpCompanyId;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function requireTenantId(): int
    {
        if (self::$tenantId === null || self::$tenantId < 1) {
            throw new \RuntimeException('Tenant context is not set.');
        }
        return self::$tenantId;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$erpCompanyId = null;
        self::$locale = 'ar';
    }
}
