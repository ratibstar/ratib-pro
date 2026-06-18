<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

final class TenantGuard
{
    /** @var array<int, string> */
    private const ALLOWED_TABLES = [
        'rateb_assets',
        'rateb_medical_devices',
        'rateb_contracts',
        'rateb_suppliers',
        'rateb_inventory',
        'rateb_warehouses',
        'rateb_product_categories',
        'rateb_approval_instances',
        'rateb_chart_of_accounts',
        'rateb_journal_entries',
        'rateb_rfq',
    ];

    public static function resolveCompanyId(): int
    {
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid > 0) {
            return $cid;
        }
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
            $cid = (int) (TenantContext::companyId() ?? 0);
            if ($cid > 0) {
                return $cid;
            }
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            return rateb_resolve_ops_company_id();
        }
        return 0;
    }

    public static function requireCompanyId(): int
    {
        $cid = self::resolveCompanyId();
        if ($cid < 1) {
            $msg = function_exists('__') ? __('select_company_ops') : 'Company context required.';
            throw new \RuntimeException($msg);
        }
        return $cid;
    }

    public static function belongsToCompany(string $table, int $id, int $companyId): bool
    {
        if ($id < 1 || $companyId < 1 || !in_array($table, self::ALLOWED_TABLES, true)) {
            return false;
        }
        $db = Database::connection();
        $stmt = $db->prepare("SELECT id FROM {$table} WHERE id = :id AND company_id = :cid LIMIT 1");
        $stmt->execute(['id' => $id, 'cid' => $companyId]);
        return (bool) $stmt->fetch();
    }

    public static function assertBelongsToCompany(string $table, int $id, ?int $companyId = null): void
    {
        if (TenantContext::isSuperAdmin()) {
            return;
        }
        $cid = $companyId ?? self::requireCompanyId();
        if (!self::belongsToCompany($table, $id, $cid)) {
            throw new \RuntimeException('Resource not found or access denied.');
        }
    }

    public static function assertAsset(int $assetId, ?int $companyId = null): void
    {
        self::assertBelongsToCompany('rateb_assets', $assetId, $companyId);
    }

    public static function assertDevice(int $deviceId, ?int $companyId = null): void
    {
        self::assertBelongsToCompany('rateb_medical_devices', $deviceId, $companyId);
    }

    public static function assertContract(int $contractId, ?int $companyId = null): void
    {
        self::assertBelongsToCompany('rateb_contracts', $contractId, $companyId);
    }

    public static function assertSupplier(int $supplierId, ?int $companyId = null): void
    {
        self::assertBelongsToCompany('rateb_suppliers', $supplierId, $companyId);
    }

    public static function assertWarehouse(int $warehouseId, ?int $companyId = null): void
    {
        self::assertBelongsToCompany('rateb_warehouses', $warehouseId, $companyId);
    }

    public static function assertInventory(int $inventoryId, ?int $companyId = null): void
    {
        self::assertBelongsToCompany('rateb_inventory', $inventoryId, $companyId);
    }

    public static function assertApprovalInstance(int $instanceId, ?int $companyId = null): array
    {
        $cid = TenantContext::isSuperAdmin() ? null : ($companyId ?? self::requireCompanyId());
        $db = Database::connection();
        if ($cid === null) {
            $row = (new \Rateb\App\Models\Inventory())->queryOne(
                'SELECT * FROM rateb_approval_instances WHERE id = :id LIMIT 1',
                ['id' => $instanceId]
            );
        } else {
            $row = (new \Rateb\App\Models\Inventory())->queryOne(
                'SELECT * FROM rateb_approval_instances WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $instanceId, 'cid' => $cid]
            );
        }
        if (!$row) {
            throw new \RuntimeException('Approval instance not found or access denied.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    public static function assertWarehouseTransfer(int $transferId, ?int $companyId = null): array
    {
        $cid = TenantContext::isSuperAdmin() ? null : ($companyId ?? self::requireCompanyId());
        $db = Database::connection();
        if ($cid === null) {
            $stmt = $db->prepare('SELECT * FROM rateb_warehouse_transfers WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $transferId]);
        } else {
            $stmt = $db->prepare('SELECT * FROM rateb_warehouse_transfers WHERE id = :id AND company_id = :cid LIMIT 1');
            $stmt->execute(['id' => $transferId, 'cid' => $cid]);
        }
        $row = $stmt->fetch();
        if (!$row) {
            throw new \RuntimeException('Warehouse transfer not found or access denied.');
        }
        return $row;
    }
}
