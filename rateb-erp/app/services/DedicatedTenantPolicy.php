<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;

final class DedicatedTenantPolicy
{
    public static function isDedicated(): bool
    {
        return function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment();
    }

    public static function companyCount(): int
    {
        try {
            return (new Company())->count([]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function canCreateCompany(): bool
    {
        if (!self::isDedicated()) {
            return true;
        }

        return self::companyCount() < 1;
    }

    public static function assertCanCreateCompany(): void
    {
        if (!self::canCreateCompany()) {
            throw new \RuntimeException(__('erp_dedicated_single_company'));
        }
    }

    public static function allowsPublicRegistration(): bool
    {
        return !self::isDedicated();
    }
}
