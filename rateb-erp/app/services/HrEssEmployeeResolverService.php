<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Employee;

/**
 * ESS identity resolver — existing rateb_employees.user_id linkage only.
 * Owns lookup, tenant scope, cardinality, and response payload shape for /api/v1/hr/me.
 */
final class HrEssEmployeeResolverService
{
    /**
     * Resolve the employee bound to an authenticated API user.
     *
     * @return array{status:int, body:array<string, mixed>}
     */
    public function resolveCurrentEmployee(?int $userId, ?int $companyId): array
    {
        $userId = (int) ($userId ?? 0);
        $companyId = (int) ($companyId ?? 0);
        if ($userId < 1 || $companyId < 1) {
            return [
                'status' => 401,
                'body' => [
                    'success' => false,
                    'code' => 'unauthorized',
                    'message' => 'Unauthorized',
                ],
            ];
        }

        $rows = (new Employee())->query(
            'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                    department_id, job_title_id, branch_id, hire_date
             FROM rateb_employees
             WHERE company_id = :cid AND user_id = :uid
             LIMIT 3',
            ['cid' => $companyId, 'uid' => $userId]
        );

        $count = count($rows);
        if ($count < 1) {
            return [
                'status' => 404,
                'body' => [
                    'success' => false,
                    'code' => 'employee_unbound',
                    'message' => 'No employee linked to this user',
                ],
            ];
        }
        if ($count > 1) {
            return [
                'status' => 409,
                'body' => [
                    'success' => false,
                    'code' => 'employee_ambiguous',
                    'message' => 'Multiple employees linked to this user',
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'employee' => $rows[0],
            ],
        ];
    }
}
