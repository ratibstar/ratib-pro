<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Employee;
use Rateb\App\Models\User;

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

        $rows = $this->employeesForUser($userId, $companyId);

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

        $employee = $rows[0];
        if ((int) ($employee['user_id'] ?? 0) < 1) {
            $this->bindEmployeeUser((int) $employee['id'], $userId);
            $employee['user_id'] = $userId;
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'employee' => $employee,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function employeesForUser(int $userId, int $companyId): array
    {
        $employee = new Employee();
        $rows = $employee->query(
            'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                    department_id, job_title_id, branch_id, hire_date
             FROM rateb_employees
             WHERE company_id = :cid AND user_id = :uid
             LIMIT 3',
            ['cid' => $companyId, 'uid' => $userId]
        );
        if (is_array($rows) && $rows !== []) {
            return $rows;
        }

        $user = (new User())->find($userId);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email === '') {
            return [];
        }

        return $employee->query(
            'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                    department_id, job_title_id, branch_id, hire_date
             FROM rateb_employees
             WHERE company_id = :cid AND LOWER(TRIM(email)) = :em
             LIMIT 3',
            ['cid' => $companyId, 'em' => $email]
        ) ?: [];
    }

    private function bindEmployeeUser(int $employeeId, int $userId): void
    {
        if ($employeeId < 1 || $userId < 1) {
            return;
        }
        try {
            (new Employee())->queryOne(
                'UPDATE rateb_employees SET user_id = :uid WHERE id = :eid AND (user_id IS NULL OR user_id = 0)',
                ['uid' => $userId, 'eid' => $employeeId]
            );
        } catch (\Throwable $e) {
            // non-fatal — resolver still returns employee row
        }
    }
}
