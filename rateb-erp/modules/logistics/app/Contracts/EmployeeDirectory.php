<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Contracts;

interface EmployeeDirectory
{
    /**
     * Returns employee row for the given id, or null if missing.
     *
     * @return array<string, mixed>|null
     */
    public function findEmployee(int $employeeId): ?array;
}
