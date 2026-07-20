<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssLeaveService;

/**
 * Thin ESS leave adapter — identity via resolver only.
 */
final class HrEssLeaveController extends Controller
{
    public function balances(): void
    {
        $yearRaw = $this->input('year', null);
        $year = is_numeric($yearRaw) ? (int) $yearRaw : null;
        $result = (new HrEssLeaveService())->balances(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $year
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function requests(): void
    {
        $status = $this->input('status', null);
        $statusStr = is_string($status) ? $status : null;
        $result = (new HrEssLeaveService())->listRequests(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $statusStr
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function show(array $params = []): void
    {
        $result = (new HrEssLeaveService())->getRequest(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (int) ($params['id'] ?? 0)
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function apply(): void
    {
        $result = (new HrEssLeaveService())->apply(
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
