<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Support;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Services\Bridge\PosBranchBridgeService;
use Rateb\App\Services\TenantFkValidator;

/** FK + branch access validation for POS entities. */
final class PosFkValidator
{
    /** @param array<string, mixed> $data @param array<int, string> $fields */
    public static function validateTenantFks(array $data, array $fields): void
    {
        TenantFkValidator::validate($data, $fields);
    }

    public static function assertBranchAccess(int $branchId): void
    {
        if ($branchId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        (new PosBranchBridgeService())->assertCanAccess($branchId);
    }

    /** @param array<string, mixed> $data */
    public static function validateTerminal(array $data, int $companyId): void
    {
        self::validateTenantFks($data, ['branch_id', 'warehouse_id']);
        $branchId = (int) ($data['branch_id'] ?? 0);
        if ($branchId > 0) {
            self::assertBranchAccess($branchId);
        }
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        if ($warehouseId > 0 && $branchId > 0) {
            self::assertWarehouseInBranch($warehouseId, $branchId, $companyId);
        }
    }

    public static function assertTerminal(int $terminalId, int $companyId, ?int $branchId = null): array
    {
        if ($terminalId < 1 || $companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_terminals WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $terminalId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        $termBranch = (int) ($row['branch_id'] ?? 0);
        self::assertBranchAccess($termBranch);
        if ($branchId !== null && $branchId > 0 && $termBranch !== $branchId) {
            throw new \RuntimeException(__('access_denied'));
        }
        return $row;
    }

    public static function assertShift(int $shiftId, int $companyId): array
    {
        if ($shiftId < 1 || $companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_shifts WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $shiftId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        self::assertBranchAccess((int) ($row['branch_id'] ?? 0));
        return $row;
    }

    public static function assertDrawer(int $drawerId, int $companyId): array
    {
        if ($drawerId < 1 || $companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_cash_drawers WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $drawerId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        self::assertBranchAccess((int) ($row['branch_id'] ?? 0));
        return $row;
    }

    private static function assertWarehouseInBranch(int $warehouseId, int $branchId, int $companyId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT branch_id FROM rateb_warehouses WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $warehouseId, 'cid' => $companyId]);
        $whBranch = $stmt->fetchColumn();
        if ($whBranch === false) {
            throw new \RuntimeException(__('db_fk_violation'));
        }
        if ($whBranch !== null && (int) $whBranch > 0 && (int) $whBranch !== $branchId) {
            throw new \RuntimeException(__('branch_warehouse_mismatch'));
        }
    }
}
