<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class TenantContext
{
    private static ?int $companyId = null;
    private static bool $superAdmin = false;

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
}
