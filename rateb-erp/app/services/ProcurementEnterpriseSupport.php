<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\EprocContract;
use Rateb\App\Models\EprocSupplierProfile;
use Rateb\App\Models\EprocTender;

/**
 * Shared helpers for Phase 21A EPROC domain services.
 * Distinct from legacy ProcurementService / rateb_purchase_* / Offline Foundation.
 */
final class ProcurementEnterpriseSupport
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
            'rateb_eproc_supplier_categories',
            'rateb_eproc_supplier_profiles',
            'rateb_eproc_supplier_sla',
            'rateb_eproc_supplier_qualification',
            'rateb_eproc_rfq_templates',
            'rateb_eproc_tenders',
            'rateb_eproc_contracts',
            'rateb_eproc_tags',
            'rateb_eproc_supplier_risk',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new EprocSupplierProfile())->queryOne(
            'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return $prefix . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed>|null */
    public static function findProfile(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new EprocSupplierProfile())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_profiles WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertProfile(int $id, int $companyId): array
    {
        $row = self::findProfile($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('supplier_profile_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findTender(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new EprocTender())->queryOne(
            'SELECT * FROM rateb_eproc_tenders WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertTender(int $id, int $companyId): array
    {
        $row = self::findTender($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('tender_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findContract(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new EprocContract())->queryOne(
            'SELECT * FROM rateb_eproc_contracts WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertContract(int $id, int $companyId): array
    {
        $row = self::findContract($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('contract_not_found');
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

    public static function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }
}
