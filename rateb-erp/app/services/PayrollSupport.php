<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\PayrollBatch;
use Rateb\App\Models\PayrollCycle;
use Rateb\App\Models\PayrollLoan;
use Rateb\App\Models\PayrollSalaryStructure;

/**
 * Shared helpers for Phase 24A Enterprise Payroll domain services.
 * Soft-links hrm_employee_profile_id / legacy_employee_id / legacy_payroll_period_id — no FKs to frozen tables.
 * No auto GL posting; accounting_post_ref is metadata only.
 */
final class PayrollSupport
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
            'rateb_payroll_salary_structures',
            'rateb_payroll_cycles',
            'rateb_payroll_run_periods',
            'rateb_payroll_batches',
            'rateb_payroll_loans',
            'rateb_payroll_advances',
            'rateb_payroll_overtime',
            'rateb_payroll_bonuses',
            'rateb_payroll_commissions',
            'rateb_payroll_adjustments',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new PayrollBatch())->queryOne(
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
    public static function findBatch(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new PayrollBatch())->queryOne(
            'SELECT * FROM rateb_payroll_batches WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertBatch(int $id, int $companyId): array
    {
        $row = self::findBatch($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('payroll_batch_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findStructure(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new PayrollSalaryStructure())->queryOne(
            'SELECT * FROM rateb_payroll_salary_structures WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertStructure(int $id, int $companyId): array
    {
        $row = self::findStructure($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('salary_structure_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findCycle(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new PayrollCycle())->queryOne(
            'SELECT * FROM rateb_payroll_cycles WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public static function findLoan(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new PayrollLoan())->queryOne(
            'SELECT * FROM rateb_payroll_loans WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
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

    public static function dateOrNull(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }

        return substr($s, 0, 32);
    }
}
