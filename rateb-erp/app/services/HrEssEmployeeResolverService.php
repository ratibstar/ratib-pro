<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\User;

/**
 * ESS identity resolver — existing rateb_employees.user_id linkage only.
 * Owns lookup, tenant scope, cardinality, and response payload shape for /api/v1/hr/me.
 *
 * Phase B: every resolution and bind is company-scoped. Cross-tenant fallbacks removed.
 * Lookups use unscoped SQL (bypass branch filters) so mobile login works for
 * company employees even when the ERP user was mis-tagged as platform SA.
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
        if ($userId < 1) {
            return [
                'status' => 401,
                'body' => [
                    'success' => false,
                    'code' => 'unauthorized',
                    'message' => 'Unauthorized',
                ],
            ];
        }
        if ($companyId < 1) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'code' => 'company_required',
                    'message' => 'Company context required',
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
        if ((int) ($employee['company_id'] ?? 0) !== $companyId) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'code' => 'tenant_mismatch',
                    'message' => 'Employee does not belong to this company',
                ],
            ];
        }
        if ((int) ($employee['user_id'] ?? 0) < 1) {
            $bound = $this->bindEmployeeUser((int) $employee['id'], $userId, $companyId);
            if ($bound) {
                $employee['user_id'] = $userId;
            }
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'employee' => $employee,
            ],
        ];
    }

    /**
     * Bind user to employee only when both belong to the same company.
     * Returns false when the bind is denied or no row updated.
     */
    public function bindEmployeeUser(int $employeeId, int $userId, int $companyId): bool
    {
        if ($employeeId < 1 || $userId < 1 || $companyId < 1) {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE rateb_employees
                 SET user_id = :uid
                 WHERE id = :eid
                   AND company_id = :cid
                   AND (user_id IS NULL OR user_id = 0 OR user_id = :uid2)'
            );
            $stmt->execute([
                'uid' => $userId,
                'uid2' => $userId,
                'eid' => $employeeId,
                'cid' => $companyId,
            ]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    private function employeesForUser(int $userId, int $companyId): array
    {
        $byUser = $this->fetchEmployees(
            'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                    department_id, job_title_id, branch_id, hire_date
             FROM rateb_employees
             WHERE user_id = :uid AND company_id = :cid
             ORDER BY id ASC
             LIMIT 3',
            ['uid' => $userId, 'cid' => $companyId]
        );
        if ($byUser !== []) {
            return $byUser;
        }

        $email = $this->userEmail($userId);
        if ($email === '') {
            return [];
        }

        return $this->fetchEmployees(
            'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                    department_id, job_title_id, branch_id, hire_date
             FROM rateb_employees
             WHERE LOWER(TRIM(email)) = :em AND company_id = :cid
             ORDER BY id ASC
             LIMIT 3',
            ['em' => $email, 'cid' => $companyId]
        );
    }

    private function userEmail(int $userId): string
    {
        $user = (new User())->find($userId);
        return strtolower(trim((string) ($user['email'] ?? '')));
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function fetchEmployees(string $sql, array $params): array
    {
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
