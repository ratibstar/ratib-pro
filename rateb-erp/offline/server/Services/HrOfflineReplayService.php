<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\AttendanceRecord;
use Rateb\App\Models\LeaveRequest;
use Rateb\App\Services\HrService;

/**
 * Thin HR offline replay adapter — delegates to existing HR domain models / HrService.
 * No payroll, approvals, or financial posting (Phase 4 / Tier 1).
 * Mirrors HrAttendanceController / HrAttendanceBulkController / HrLeavesController write paths.
 */
final class HrOfflineReplayService
{
    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'attendance.create',
            'attendance',
            'hr.attendance',
            'attendance.bulk',
            'hr.attendance.bulk',
            'leave_request.create',
            'leave_request.draft',
            'leave_draft',
            'hr.leave_draft',
        ];
    }

    public function __construct(
        private ?HrOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): HrOfflineTenantGuard
    {
        return $this->guard ??= new HrOfflineTenantGuard();
    }

    private function resolver(): OfflineConflictResolverService
    {
        return $this->resolver ??= new OfflineConflictResolverService();
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array{status: string, error?: string, result?: array<string, mixed>, reason?: string}
     */
    public function replayFromQueueRow(array $queueRow): array
    {
        $decoded = $this->decodePayload($queueRow);
        $action = $this->normalizeAction(
            (string) ($decoded['action'] ?? $queueRow['action'] ?? '')
        );
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        unset($inner['branch_id'], $inner['company_id'], $inner['user_id'], $inner['device_id']);
        $idempotencyKey = substr(trim((string) (
            $queueRow['idempotency_key']
            ?? $decoded['client_id']
            ?? $decoded['idempotency_key']
            ?? ''
        )), 0, 64);

        if (!in_array($action, self::deferredActions(), true)) {
            return ['status' => 'skipped', 'error' => 'unknown_hr_action'];
        }

        try {
            $scope = $this->buildScope($queueRow);
            if ($scope['company_id'] < 1) {
                return ['status' => 'failed', 'error' => 'company_required'];
            }

            TenantContext::setCompanyId($scope['company_id']);
            if ($scope['user_id'] > 0) {
                SessionManager::set('rateb_user_id', $scope['user_id']);
            }
            HrService::bootstrapTenant();

            $result = $this->replay($action, $scope, $inner, $idempotencyKey);

            return ['status' => 'synced', 'result' => $result];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($this->isConflictError($message)) {
                return ['status' => 'conflict', 'error' => $message, 'reason' => $message];
            }

            return ['status' => 'failed', 'error' => $message];
        }
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    public function replay(string $action, array $scope, array $inner, string $idempotencyKey = ''): array
    {
        $action = $this->normalizeAction($action);

        return match ($action) {
            'attendance.create', 'attendance', 'hr.attendance'
                => $this->attendanceCreate($scope, $inner, $idempotencyKey),
            'attendance.bulk', 'hr.attendance.bulk'
                => $this->attendanceBulk($scope, $inner, $idempotencyKey),
            'leave_request.create', 'leave_request.draft', 'leave_draft', 'hr.leave_draft'
                => $this->leaveDraft($scope, $inner, $idempotencyKey),
            default => throw new \RuntimeException('unknown_hr_action'),
        };
    }

    /**
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolveConflict(array $clientItem, ?array $serverItem): array
    {
        return $this->resolver()->resolveHr($clientItem, $serverItem);
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function attendanceCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        $employeeId = (int) ($inner['employee_id'] ?? 0);
        $assert = $this->guard()->assertEmployee($employeeId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }

        $date = trim((string) ($inner['attendance_date'] ?? ''));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \RuntimeException('empty_attendance_payload');
        }

        if ($idempotencyKey !== '') {
            $existingId = $this->guard()->attendanceExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingId !== null && $existingId > 0) {
                return ['ok' => true, 'idempotent' => true, 'attendance_id' => $existingId];
            }
        }

        $existing = $this->guard()->findAttendanceByEmployeeDate($scope['company_id'], $employeeId, $date);
        if ($existing !== null) {
            $decision = $this->resolver()->resolveHr(
                [
                    'version' => (int) ($inner['version'] ?? 1),
                    'expected_status' => $inner['expected_status'] ?? null,
                    'expected_check_in' => $inner['expected_check_in'] ?? null,
                ],
                [
                    'version' => (int) ($inner['server_version'] ?? ((int) ($existing['id'] ?? 0))),
                    'status' => $existing['status'] ?? null,
                    'check_in' => $existing['check_in'] ?? null,
                ]
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'attendance_conflict'));
            }
        }

        $status = trim((string) ($inner['status'] ?? 'present'));
        if (!in_array($status, ['present', 'absent', 'late', 'leave', 'half_day'], true)) {
            $status = 'present';
        }

        $notes = trim((string) ($inner['notes'] ?? ''));
        if ($idempotencyKey !== '') {
            $notes = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }

        $payload = [
            'company_id' => $scope['company_id'],
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'check_in' => $this->normalizeTime((string) ($inner['check_in'] ?? '09:00')),
            'check_out' => $this->normalizeTime((string) ($inner['check_out'] ?? '17:00')),
            'status' => $status,
            'notes' => $notes !== '' ? $notes : null,
        ];
        if ($scope['branch_id'] > 0) {
            $payload['branch_id'] = $scope['branch_id'];
        }

        $model = new AttendanceRecord();
        if ($existing !== null) {
            $id = (int) ($existing['id'] ?? 0);
            $model->update($id, $payload);

            return ['ok' => true, 'attendance_id' => $id, 'updated' => true];
        }

        $id = $model->create($payload);

        return ['ok' => true, 'attendance_id' => $id, 'created' => true];
    }

    /**
     * Bulk attendance — same upsert semantics as HrAttendanceBulkController::store.
     *
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function attendanceBulk(array $scope, array $inner, string $idempotencyKey): array
    {
        $date = trim((string) ($inner['attendance_date'] ?? ''));
        $rows = is_array($inner['rows'] ?? null) ? $inner['rows'] : [];
        if ($date === '' || $rows === []) {
            throw new \RuntimeException('empty_attendance_bulk_payload');
        }

        $saved = 0;
        $ids = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowKey = $idempotencyKey !== '' ? ($idempotencyKey . ':' . $i) : '';
            $result = $this->attendanceCreate($scope, array_merge($row, [
                'attendance_date' => $date,
                'version' => $inner['version'] ?? 1,
            ]), $rowKey);
            $ids[] = (int) ($result['attendance_id'] ?? 0);
            $saved++;
        }

        if ($saved < 1) {
            throw new \RuntimeException('empty_attendance_bulk_payload');
        }

        return ['ok' => true, 'saved' => $saved, 'attendance_ids' => $ids];
    }

    /**
     * Leave request draft only — status pending; never calls approveLeave/rejectLeave.
     *
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function leaveDraft(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existingId = $this->guard()->leaveExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingId !== null && $existingId > 0) {
                return ['ok' => true, 'idempotent' => true, 'leave_request_id' => $existingId];
            }
        }

        $employeeId = (int) ($inner['employee_id'] ?? 0);
        $assert = $this->guard()->assertEmployee($employeeId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }

        $leaveTypeId = (int) ($inner['leave_type_id'] ?? 0);
        $lt = $this->guard()->assertLeaveType($leaveTypeId, $scope);
        if (!$lt['ok']) {
            throw new \RuntimeException((string) ($lt['error'] ?? 'leave_type_tenant_mismatch'));
        }

        $start = trim((string) ($inner['start_date'] ?? ''));
        $end = trim((string) ($inner['end_date'] ?? ''));
        if ($start === '' || $end === '') {
            throw new \RuntimeException('empty_leave_draft_payload');
        }

        $days = (float) ($inner['days'] ?? 0);
        if ($days <= 0) {
            $startTs = strtotime($start);
            $endTs = strtotime($end);
            if ($startTs === false || $endTs === false || $endTs < $startTs) {
                throw new \RuntimeException('invalid_leave_dates');
            }
            $days = (int) round(($endTs - $startTs) / 86400) + 1;
        }

        $reason = trim((string) ($inner['reason'] ?? ''));
        if ($idempotencyKey !== '') {
            $reason = trim($reason . ' [offline:' . $idempotencyKey . ']');
        }

        // Draft only — pending awaiting online approval (approvals out of scope).
        $payload = [
            'company_id' => $scope['company_id'],
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'reason' => $reason !== '' ? $reason : null,
            'status' => 'pending',
        ];
        if ($scope['branch_id'] > 0) {
            $payload['branch_id'] = $scope['branch_id'];
        }

        $id = (new LeaveRequest())->create($payload);

        return ['ok' => true, 'leave_request_id' => $id, 'status' => 'pending', 'draft' => true];
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '09:00:00';
        }
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }

    private function isConflictError(string $message): bool
    {
        return in_array($message, [
            'attendance_conflict',
            'server_newer',
            'status_changed',
            'branch_mismatch',
            'tenant_mismatch',
        ], true);
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        $aliases = [
            'create_attendance' => 'attendance.create',
            'bulk_attendance' => 'attendance.bulk',
            'create_leave_draft' => 'leave_request.draft',
            'create_leave' => 'leave_request.draft',
        ];

        return $aliases[$action] ?? $action;
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array<string, mixed>
     */
    private function decodePayload(array $queueRow): array
    {
        $raw = $queueRow['payload'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return ['action' => $queueRow['action'] ?? null, 'payload' => []];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['action' => $queueRow['action'] ?? null, 'payload' => []];
    }

    /**
     * Scope from queue row only — never from client payload (H-BRANCH-001).
     *
     * @param array<string, mixed> $queueRow
     * @return array{company_id: int, branch_id: int, user_id: int, device_id: string}
     */
    private function buildScope(array $queueRow): array
    {
        return (new OfflineReplayScopeService())->fromQueueRow($queueRow);
    }
}
