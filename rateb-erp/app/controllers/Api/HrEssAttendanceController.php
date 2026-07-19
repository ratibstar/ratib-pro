<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssEmployeeResolverService;
use Rateb\App\Services\HrService;

/**
 * Thin ESS adapter — attendance day lookup.
 * Identity: HrEssEmployeeResolverService only (never client employee_id).
 * Data: HrService::findAttendanceByEmployeeDate
 */
final class HrEssAttendanceController extends Controller
{
    public function today(): void
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee(
            TenantContext::apiUserId(),
            TenantContext::companyId()
        );
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            Response::json($resolved['body'], (int) $resolved['status']);
            return;
        }

        $employee = $resolved['body']['employee'] ?? null;
        $employeeId = (int) (is_array($employee) ? ($employee['id'] ?? 0) : 0);
        $date = (string) $this->input('date', date('Y-m-d'));

        $row = (new HrService())->findAttendanceByEmployeeDate(
            (int) TenantContext::companyId(),
            $employeeId,
            $date
        );
        Response::json([
            'success' => true,
            'attendance' => $row,
        ]);
    }
}
