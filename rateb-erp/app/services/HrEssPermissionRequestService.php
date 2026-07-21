<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\HrPermissionRequest;

/**
 * ESS short-exit permission requests — employee-scoped only.
 * Never trusts client employee_id / company_id.
 */
final class HrEssPermissionRequestService
{
    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function listMine(int $userId, int $companyId, ?string $status = null): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);

        $statusFilter = $status !== null && $status !== '' ? strtolower(trim($status)) : null;
        if ($statusFilter !== null
            && !in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'], true)
        ) {
            return $this->fail(422, 'invalid_status', 'Invalid permission request status filter');
        }

        $sql = 'SELECT id, permission_date, time_from, time_to, reason, status, created_at
                FROM rateb_hr_permission_requests
                WHERE company_id = :cid AND employee_id = :eid';
        $params = ['cid' => $companyId, 'eid' => $employeeId];
        if ($statusFilter !== null) {
            $sql .= ' AND status = :st';
            $params['st'] = $statusFilter;
        }
        $sql .= ' ORDER BY permission_date DESC, id DESC LIMIT 100';

        $rows = (new HrPermissionRequest())->query($sql, $params);
        $items = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $dto = $this->dto(is_array($row) ? $row : null);
            if ($dto !== null) {
                $items[] = $dto;
            }
        }

        return $this->ok(['items' => $items]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function show(int $userId, int $companyId, int $requestId): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);
        if ($requestId < 1) {
            return $this->fail(422, 'invalid_request', 'Invalid permission request id');
        }

        $row = (new HrPermissionRequest())->queryOne(
            'SELECT id, permission_date, time_from, time_to, reason, status, created_at
             FROM rateb_hr_permission_requests
             WHERE company_id = :cid AND employee_id = :eid AND id = :id
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId, 'id' => $requestId]
        );
        if ($row === null) {
            return $this->fail(404, 'not_found', 'Permission request not found');
        }

        return $this->ok(['request' => $this->dto($row)]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function submit(int $userId, int $companyId, array $payload = []): array
    {
        $resolved = $this->resolveEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeIdFromResolved($resolved);

        unset(
            $payload['employee_id'],
            $payload['company_id'],
            $payload['user_id'],
            $payload['status'],
            $payload['approved_by'],
            $payload['approved_at']
        );

        $date = $this->normalizeDate($payload['permission_date'] ?? null);
        if ($date === null) {
            return $this->fail(422, 'validation_error', 'permission_date is required (YYYY-MM-DD)');
        }
        $timeFrom = $this->normalizeTime($payload['time_from'] ?? null);
        $timeTo = $this->normalizeTime($payload['time_to'] ?? null);
        if ($timeFrom === null || $timeTo === null) {
            return $this->fail(422, 'validation_error', 'time_from and time_to are required (HH:MM)');
        }
        if ($timeFrom >= $timeTo) {
            return $this->fail(422, 'validation_error', 'time_to must be after time_from');
        }

        $reason = trim((string) ($payload['reason'] ?? ''));
        if (mb_strlen($reason) > 1000) {
            return $this->fail(422, 'validation_error', 'reason is too long');
        }

        $overlap = (new HrPermissionRequest())->queryOne(
            "SELECT id FROM rateb_hr_permission_requests
             WHERE company_id = :cid
               AND employee_id = :eid
               AND permission_date = :d
               AND status = 'pending'
               AND time_from < :tto
               AND time_to > :tfrom
             LIMIT 1",
            [
                'cid' => $companyId,
                'eid' => $employeeId,
                'd' => $date,
                'tto' => $timeTo,
                'tfrom' => $timeFrom,
            ]
        );
        if ($overlap !== null) {
            return $this->fail(409, 'duplicate_request', 'Overlapping permission request already exists');
        }

        $id = (new HrPermissionRequest())->create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'permission_date' => $date,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'reason' => $reason !== '' ? $reason : null,
            'status' => 'pending',
        ]);
        if ($id < 1) {
            return $this->fail(500, 'submit_failed', 'Could not create permission request');
        }

        $row = (new HrPermissionRequest())->queryOne(
            'SELECT id, permission_date, time_from, time_to, reason, status, created_at
             FROM rateb_hr_permission_requests
             WHERE id = :id AND company_id = :cid AND employee_id = :eid
             LIMIT 1',
            ['id' => $id, 'cid' => $companyId, 'eid' => $employeeId]
        );

        return $this->ok([
            'request' => $this->dto($row),
            'permission_request_id' => $id,
        ], 201);
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

    private function normalizeDate(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $s);

        return ($dt && $dt->format('Y-m-d') === $s) ? $s : null;
    }

    private function normalizeTime(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $s)) {
            $s = substr($s, 0, 5);
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $s)) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $s));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $h, $m);
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    private function dto(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $from = (string) ($row['time_from'] ?? '');
        $to = (string) ($row['time_to'] ?? '');
        if (strlen($from) >= 5) {
            $from = substr($from, 0, 5);
        }
        if (strlen($to) >= 5) {
            $to = substr($to, 0, 5);
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'permission_date' => (string) ($row['permission_date'] ?? ''),
            'time_from' => $from,
            'time_to' => $to,
            'reason' => $row['reason'] !== null ? (string) $row['reason'] : null,
            'status' => (string) ($row['status'] ?? ''),
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int,body:array<string,mixed>}
     */
    private function ok(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'body' => ['success' => true, 'data' => $data],
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
