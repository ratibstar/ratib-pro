<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Employee;

/**
 * Thin ESS identity read — existing rateb_employees.user_id linkage only.
 * No new tables, no HR business rules, no writes.
 */
final class HrEssMeController extends Controller
{
    public function me(): void
    {
        $userId = (int) (TenantContext::apiUserId() ?? 0);
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($userId < 1 || $companyId < 1) {
            Response::json(['success' => false, 'code' => 'unauthorized', 'message' => 'Unauthorized'], 401);
            return;
        }

        $model = new Employee();
        $rows = $model->query(
            'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                    department_id, job_title_id, branch_id, hire_date
             FROM rateb_employees
             WHERE company_id = :cid AND user_id = :uid
             LIMIT 3',
            ['cid' => $companyId, 'uid' => $userId]
        );

        $count = count($rows);
        if ($count < 1) {
            Response::json([
                'success' => false,
                'code' => 'employee_unbound',
                'message' => 'No employee linked to this user',
            ], 404);
            return;
        }
        if ($count > 1) {
            Response::json([
                'success' => false,
                'code' => 'employee_ambiguous',
                'message' => 'Multiple employees linked to this user',
            ], 409);
            return;
        }

        Response::json([
            'success' => true,
            'employee' => $rows[0],
        ]);
    }
}
