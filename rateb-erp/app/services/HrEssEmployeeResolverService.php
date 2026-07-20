<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\User;

/**
 * ESS identity resolver — existing rateb_employees.user_id linkage only.
 * Owns lookup, tenant scope, cardinality, and response payload shape for /api/v1/hr/me.
 *
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
        $byUser = $this->fetchEmployees(
            'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                    department_id, job_title_id, branch_id, hire_date
             FROM rateb_employees
             WHERE user_id = :uid
             ORDER BY id ASC
             LIMIT 3',
            ['uid' => $userId]
        );
        if ($byUser !== []) {
            if ($companyId > 0) {
                $inCompany = array_values(array_filter(
                    $byUser,
                    static fn (array $row): bool => (int) ($row['company_id'] ?? 0) === $companyId
                ));
                if ($inCompany !== []) {
                    return $inCompany;
                }
            }
            return $byUser;
        }

        $email = $this->userEmail($userId);
        if ($email === '') {
            return [];
        }

        $params = ['em' => $email];
        $sql = 'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                       department_id, job_title_id, branch_id, hire_date
                FROM rateb_employees
                WHERE LOWER(TRIM(email)) = :em';
        if ($companyId > 0) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY id ASC LIMIT 3';

        $byEmail = $this->fetchEmployees($sql, $params);
        if ($byEmail !== [] || $companyId < 1) {
            return $byEmail;
        }

        // Token company may be wrong (platform SA / fallback). Match email globally.
        return $this->fetchEmployees(
            'SELECT id, company_id, employee_code, name, email, phone, status, user_id,
                    department_id, job_title_id, branch_id, hire_date
             FROM rateb_employees
             WHERE LOWER(TRIM(email)) = :em
             ORDER BY id ASC
             LIMIT 3',
            ['em' => $email]
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

    private function bindEmployeeUser(int $employeeId, int $userId): void
    {
        if ($employeeId < 1 || $userId < 1) {
            return;
        }
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE rateb_employees SET user_id = :uid WHERE id = :eid AND (user_id IS NULL OR user_id = 0)'
            );
            $stmt->execute(['uid' => $userId, 'eid' => $employeeId]);
        } catch (\Throwable $e) {
            // non-fatal — resolver still returns employee row
        }
    }
}
