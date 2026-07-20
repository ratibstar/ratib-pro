<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\HrEmployeeRequest;
use Rateb\App\Models\HrmEmployeeProfile;
use Rateb\App\Models\LeaveRequest;
use Rateb\App\Models\User;

/**
 * Thin ESS Phase C surface — composes existing models/services only.
 * No new HR business rules; tenant + employee scope from resolved identity.
 */
final class HrEssPhaseCService
{
    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function dashboard(int $userId, int $companyId): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employee = $resolved['body']['employee'] ?? [];
        $employeeId = (int) (is_array($employee) ? ($employee['id'] ?? 0) : 0);

        $hr = new HrService();
        $attendance = $hr->findAttendanceByEmployeeDate($companyId, $employeeId, date('Y-m-d'));
        $balances = $hr->leaveBalancesForEmployee($employeeId, (int) date('Y'));
        $notifications = (new NotificationService())->listForUser($userId, $companyId);
        $unread = 0;
        foreach ($notifications as $n) {
            if (empty($n['is_read'])) {
                $unread++;
            }
        }

        $pendingLeaves = (new LeaveRequest())->query(
            'SELECT id, leave_type_id, start_date, end_date, status, created_at
             FROM rateb_leave_requests
             WHERE company_id = :cid AND employee_id = :eid AND status IN (\'pending\',\'draft\')
             ORDER BY id DESC LIMIT 10',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $pendingRequests = (new HrEmployeeRequest())->query(
            'SELECT id, request_no, request_type, request_date, status, notes, created_at
             FROM rateb_hr_employee_requests
             WHERE company_id = :cid AND employee_id = :eid AND status IN (\'pending\',\'open\',\'draft\')
             ORDER BY id DESC LIMIT 10',
            ['cid' => $companyId, 'eid' => $employeeId]
        );

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'employee' => $employee,
                'attendance_today' => $attendance,
                'schedule_today' => null,
                'leave_balances' => is_array($balances) ? $balances : [],
                'pending_requests' => is_array($pendingRequests) ? $pendingRequests : [],
                'pending_leaves' => is_array($pendingLeaves) ? $pendingLeaves : [],
                'notifications_summary' => [
                    'total' => count($notifications),
                    'unread' => $unread,
                    'recent' => array_slice($notifications, 0, 5),
                ],
                'payroll_summary' => [
                    'available' => false,
                    'message' => 'Payslip detail uses payroll feature when enabled',
                ],
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function listEmployeeRequests(int $userId, int $companyId, ?string $type = null): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = (int) ($resolved['body']['employee']['id'] ?? 0);
        $params = ['cid' => $companyId, 'eid' => $employeeId];
        $sql = 'SELECT * FROM rateb_hr_employee_requests
                WHERE company_id = :cid AND employee_id = :eid';
        if ($type !== null && $type !== '') {
            $sql .= ' AND request_type = :t';
            $params['t'] = $type;
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $rows = (new HrEmployeeRequest())->query($sql, $params);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'requests' => is_array($rows) ? $rows : [],
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function getEmployeeRequest(int $userId, int $companyId, int $requestId): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = (int) ($resolved['body']['employee']['id'] ?? 0);
        $row = (new HrEmployeeRequest())->queryOne(
            'SELECT * FROM rateb_hr_employee_requests
             WHERE id = :id AND company_id = :cid AND employee_id = :eid LIMIT 1',
            ['id' => $requestId, 'cid' => $companyId, 'eid' => $employeeId]
        );
        if (!$row) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'message' => 'Request not found'],
            ];
        }

        return [
            'status' => 200,
            'body' => ['success' => true, 'request' => $row, 'history' => []],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{status:int, body:array<string,mixed>}
     */
    public function submitInquiry(int $userId, int $companyId, array $input): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = (int) ($resolved['body']['employee']['id'] ?? 0);
        $type = strtolower(trim((string) ($input['request_type'] ?? 'inquiry')));
        if (!in_array($type, ['inquiry', 'complaint'], true)) {
            $type = 'inquiry';
        }
        $notes = trim((string) ($input['notes'] ?? $input['message'] ?? ''));
        if ($notes === '') {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => 'Message is required'],
            ];
        }
        $id = (new HrEmployeeRequest())->create([
            'company_id' => $companyId,
            'request_no' => 'ESS-' . strtoupper(substr($type, 0, 3)) . '-' . date('YmdHis'),
            'employee_id' => $employeeId,
            'request_type' => $type,
            'request_date' => date('Y-m-d'),
            'status' => 'pending',
            'notes' => mb_substr($notes, 0, 2000),
        ]);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'id' => $id,
                'request_type' => $type,
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function ratings(int $userId, int $companyId): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = (int) ($resolved['body']['employee']['id'] ?? 0);
        $profile = (new HrmEmployeeProfile())->queryOne(
            'SELECT id FROM rateb_hrm_employee_profiles
             WHERE company_id = :cid AND legacy_employee_id = :eid AND deleted_at IS NULL
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $items = [];
        $score = null;
        if ($profile) {
            try {
                $list = (new PerformanceReviewService())->list(25, 0, '', null, (int) $profile['id']);
                $items = $list['items'] ?? [];
                foreach ($items as $row) {
                    if (isset($row['overall_score']) && $row['overall_score'] !== null && $row['overall_score'] !== '') {
                        $score = (float) $row['overall_score'];
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $items = [];
            }
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'performance_score' => $score,
                'reviews' => $items,
                'kpi_summary' => [],
                'monthly_evaluation' => $items[0] ?? null,
            ],
        ];
    }

    /**
     * Architecture-ready payment surface — no invented bank/gateway data.
     *
     * @return array{status:int, body:array<string,mixed>}
     */
    public function paymentMethods(int $userId, int $companyId): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'salary_payment' => null,
                'bank_accounts' => [],
                'wallet' => null,
                'gateways' => [],
                'available' => false,
            ],
        ];
    }

    /**
     * @param array{current_password?:string,new_password?:string} $input
     * @return array{status:int, body:array<string,mixed>}
     */
    public function changePassword(int $userId, int $companyId, array $input): array
    {
        if ($userId < 1 || $companyId < 1) {
            return [
                'status' => 401,
                'body' => ['success' => false, 'message' => 'Unauthorized'],
            ];
        }
        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['new_password'] ?? '');
        if ($current === '' || strlen($new) < 8) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => 'Invalid password payload'],
            ];
        }
        $userModel = new User();
        $user = $userModel->queryOne(
            'SELECT * FROM rateb_users WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $userId, 'cid' => $companyId]
        );
        if (!$user) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'message' => 'User not found'],
            ];
        }
        $hash = (string) ($user['password_hash'] ?? $user['password'] ?? '');
        if ($hash === '' || !password_verify($current, $hash)) {
            return [
                'status' => 403,
                'body' => ['success' => false, 'code' => 'invalid_current_password', 'message' => 'Current password is incorrect'],
            ];
        }
        $data = [];
        $userModel->applyPassword($data, $new);
        $userModel->update($userId, $data);

        return [
            'status' => 200,
            'body' => ['success' => true],
        ];
    }
}
