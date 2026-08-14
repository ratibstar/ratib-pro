<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrManagerTeamService;

/** Phase P — Manager self-service API (soft-linked team only). */
final class HrManagerTeamController extends Controller
{
    public function team(): void
    {
        $result = (new HrManagerTeamService())->myTeam(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function attendance(): void
    {
        $from = $this->input('from', null);
        $to = $this->input('to', null);
        $result = (new HrManagerTeamService())->teamAttendance(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            is_string($from) ? $from : null,
            is_string($to) ? $to : null
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function leave(): void
    {
        $status = $this->input('status', null);
        $result = (new HrManagerTeamService())->teamLeave(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            is_string($status) ? $status : null
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function requests(): void
    {
        $status = $this->input('status', null);
        $result = (new HrManagerTeamService())->teamRequests(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            is_string($status) ? $status : null
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function approvals(): void
    {
        $type = $this->input('type', null);
        $result = (new HrManagerTeamService())->teamApprovals(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            is_string($type) ? $type : null
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function decide(): void
    {
        $payload = $this->jsonBody();
        $result = (new HrManagerTeamService())->decide(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (string) ($payload['source_key'] ?? ''),
            (int) ($payload['record_id'] ?? $payload['id'] ?? 0),
            (string) ($payload['action'] ?? 'approve'),
            isset($payload['comment']) ? (string) $payload['comment'] : null
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function employee(array $params = []): void
    {
        $result = (new HrManagerTeamService())->teamEmployeeProfile(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (int) ($params['id'] ?? 0)
        );
        Response::json($result['body'], (int) $result['status']);
    }

    /** @return array<string,mixed> */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return is_array($_POST) ? $_POST : [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
