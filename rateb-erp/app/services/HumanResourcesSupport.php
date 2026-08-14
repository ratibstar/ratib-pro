<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\HrmDepartment;
use Rateb\App\Models\HrmEmployeeProfile;
use Rateb\App\Models\HrmPerformanceReview;
use Rateb\App\Models\HrmTraining;

/**
 * Shared helpers for Phase 23A Enterprise HR (HRMS) domain services.
 * Soft-links legacy_employee_id / legacy_job_title_id / legacy_workplace_id / legacy_document_id — no FKs to frozen tables.
 */
final class HumanResourcesSupport
{
    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function requireCompanyId(): int
    {
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid < 1) {
            throw new \RuntimeException('company_required');
        }

        return $cid;
    }

    public static function branchId(): ?int
    {
        $bid = (int) (SessionManager::get('rateb_branch_id') ?? SessionManager::get('branch_id') ?? 0);

        return $bid > 0 ? $bid : null;
    }

    public static function userId(): ?int
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $uid > 0 ? $uid : null;
    }

    /** @return array<string, mixed> */
    public static function actorFields(bool $creating = true): array
    {
        $uid = self::userId();
        $out = ['updated_by' => $uid];
        if ($creating) {
            $out['created_by'] = $uid;
        }

        return $out;
    }

    public static function nextCode(string $table, string $prefix, int $companyId): string
    {
        $allowed = [
            'rateb_hrm_departments',
            'rateb_hrm_positions',
            'rateb_hrm_grades',
            'rateb_hrm_locations',
            'rateb_hrm_org_units',
            'rateb_hrm_employee_profiles',
            'rateb_hrm_training',
            'rateb_hrm_performance_reviews',
            'rateb_hrm_skills',
            'rateb_hrm_competencies',
            'rateb_hrm_disciplinary_actions',
            'rateb_hrm_rewards',
            'rateb_hrm_transfers',
            'rateb_hrm_promotions',
            'rateb_hrm_tags',
            'rateb_hrm_certifications',
            'rateb_hrm_licenses',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new HrmEmployeeProfile())->queryOne(
            'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return $prefix . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function assertOptimisticVersion(array $row, mixed $expectedVersion): void
    {
        if ($expectedVersion === null || $expectedVersion === '') {
            return;
        }
        if ((int) $expectedVersion !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
    }

    /** @return array<string, mixed>|null */
    public static function findProfile(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new HrmEmployeeProfile())->queryOne(
            'SELECT * FROM rateb_hrm_employee_profiles WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertProfile(int $id, int $companyId): array
    {
        $row = self::findProfile($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('employee_profile_not_found');
        }

        return $row;
    }

    /**
     * Phase C — legacy_employee_id must point at ops rateb_employees in the same company.
     * Null/0 clears the soft-link (allowed). Cross-company or missing employee → deny.
     */
    public static function assertLegacyEmployee(?int $legacyEmployeeId, int $companyId): void
    {
        if ($legacyEmployeeId === null || $legacyEmployeeId < 1) {
            return;
        }
        if ($companyId < 1) {
            throw new \InvalidArgumentException('legacy_employee_company_required');
        }
        $row = (new \Rateb\App\Models\Employee())->queryOne(
            'SELECT id FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $legacyEmployeeId, 'cid' => $companyId]
        );
        if (!is_array($row) || (int) ($row['id'] ?? 0) < 1) {
            throw new \InvalidArgumentException('legacy_employee_tenant_mismatch');
        }
    }

    /** @return array<string, mixed>|null */
    public static function findDepartment(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new HrmDepartment())->queryOne(
            'SELECT * FROM rateb_hrm_departments WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertDepartment(int $id, int $companyId): array
    {
        $row = self::findDepartment($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('department_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findTraining(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new HrmTraining())->queryOne(
            'SELECT * FROM rateb_hrm_training WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertTraining(int $id, int $companyId): array
    {
        $row = self::findTraining($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('training_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findPerformanceReview(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new HrmPerformanceReview())->queryOne(
            'SELECT * FROM rateb_hrm_performance_reviews WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertPerformanceReview(int $id, int $companyId): array
    {
        $row = self::findPerformanceReview($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('performance_review_not_found');
        }

        return $row;
    }

    public static function nullIfEmpty(mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }
        if (is_string($v) && trim($v) === '') {
            return null;
        }

        return $v;
    }

    public static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    public static function floatOrZero(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }

        return (float) $v;
    }

    public static function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }

    public static function dateOrNull(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }

        return substr($s, 0, 32);
    }
}
