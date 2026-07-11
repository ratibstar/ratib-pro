<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\MfgBom;
use Rateb\App\Models\MfgProductionOrder;
use Rateb\App\Models\MfgProduct;
use Rateb\App\Models\MfgWorkOrder;

/**
 * Shared helpers for Phase 22A Manufacturing (MRP) domain services.
 * Soft-links inventory_item_id / warehouse_id / project_id / eam_asset_id / cost_center_id — no frozen-table FKs.
 */
final class ManufacturingSupport
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
            'rateb_mfg_products',
            'rateb_mfg_product_variants',
            'rateb_mfg_boms',
            'rateb_mfg_work_centers',
            'rateb_mfg_machines',
            'rateb_mfg_routings',
            'rateb_mfg_routing_operations',
            'rateb_mfg_production_orders',
            'rateb_mfg_work_orders',
            'rateb_mfg_quality_checks',
            'rateb_mfg_tags',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new MfgProduct())->queryOne(
            'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return $prefix . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed>|null */
    public static function findProduct(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new MfgProduct())->queryOne(
            'SELECT * FROM rateb_mfg_products WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertProduct(int $id, int $companyId): array
    {
        $row = self::findProduct($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('product_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findBom(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new MfgBom())->queryOne(
            'SELECT * FROM rateb_mfg_boms WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertBom(int $id, int $companyId): array
    {
        $row = self::findBom($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('bom_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findProductionOrder(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new MfgProductionOrder())->queryOne(
            'SELECT * FROM rateb_mfg_production_orders WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertProductionOrder(int $id, int $companyId): array
    {
        $row = self::findProductionOrder($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('production_order_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findWorkOrder(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new MfgWorkOrder())->queryOne(
            'SELECT * FROM rateb_mfg_work_orders WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
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
