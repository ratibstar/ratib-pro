<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\AttendanceRecord;

/**
 * ESS Attendance — thin orchestration over HrService + AttendanceRecord.
 *
 * No shift/payroll/attendance policy calculations.
 * Identity always from resolver (caller); never trusts client employee_id.
 */
final class HrEssAttendanceService
{
    public function __construct(
        private ?HrService $hr = null,
    ) {
    }

    private function hr(): HrService
    {
        return $this->hr ??= new HrService();
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function today(int $userId, int $companyId, ?string $date = null): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);
        $day = $this->normalizeDate($date ?? date('Y-m-d'));
        if ($day === null) {
            return $this->fail(422, 'invalid_date', 'Invalid attendance date');
        }

        $row = $this->hr()->findAttendanceByEmployeeDate($companyId, $employeeId, $day);

        return $this->ok([
            'attendance' => $this->toDto($row),
        ]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function history(int $userId, int $companyId, ?string $from = null, ?string $to = null): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);

        $toDay = $this->normalizeDate($to ?? date('Y-m-d'));
        $fromDay = $this->normalizeDate($from ?? date('Y-m-d', strtotime('-30 days')));
        if ($fromDay === null || $toDay === null) {
            return $this->fail(422, 'invalid_date', 'Invalid attendance date range');
        }
        if ($fromDay > $toDay) {
            return $this->fail(422, 'invalid_date', 'from must be on or before to');
        }

        $rows = $this->hr()->listAttendanceForEmployee($companyId, $employeeId, $fromDay, $toDay);
        $items = [];
        foreach ($rows as $row) {
            $dto = $this->toDto(is_array($row) ? $row : null);
            if ($dto !== null) {
                $items[] = $dto;
            }
        }

        return $this->ok([
            'items' => $items,
            'from' => $fromDay,
            'to' => $toDay,
        ]);
    }

    /**
     * Check-in for today (or optional date). Creates the day row if missing.
     * Duplicate check-in → 409 already_checked_in.
     *
     * @param array<string,mixed> $payload ignored identity fields; optional notes only
     * @return array{status:int,body:array<string,mixed>}
     */
    public function checkIn(int $userId, int $companyId, array $payload = []): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employee = is_array($resolved['body']['employee'] ?? null)
            ? $resolved['body']['employee']
            : [];
        $employeeId = (int) ($employee['id'] ?? 0);
        $day = $this->normalizeDate(
            isset($payload['date']) ? (string) $payload['date'] : date('Y-m-d')
        );
        if ($day === null || $employeeId < 1) {
            return $this->fail(422, 'invalid_date', 'Invalid attendance date');
        }

        // Never trust client employee_id — strip if present.
        unset($payload['employee_id'], $payload['company_id'], $payload['user_id']);

        $existing = $this->hr()->findAttendanceByEmployeeDate($companyId, $employeeId, $day);
        if ($existing !== null && $this->hasTime($existing['check_in'] ?? null)) {
            return $this->fail(409, 'already_checked_in', 'Already checked in for this date');
        }

        $checkIn = $this->normalizeTime(
            isset($payload['check_in']) ? (string) $payload['check_in'] : date('H:i:s')
        );
        if ($checkIn === null) {
            return $this->fail(422, 'invalid_state', 'Invalid check-in time');
        }

        $notes = trim((string) ($payload['notes'] ?? ''));
        $branchId = (int) ($employee['branch_id'] ?? 0);

        $model = new AttendanceRecord();
        if ($existing !== null) {
            $id = (int) ($existing['id'] ?? 0);
            $update = [
                'check_in' => $checkIn,
                'status' => 'present',
            ];
            if ($notes !== '') {
                $update['notes'] = $notes;
            }
            $model->update($id, $update);
        } else {
            $create = [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'attendance_date' => $day,
                'check_in' => $checkIn,
                'check_out' => null,
                'status' => 'present',
                'notes' => $notes !== '' ? $notes : null,
            ];
            if ($branchId > 0) {
                $create['branch_id'] = $branchId;
            }
            $id = $model->create($create);
        }

        $row = $this->hr()->findAttendanceByEmployeeDate($companyId, $employeeId, $day);

        return $this->ok([
            'attendance' => $this->toDto($row),
            'action' => 'check_in',
            'attendance_id' => $id,
        ]);
    }

    /**
     * Check-out — online only. Requires prior check-in.
     *
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function checkOut(int $userId, int $companyId, array $payload = []): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);
        $day = $this->normalizeDate(
            isset($payload['date']) ? (string) $payload['date'] : date('Y-m-d')
        );
        if ($day === null) {
            return $this->fail(422, 'invalid_date', 'Invalid attendance date');
        }

        unset($payload['employee_id'], $payload['company_id'], $payload['user_id']);

        $existing = $this->hr()->findAttendanceByEmployeeDate($companyId, $employeeId, $day);
        if ($existing === null || !$this->hasTime($existing['check_in'] ?? null)) {
            return $this->fail(422, 'invalid_state', 'Check-in required before check-out');
        }
        if ($this->hasTime($existing['check_out'] ?? null)) {
            return $this->fail(422, 'invalid_state', 'Already checked out for this date');
        }

        $checkOut = $this->normalizeTime(
            isset($payload['check_out']) ? (string) $payload['check_out'] : date('H:i:s')
        );
        if ($checkOut === null) {
            return $this->fail(422, 'invalid_state', 'Invalid check-out time');
        }

        $id = (int) ($existing['id'] ?? 0);
        (new AttendanceRecord())->update($id, [
            'check_out' => $checkOut,
        ]);

        $row = $this->hr()->findAttendanceByEmployeeDate($companyId, $employeeId, $day);

        return $this->ok([
            'attendance' => $this->toDto($row),
            'action' => 'check_out',
            'attendance_id' => $id,
        ]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function resolveEmployee(int $userId, int $companyId): array
    {
        return (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
    }

    /**
     * @param array{status:int,body:array<string,mixed>} $resolved
     */
    private function employeeIdFromResolved(array $resolved): int
    {
        $employee = $resolved['body']['employee'] ?? null;

        return (int) (is_array($employee) ? ($employee['id'] ?? 0) : 0);
    }

    /**
     * Explicit ESS DTO — no SELECT * leakage.
     *
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    private function toDto(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'attendance_date' => (string) ($row['attendance_date'] ?? ''),
            'check_in' => $row['check_in'] !== null && $row['check_in'] !== ''
                ? (string) $row['check_in']
                : null,
            'check_out' => $row['check_out'] !== null && $row['check_out'] !== ''
                ? (string) $row['check_out']
                : null,
            'status' => (string) ($row['status'] ?? ''),
            'notes' => isset($row['notes']) && $row['notes'] !== null && $row['notes'] !== ''
                ? (string) $row['notes']
                : null,
        ];
    }

    private function hasTime(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        $s = trim((string) $value);

        return $s !== '' && $s !== '00:00:00';
    }

    private function normalizeDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $parts = explode('-', $date);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return null;
        }

        return $date;
    }

    private function normalizeTime(string $time): ?string
    {
        $time = trim($time);
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int,body:array<string,mixed>}
     */
    private function ok(array $data): array
    {
        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $data,
            ],
        ];
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function fail(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
