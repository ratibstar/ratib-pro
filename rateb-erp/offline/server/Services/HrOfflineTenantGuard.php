<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\AttendanceRecord;
use Rateb\App\Models\Employee;
use Rateb\App\Models\LeaveRequest;
use Rateb\App\Models\LeaveType;

/**
 * Tenant + branch isolation checks for HR offline replay.
 * Additive — does not alter HrService / HR controllers.
 */
final class HrOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope company_id, branch_id
     * @return array{ok: bool, error?: string, employee?: array<string, mixed>}
     */
    public function assertEmployee(int $employeeId, array $scope): array
    {
        if ($employeeId < 1) {
            return ['ok' => false, 'error' => 'invalid_employee_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }

        $emp = (new Employee())->find($employeeId);
        if ($emp === null) {
            return ['ok' => false, 'error' => 'employee_not_found'];
        }
        if ((int) ($emp['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => 'tenant_mismatch'];
        }

        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($emp['branch_id']) && $emp['branch_id'] !== null && $emp['branch_id'] !== '') {
            if ((int) $emp['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'employee' => $emp];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string}
     */
    public function assertLeaveType(int $leaveTypeId, array $scope): array
    {
        if ($leaveTypeId < 1) {
            return ['ok' => false, 'error' => 'invalid_leave_type_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new LeaveType())->find($leaveTypeId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'leave_type_not_found'];
        }
        if ((int) ($row['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => 'leave_type_tenant_mismatch'];
        }

        return ['ok' => true];
    }

    public function attendanceExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !OfflineSchema::hasColumn('rateb_attendance_records', 'id')) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $row = (new AttendanceRecord())->queryOne(
            'SELECT id FROM rateb_attendance_records
             WHERE company_id = :cid AND notes LIKE :marker
             ORDER BY id ASC LIMIT 1',
            ['cid' => $companyId, 'marker' => $marker]
        );

        return $row ? (int) ($row['id'] ?? 0) : null;
    }

    public function leaveExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !OfflineSchema::hasColumn('rateb_leave_requests', 'id')) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $row = (new LeaveRequest())->queryOne(
            'SELECT id FROM rateb_leave_requests
             WHERE company_id = :cid AND reason LIKE :marker
             ORDER BY id ASC LIMIT 1',
            ['cid' => $companyId, 'marker' => $marker]
        );

        return $row ? (int) ($row['id'] ?? 0) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAttendanceByEmployeeDate(int $companyId, int $employeeId, string $date): ?array
    {
        if ($companyId < 1 || $employeeId < 1 || $date === '') {
            return null;
        }
        if (!OfflineSchema::hasColumn('rateb_attendance_records', 'id')) {
            return null;
        }

        return (new AttendanceRecord())->queryOne(
            'SELECT * FROM rateb_attendance_records
             WHERE company_id = :cid AND employee_id = :eid AND attendance_date = :d
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId, 'd' => $date]
        );
    }
}
