<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssAttendanceService;

/**
 * Thin ESS attendance adapter — identity via resolver only.
 * Routes: today / history / check-in / check-out.
 */
final class HrEssAttendanceController extends Controller
{
    public function today(): void
    {
        $result = (new HrEssAttendanceService())->today(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $this->optionalString('date')
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function history(): void
    {
        $result = (new HrEssAttendanceService())->history(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $this->optionalString('from'),
            $this->optionalString('to')
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function checkIn(): void
    {
        $result = (new HrEssAttendanceService())->checkIn(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $this->jsonBody()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function checkOut(): void
    {
        $result = (new HrEssAttendanceService())->checkOut(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $this->jsonBody()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    private function optionalString(string $key): ?string
    {
        $v = $this->input($key, null);
        if ($v === null || $v === '') {
            return null;
        }

        return is_string($v) ? $v : (string) $v;
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
