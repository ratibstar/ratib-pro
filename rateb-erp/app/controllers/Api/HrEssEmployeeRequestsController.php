<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssPhaseCService;

/** Thin ESS employee requests adapter. */
final class HrEssEmployeeRequestsController extends Controller
{
    public function list(): void
    {
        $type = $this->input('type', null);
        $typeStr = is_string($type) ? $type : null;
        $result = (new HrEssPhaseCService())->listEmployeeRequests(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $typeStr
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function show(array $params = []): void
    {
        $result = (new HrEssPhaseCService())->getEmployeeRequest(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (int) ($params['id'] ?? 0)
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function create(): void
    {
        $payload = $this->jsonBody();
        $result = (new HrEssPhaseCService())->submitInquiry(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $payload
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
