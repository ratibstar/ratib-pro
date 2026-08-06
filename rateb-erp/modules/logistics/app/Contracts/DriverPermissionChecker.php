<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Contracts;

interface DriverPermissionChecker
{
    public function canDrive(int $userId, int $companyId): bool;
}
