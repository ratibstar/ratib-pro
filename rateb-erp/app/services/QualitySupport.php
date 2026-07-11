<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\QmsCorrectiveAction;
use Rateb\App\Models\QmsInspection;
use Rateb\App\Models\QmsPlan;

/**
 * Shared helpers for Phase 25A Enterprise Quality (QMS) domain services.
 * Soft-links mfg_quality_check_id / eam_inspection_id / legacy_supplier_id / hrm_training_id — no FKs to frozen tables.
 */
final class QualitySupport
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
            'rateb_qms_programs',
            'rateb_qms_plans',
            'rateb_qms_standards',
            'rateb_qms_checklists',
            'rateb_qms_inspections',
            'rateb_qms_defects',
            'rateb_qms_nonconformities',
            'rateb_qms_corrective_actions',
            'rateb_qms_preventive_actions',
            'rateb_qms_audits',
            'rateb_qms_complaints',
            'rateb_qms_supplier_quality',
            'rateb_qms_training',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new QmsPlan())->queryOne(
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
    public static function findInspection(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new QmsInspection())->queryOne(
            'SELECT * FROM rateb_qms_inspections WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertInspection(int $id, int $companyId): array
    {
        $row = self::findInspection($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('inspection_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findCorrectiveAction(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new QmsCorrectiveAction())->queryOne(
            'SELECT * FROM rateb_qms_corrective_actions WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertCorrectiveAction(int $id, int $companyId): array
    {
        $row = self::findCorrectiveAction($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('corrective_action_not_found');
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

    public static function dateOrNull(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }

        return substr($s, 0, 32);
    }
}
