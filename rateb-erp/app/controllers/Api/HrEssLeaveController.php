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
        $rows = $this->essPrimaryLeaveBalances(is_array($rows) ? $rows : []);
        Response::json([
            'success' => true,
            'balances' => $rows,
        ]);
    }

    /**
     * ESS leave list: primary types first; drop maternity/paternity/iddah noise.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function essPrimaryLeaveBalances(array $rows): array
    {
        $skip = ['maternity' => true, 'paternity' => true, 'iddah' => true];
        $preferred = ['annual', 'sick', 'emergency', 'unpaid', 'hajj', 'marriage', 'bereavement'];
        $byCode = [];
        foreach ($rows as $row) {
            $code = strtolower(trim((string) ($row['leave_type_code'] ?? '')));
            if ($code === '' || isset($skip[$code])) {
                continue;
            }
            $byCode[$code] = $row;
        }
        $ordered = [];
        foreach ($preferred as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code];
                unset($byCode[$code]);
            }
        }
        foreach ($byCode as $row) {
            $ordered[] = $row;
        }

        return $ordered;
    }
}
