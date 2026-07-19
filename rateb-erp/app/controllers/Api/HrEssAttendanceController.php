<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrService;

/**
 * Thin ESS adapter — attendance day lookup only.
 * ONE service: HrService::findAttendanceByEmployeeDate
 */
final class HrEssAttendanceController extends Controller
{
    public function today(): void
    {
        $row = (new HrService())->findAttendanceByEmployeeDate(
            (int) TenantContext::companyId(),
            (int) $this->input('employee_id', 0),
            (string) $this->input('date', date('Y-m-d'))
        );
        Response::json([
            'success' => true,
            'attendance' => $row,
        ]);
    }
}
