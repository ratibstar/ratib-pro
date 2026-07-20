<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Employee;
use Rateb\App\Models\User;

/**
 * ESS Profile — thin read adapter over rateb_employees + lookups.
 * No payroll fields. No credentials. Identity via resolver only.
 */
final class HrEssProfileService
{
    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function getProfile(int $userId, int $companyId): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employee = $resolved['body']['employee'] ?? null;
        if (!is_array($employee)) {
            return $this->fail(404, 'not_found', 'Employee not found');
        }
        $employeeId = (int) ($employee['id'] ?? 0);
        $cid = (int) ($employee['company_id'] ?? $companyId);
        if ($employeeId < 1 || $cid < 1) {
            return $this->fail(404, 'not_found', 'Employee not found');
        }
        if ($companyId > 0 && $cid !== $companyId) {
            return $this->fail(403, 'forbidden', 'Tenant mismatch');
        }

        $row = $this->loadProfileRow($cid, $employeeId);
        if ($row === null) {
            return $this->fail(404, 'not_found', 'Employee profile not found');
        }

        return $this->ok(['profile' => $this->profileDto($row)]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadProfileRow(int $companyId, int $employeeId): ?array
    {
        return (new Employee())->queryOne(
            'SELECT e.id, e.employee_code, e.name, e.email, e.phone, e.status, e.hire_date,
                    e.user_id, e.department_id, e.job_title_id, e.branch_id, e.job_title AS job_title_text,
                    d.name AS department_name,
                    jt.name AS job_title_name,
                    b.name AS branch_name
             FROM rateb_employees e
             LEFT JOIN rateb_hr_departments d
               ON d.id = e.department_id AND d.company_id = e.company_id
             LEFT JOIN rateb_hr_job_titles jt
               ON jt.id = e.job_title_id AND jt.company_id = e.company_id
             LEFT JOIN rateb_branches b
               ON b.id = e.branch_id AND b.company_id = e.company_id
             WHERE e.company_id = :cid AND e.id = :eid
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function profileDto(array $row): array
    {
        $jobTitle = trim((string) ($row['job_title_name'] ?? ''));
        if ($jobTitle === '') {
            $jobTitle = trim((string) ($row['job_title_text'] ?? ''));
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'employee_no' => (string) ($row['employee_code'] ?? ''),
            'full_name' => (string) ($row['name'] ?? ''),
            'photo_url' => $this->photoUrl((int) ($row['user_id'] ?? 0)),
            'email' => $this->nullableString($row['email'] ?? null),
            'phone' => $this->nullableString($row['phone'] ?? null),
            'department' => $this->nullableString($row['department_name'] ?? null),
            'job_title' => $jobTitle !== '' ? $jobTitle : null,
            'branch' => $this->nullableString($row['branch_name'] ?? null),
            'manager' => $this->managerName((int) ($row['id'] ?? 0)),
            'join_date' => $this->nullableString($row['hire_date'] ?? null),
            'status' => (string) ($row['status'] ?? ''),
        ];
    }

    /**
     * Resolve manager display name from HRM profile link when present.
     * Never invents hierarchy — null when not linked.
     */
    private function managerName(int $employeeId): ?string
    {
        if ($employeeId < 1) {
            return null;
        }
        try {
            $profile = (new Employee())->queryOne(
                'SELECT p.manager_profile_id
                 FROM rateb_hrm_employee_profiles p
                 WHERE p.legacy_employee_id = :eid AND p.deleted_at IS NULL
                 LIMIT 1',
                ['eid' => $employeeId]
            );
            $managerProfileId = (int) ($profile['manager_profile_id'] ?? 0);
            if ($managerProfileId < 1) {
                return null;
            }
            $manager = (new Employee())->queryOne(
                'SELECT TRIM(CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\'))) AS full_name,
                        legacy_employee_id
                 FROM rateb_hrm_employee_profiles
                 WHERE id = :mid AND deleted_at IS NULL
                 LIMIT 1',
                ['mid' => $managerProfileId]
            );
            if (!is_array($manager)) {
                return null;
            }
            $name = trim((string) ($manager['full_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
            $legacyId = (int) ($manager['legacy_employee_id'] ?? 0);
            if ($legacyId < 1) {
                return null;
            }
            $legacy = (new Employee())->queryOne(
                'SELECT name FROM rateb_employees WHERE id = :id LIMIT 1',
                ['id' => $legacyId]
            );

            return is_array($legacy) ? $this->nullableString($legacy['name'] ?? null) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function photoUrl(int $userId): ?string
    {
        if ($userId < 1) {
            return null;
        }
        try {
            $user = (new User())->queryOne(
                'SELECT avatar_path FROM rateb_users WHERE id = :id LIMIT 1',
                ['id' => $userId]
            );
            $path = trim((string) ($user['avatar_path'] ?? ''));
            if ($path === '') {
                return null;
            }
            if (preg_match('#^https?://#i', $path)) {
                return $path;
            }
            if (function_exists('rateb_public_url')) {
                return rateb_public_url(ltrim($path, '/'));
            }

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int,body:array<string,mixed>}
     */
    private function ok(array $data): array
    {
        return [
            'status' => 200,
            'body' => ['success' => true, 'data' => $data],
        ];
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function fail(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
