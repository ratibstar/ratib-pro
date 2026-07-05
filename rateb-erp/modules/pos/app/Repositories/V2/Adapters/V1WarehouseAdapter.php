<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\DTO\V2\Register\PosV2WarehouseContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2WarehousePortInterface;
use Rateb\App\Pos\Services\Bridge\PosWarehouseBridgeService;

/** Wraps V1 PosWarehouseBridgeService — read-only warehouse label. */
final class V1WarehouseAdapter implements PosV2WarehousePortInterface
{
    public function __construct(
        private readonly PosWarehouseBridgeService $warehouseBridge = new PosWarehouseBridgeService(),
    ) {
    }

    public function resolve(int $warehouseId): ?PosV2WarehouseContext
    {
        if ($warehouseId < 1) {
            return null;
        }

        $label = $this->warehouseBridge->label($warehouseId);
        if ($label === null) {
            return null;
        }

        return new PosV2WarehouseContext(
            id: (int) $label['id'],
            name: (string) $label['name'],
        );
    }
}
