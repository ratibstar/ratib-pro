<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\HrEmployeeRequest;
use Rateb\App\Models\LeaveRequest;

/**
 * Phase P — simplified ESS Employee 360 (self only).
 * Composes existing profile / leave / request / decision / document reads.
 * Never trusts client employee_id. Does not mutate SoT engines.
 */
final class HrEss360Service
{
    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function simplified360(int $userId, int $companyId): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $this->normalizeFailure($resolved);
        }
        $employee = is_array($resolved['body']['employee'] ?? null) ? $resolved['body']['employee'] : [];
        $employeeId = (int) ($employee['id'] ?? 0);
        if ($employeeId < 1) {
            return $this->fail(404, 'employee_unbound', 'Employee not bound');
        }

        $profile = (new HrEssProfileService())->getProfile($userId, $companyId);
        $profileDto = (int) ($profile['status'] ?? 0) === 200
            ? ($profile['body']['data']['profile'] ?? $profile['body']['profile'] ?? null)
            : null;

        $hr = new HrService();
        $balances = $hr->leaveBalancesForEmployee($employeeId, (int) date('Y'));
        $leaveHistory = (new LeaveRequest())->query(
            'SELECT id, leave_type_id, start_date, end_date, days, status, created_at
             FROM rateb_leave_requests
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC LIMIT 25',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $requests = (new HrEmployeeRequest())->query(
            'SELECT id, request_no, request_type, request_date, status, notes, document_id, created_at
             FROM rateb_hr_employee_requests
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC LIMIT 25',
            ['cid' => $companyId, 'eid' => $employeeId]
        );

        $decisions = [];
        try {
            $decisions = (new HrDecisionService())->listForEmployee($companyId, $employeeId, 20);
        } catch (\Throwable $e) {
            $decisions = [];
        }

        $docs = (new HrEssPayslipDocumentService())->listDocuments($userId, $companyId);
        $docItems = (int) ($docs['status'] ?? 0) === 200
            ? ($docs['body']['data']['items'] ?? $docs['body']['items'] ?? [])
            : [];

        $payslips = (new HrEssPayslipDocumentService())->listPayslips($userId, $companyId);
        $payslipItems = (int) ($payslips['status'] ?? 0) === 200
            ? ($payslips['body']['data']['items'] ?? $payslips['body']['items'] ?? [])
            : [];

        $notifications = new NotificationService();
        $unread = $notifications->countUnreadForUser($userId, $companyId);
        $recent = $notifications->listRecentForUser($userId, $companyId, 8);

        $letterTypes = HrLetterIssueService::LETTER_TYPES;
        $letterRequests = [];
        foreach (is_array($requests) ? $requests : [] as $row) {
            if (in_array((string) ($row['request_type'] ?? ''), $letterTypes, true)) {
                $letterRequests[] = $row;
            }
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'employee' => $employee,
                'profile' => is_array($profileDto) ? $profileDto : null,
                'leave_balances' => is_array($balances) ? $balances : [],
                'leave_history' => is_array($leaveHistory) ? $leaveHistory : [],
                'requests' => is_array($requests) ? $requests : [],
                'letters' => $letterRequests,
                'decisions' => is_array($decisions) ? $decisions : [],
                'documents' => is_array($docItems) ? array_slice($docItems, 0, 25) : [],
                'payslips' => is_array($payslipItems) ? array_slice($payslipItems, 0, 12) : [],
                'notifications' => [
                    'unread' => $unread,
                    'recent' => $recent,
                ],
                'certificate_types' => $letterTypes,
            ],
        ];
    }

    /**
     * @return array{status:int, body:array{success:false,code:string,message:string}}
     */
    private function fail(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'code' => $code,
                'message' => rateb_error_message($code, $message),
            ],
        ];
    }

    /**
     * @param array{status?:int, body?:array<string,mixed>} $result
     * @return array{status:int, body:array{success:false,code:string,message:string}}
     */
    private function normalizeFailure(array $result): array
    {
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];

        return $this->fail(
            (int) ($result['status'] ?? 500),
            (string) ($body['code'] ?? 'error'),
            (string) ($body['message'] ?? '')
        );
    }
}
