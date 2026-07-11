<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\EamAsset;
use Rateb\App\Models\EamMaintenanceRequest;
use Rateb\App\Models\EamWorkOrder;

/**
 * Shared helpers for Phase 19A EAM domain services.
 * Future Offline (19B) must call domain services — never duplicate these helpers offline.
 * Distinct from legacy Asset model / AssetDeviceWorkflowService.
 */
final class AssetSupport
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

    public static function nextAssetNo(int $companyId): string
    {
        $row = (new EamAsset())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eam_assets WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'EAM-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function nextRequestNo(int $companyId): string
    {
        $row = (new EamMaintenanceRequest())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eam_maintenance_requests WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'MRQ-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function nextWorkOrderNo(int $companyId): string
    {
        $row = (new EamWorkOrder())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eam_work_orders WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'WO-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function nextCode(string $table, string $prefix, int $companyId): string
    {
        $allowed = [
            'rateb_eam_asset_categories', 'rateb_eam_manufacturers', 'rateb_eam_vendors',
            'rateb_eam_locations', 'rateb_eam_asset_models', 'rateb_eam_maintenance_plans',
            'rateb_eam_checklists', 'rateb_eam_tags',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new EamAsset())->queryOne(
            'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return $prefix . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed>|null */
    public static function findAsset(int $assetId, int $companyId): ?array
    {
        if ($assetId < 1 || $companyId < 1) {
            return null;
        }
        $row = (new EamAsset())->queryOne(
            'SELECT * FROM rateb_eam_assets WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $assetId, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertAsset(int $assetId, int $companyId): array
    {
        $row = self::findAsset($assetId, $companyId);
        if ($row === null) {
            throw new \RuntimeException('asset_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findMaintenanceRequest(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new EamMaintenanceRequest())->queryOne(
            'SELECT * FROM rateb_eam_maintenance_requests WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertMaintenanceRequest(int $id, int $companyId): array
    {
        $row = self::findMaintenanceRequest($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('maintenance_request_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findWorkOrder(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new EamWorkOrder())->queryOne(
            'SELECT * FROM rateb_eam_work_orders WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertWorkOrder(int $id, int $companyId): array
    {
        $row = self::findWorkOrder($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('work_order_not_found');
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
