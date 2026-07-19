<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Services\HrService;

/**
 * Thin ESS adapter — leave balances only.
 * ONE service: HrService::leaveBalancesForEmployee
 */
final class HrEssLeaveController extends Controller
{
    public function balances(): void
    {
        $rows = (new HrService())->leaveBalancesForEmployee(
            (int) $this->input('employee_id', 0),
            (int) $this->input('year', date('Y'))
        );
        Response::json([
            'success' => true,
            'balances' => $rows,
        ]);
    }
}
