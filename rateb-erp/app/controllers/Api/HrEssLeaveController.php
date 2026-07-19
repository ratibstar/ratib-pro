<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssEmployeeResolverService;
use Rateb\App\Services\HrService;

/**
 * Thin ESS adapter — leave balances.
 * Identity: HrEssEmployeeResolverService only (never client employee_id).
 * Data: HrService::leaveBalancesForEmployee
 */
final class HrEssLeaveController extends Controller
{
    public function balances(): void
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
        $year = (int) $this->input('year', date('Y'));

        $rows = (new HrService())->leaveBalancesForEmployee($employeeId, $year);
        Response::json([
            'success' => true,
            'balances' => $rows,
        ]);
    }
}
