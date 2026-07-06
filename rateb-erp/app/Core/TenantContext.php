<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class TenantContext
{
    private static ?int $companyId = null;
    private static bool $superAdmin = false;
    /** @var array<int, string>|null */
    private static ?array $apiModules = null;
    private static ?int $apiUserId = null;

    public static function setCompanyId(?int $companyId): void
    {
        self::$companyId = $companyId;
    }

    public static function companyId(): ?int
    {
        return self::$companyId;
    }

    public static function setSuperAdmin(bool $flag): void
    {
        self::$superAdmin = $flag;
    }

    public static function isSuperAdmin(): bool
    {
        return self::$superAdmin;
    }

    /** @param array<int, string>|null $modules */
    public static function setApiModules(?array $modules): void
    {
        self::$apiModules = $modules;
    }

    /** @return array<int, string>|null */
    public static function apiModules(): ?array
    {
        return self::$apiModules;
    }

    public static function setApiUserId(?int $userId): void
    {
        self::$apiUserId = ($userId !== null && $userId > 0) ? $userId : null;
    }

    public static function apiUserId(): ?int
    {
        return self::$apiUserId;
    }
}
