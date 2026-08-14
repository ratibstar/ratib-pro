<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\LeaveRequest;
use Rateb\App\Models\LeaveType;

/**
 * ESS Leave — thin orchestration over HrService + LeaveRequest / LeaveType.
 *
 * No leave policy invention beyond existing Admin/offline days formula.
 * Identity from resolver (caller); never trusts client employee_id.
 */
final class HrEssLeaveService
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
    public function balances(int $userId, int $companyId, ?int $year = null): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);
        $y = $year !== null && $year > 0 ? $year : (int) date('Y');

        $rows = $this->hr()->leaveBalancesForEmployee($employeeId, $y);
        $items = [];
        foreach ($this->essPrimaryLeaveBalances(is_array($rows) ? $rows : []) as $row) {
            $dto = $this->balanceDto(is_array($row) ? $row : null);
            if ($dto !== null) {
                $items[] = $dto;
            }
        }

        return $this->ok([
            'items' => $items,
            'year' => $y,
        ]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function listRequests(int $userId, int $companyId, ?string $status = null): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);
        $statusFilter = $status !== null && $status !== '' ? strtolower(trim($status)) : null;
        if ($statusFilter !== null && !in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            return $this->fail(422, 'invalid_status', 'Invalid leave request status filter');
        }

        $rows = $this->hr()->listLeaveRequestsForEmployee($companyId, $employeeId, $statusFilter);
        $items = [];
        foreach ($rows as $row) {
            $dto = $this->requestDto(is_array($row) ? $row : null);
            if ($dto !== null) {
                $items[] = $dto;
            }
        }

        return $this->ok(['items' => $items]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function getRequest(int $userId, int $companyId, int $requestId): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);
        if ($requestId < 1) {
            return $this->fail(422, 'invalid_request', 'Invalid leave request id');
        }

        $row = $this->hr()->findLeaveRequestForEmployee($companyId, $employeeId, $requestId);
        if ($row === null) {
            return $this->fail(404, 'not_found', 'Leave request not found');
        }

        return $this->ok(['request' => $this->requestDto($row)]);
    }

    /**
     * Apply leave as pending draft. Server computes inclusive days.
     *
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function apply(int $userId, int $companyId, array $payload = []): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employee = is_array($resolved['body']['employee'] ?? null)
            ? $resolved['body']['employee']
            : [];
        $employeeId = (int) ($employee['id'] ?? 0);

        unset($payload['employee_id'], $payload['company_id'], $payload['user_id'], $payload['status']);

        $leaveTypeId = (int) ($payload['leave_type_id'] ?? 0);
        if ($leaveTypeId < 1) {
            return $this->fail(422, 'validation_error', 'leave_type_id is required');
        }

        $type = (new LeaveType())->queryOne(
            'SELECT id, company_id, code, name, status
             FROM rateb_leave_types
             WHERE id = :id AND company_id = :cid
             LIMIT 1',
            ['id' => $leaveTypeId, 'cid' => $companyId]
        );
        if ($type === null) {
            return $this->fail(422, 'validation_error', 'Invalid leave type for this company');
        }
        if (strtolower((string) ($type['status'] ?? '')) === 'inactive') {
            return $this->fail(422, 'validation_error', 'Leave type is inactive');
        }

        $start = $this->normalizeDate(isset($payload['start_date']) ? (string) $payload['start_date'] : '');
        $end = $this->normalizeDate(isset($payload['end_date']) ? (string) $payload['end_date'] : '');
        if ($start === null || $end === null) {
            return $this->fail(422, 'validation_error', 'start_date and end_date are required (YYYY-MM-DD)');
        }
        if ($end < $start) {
            return $this->fail(422, 'validation_error', 'end_date must be on or after start_date');
        }

        $days = $this->inclusiveDays($start, $end);
        if ($days < 1) {
            return $this->fail(422, 'validation_error', 'Invalid leave date range');
        }

        if ($this->hr()->hasOverlappingLeaveRequest($companyId, $employeeId, $start, $end)) {
            return $this->fail(409, 'duplicate_request', 'Overlapping leave request already exists');
        }

        $reason = trim((string) ($payload['reason'] ?? ''));
        $branchId = (int) ($employee['branch_id'] ?? 0);

        try {
            $id = $this->hr()->createPendingLeaveRequest(
                $companyId,
                $employeeId,
                $leaveTypeId,
                $start,
                $end,
                (float) $days,
                $reason !== '' ? $reason : null,
                $branchId > 0 ? $branchId : null,
                $userId
            );
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === __('leave_overlap_conflict') || str_contains($msg, 'overlap')) {
                return $this->fail(409, 'duplicate_request', 'Overlapping leave request already exists');
            }
            if ($msg === __('leave_balance_insufficient') || str_contains($msg, 'balance')) {
                return $this->fail(422, 'insufficient_balance', 'Requested days exceed remaining leave balance');
            }
            return $this->fail(422, 'validation_error', $msg !== '' ? $msg : 'Leave request rejected');
        }

        $row = $this->hr()->findLeaveRequestForEmployee($companyId, $employeeId, $id);

        // Notify after persist only — reuse existing oversight notifier (no second NotificationService).
        if ($id > 0) {
            $label = function_exists('__') ? (string) __('hr_leaves') : 'hr_leaves';
            ApprovalOversightService::notifyPendingSubmission(
                $companyId,
                'hr_leave',
                $label !== '' ? $label : 'hr_leaves',
                $id
            );
        }

        return $this->ok([
            'request' => $this->requestDto($row),
            'leave_request_id' => $id,
        ], 201);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function cancel(int $userId, int $companyId, int $requestId): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);
        if ($requestId < 1) {
            return $this->fail(422, 'invalid_request', 'Invalid leave request id');
        }
        $row = $this->hr()->findLeaveRequestForEmployee($companyId, $employeeId, $requestId);
        if ($row === null) {
            return $this->fail(404, 'not_found', 'Leave request not found');
        }
        try {
            $this->hr()->cancelLeave($requestId, $userId);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'posted_payroll') || $msg === __('leave_cancel_blocked_posted_payroll')) {
                return $this->fail(409, 'posted_payroll', 'Cannot cancel leave overlapping posted payroll');
            }
            return $this->fail(422, 'cancel_failed', $msg !== '' ? $msg : 'Cancel failed');
        }
        $updated = $this->hr()->findLeaveRequestForEmployee($companyId, $employeeId, $requestId);
        return $this->ok(['request' => $this->requestDto($updated)]);
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
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function essPrimaryLeaveBalances(array $rows): array
    {
        $skip = ['maternity' => true, 'paternity' => true, 'iddah' => true];
        $preferred = ['annual', 'sick', 'emergency', 'unpaid', 'hajj', 'marriage', 'bereavement'];
        $byCode = [];
        foreach ($rows as $row) {
            $code = strtolower(trim((string) ($row['leave_type_code'] ?? '')));
            if ($code === '' || isset($skip[$code])) {
                continue;
            }
            $byCode[$code] = $row;
        }
        $ordered = [];
        foreach ($preferred as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code];
                unset($byCode[$code]);
            }
        }
        foreach ($byCode as $row) {
            $ordered[] = $row;
        }

        return $ordered;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    private function balanceDto(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'leave_type_id' => (int) ($row['leave_type_id'] ?? 0),
            'leave_type_code' => (string) ($row['leave_type_code'] ?? ''),
            'leave_type_name' => (string) ($row['leave_type_name'] ?? ''),
            'balance_year' => (int) ($row['balance_year'] ?? 0),
            'entitled_days' => (float) ($row['entitled_days'] ?? 0),
            'used_days' => (float) ($row['used_days'] ?? 0),
            'remaining_days' => (float) ($row['remaining_days'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    private function requestDto(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'leave_type_id' => (int) ($row['leave_type_id'] ?? 0),
            'leave_type_code' => (string) ($row['leave_type_code'] ?? ''),
            'leave_type_name' => (string) ($row['leave_type_name'] ?? ''),
            'start_date' => (string) ($row['start_date'] ?? ''),
            'end_date' => (string) ($row['end_date'] ?? ''),
            'days' => isset($row['days']) ? (float) $row['days'] : null,
            'reason' => isset($row['reason']) && $row['reason'] !== null && $row['reason'] !== ''
                ? (string) $row['reason']
                : null,
            'status' => (string) ($row['status'] ?? ''),
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
        ];
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $parts = explode('-', $date);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return null;
        }

        return $date;
    }

    /** Inclusive calendar days — same formula as Admin leave create / offline leaveDraft. */
    private function inclusiveDays(string $start, string $end): int
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        if ($startTs === false || $endTs === false || $endTs < $startTs) {
            return 0;
        }

        return (int) round(($endTs - $startTs) / 86400) + 1;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int,body:array<string,mixed>}
     */
    private function ok(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
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
