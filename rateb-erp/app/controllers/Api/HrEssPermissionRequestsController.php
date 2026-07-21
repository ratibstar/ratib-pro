<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssPermissionRequestService;

/** Thin ESS permission-request adapter — identity via resolver only. */
final class HrEssPermissionRequestsController extends Controller
{
    public function list(): void
    {
        $status = $this->input('status', null);
        $statusStr = is_string($status) ? $status : null;
        $result = (new HrEssPermissionRequestService())->listMine(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $statusStr
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function show(array $params = []): void
    {
        $result = (new HrEssPermissionRequestService())->show(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (int) ($params['id'] ?? 0)
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function submit(): void
    {
        $result = (new HrEssPermissionRequestService())->submit(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $this->jsonBody()
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
