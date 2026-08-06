<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\Integration;

use Rateb\App\Logistics\Contracts\EmployeeDirectory;
use Rateb\App\Services\HrService;

/** Resolves employees via HrService::employeeProfile — no Core edits. */
final class HrEmployeeDirectory implements EmployeeDirectory
{
    public function __construct(private HrService $hr = new HrService())
    {
    }

    public function findEmployee(int $employeeId): ?array
    {
        if ($employeeId < 1) {
            return null;
        }
        $profile = $this->hr->employeeProfile($employeeId);
        if (!is_array($profile)) {
            return null;
        }
        $employee = $profile['employee'] ?? null;

        return is_array($employee) ? $employee : null;
    }
}
