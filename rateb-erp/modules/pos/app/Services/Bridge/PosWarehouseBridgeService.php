<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Models\Warehouse;
use Rateb\App\Services\WarehouseService;

/** Warehouse bridge — resolve default warehouse per branch via ERP. */
final class PosWarehouseBridgeService
{
    public function warehouseService(): WarehouseService
    {
        return new WarehouseService();
    }

    public function ensureDefaultWarehouse(int $companyId): int
    {
        return (new WarehouseService())->ensureDefaultWarehouse($companyId);
    }

    /** @return array{id: int, name: string}|null */
    public function label(int $warehouseId): ?array
    {
        if ($warehouseId < 1) {
            return null;
        }
        $row = (new Warehouse())->find($warehouseId);
        if (!$row) {
            return null;
        }
        return [
            'id' => $warehouseId,
            'name' => trim((string) ($row['name'] ?? $row['name_ar'] ?? '')),
        ];
    }
}
